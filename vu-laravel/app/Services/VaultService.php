<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class VaultService
{
    private $client;
    private $baseUrl;
    private $token;
    private $mountPath;
    private $timeout;

    public function __construct()
    {
        $this->baseUrl = config('services.vault.url', 'http://localhost:8200');
        $this->token = config('services.vault.token', '');
        $this->mountPath = config('services.vault.mount_path', 'secret');
        $this->timeout = 30;
        
        $this->client = new Client([
            'timeout' => $this->timeout,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Vault-Token' => $this->token
            ]
        ]);
    }

    /**
     * Store a private key in Vault
     */
    public function storePrivateKey(string $certificateId, string $privateKey): bool
    {
        try {
            $response = $this->client->post("{$this->baseUrl}/v1/{$this->mountPath}/data/certificates/{$certificateId}", [
                'json' => [
                    'data' => [
                        'private_key' => $privateKey,
                        'stored_at' => now()->toISOString(),
                        'certificate_id' => $certificateId
                    ]
                ]
            ]);

            Log::info('Private key stored in Vault', ['certificate_id' => $certificateId]);
            
            // Cache the reference
            Cache::put("vault:cert:{$certificateId}", true, now()->addDays(365));
            
            return true;

        } catch (RequestException $e) {
            Log::error('Failed to store private key in Vault', [
                'error' => $e->getMessage(),
                'certificate_id' => $certificateId
            ]);
            
            return false;
        }
    }

    /**
     * Retrieve a private key from Vault
     */
    public function getPrivateKey(string $certificateId): ?string
    {
        try {
            $response = $this->client->get("{$this->baseUrl}/v1/{$this->mountPath}/data/certificates/{$certificateId}");
            $data = json_decode($response->getBody(), true);
            
            if (isset($data['data']['data']['private_key'])) {
                Log::info('Private key retrieved from Vault', ['certificate_id' => $certificateId]);
                return $data['data']['data']['private_key'];
            }

            return null;

        } catch (RequestException $e) {
            Log::error('Failed to retrieve private key from Vault', [
                'error' => $e->getMessage(),
                'certificate_id' => $certificateId
            ]);
            
            return null;
        }
    }

    /**
     * Delete a private key from Vault
     */
    public function deletePrivateKey(string $certificateId): bool
    {
        try {
            $this->client->delete("{$this->baseUrl}/v1/{$this->mountPath}/data/certificates/{$certificateId}");
            
            Log::info('Private key deleted from Vault', ['certificate_id' => $certificateId]);
            
            // Remove from cache
            Cache::forget("vault:cert:{$certificateId}");
            
            return true;

        } catch (RequestException $e) {
            Log::error('Failed to delete private key from Vault', [
                'error' => $e->getMessage(),
                'certificate_id' => $certificateId
            ]);
            
            return false;
        }
    }

    /**
     * Store any secret in Vault
     */
    public function storeSecret(string $path, array $data): bool
    {
        try {
            $response = $this->client->post("{$this->baseUrl}/v1/{$this->mountPath}/data/{$path}", [
                'json' => [
                    'data' => $data
                ]
            ]);

            Log::info('Secret stored in Vault', ['path' => $path]);
            return true;

        } catch (RequestException $e) {
            Log::error('Failed to store secret in Vault', [
                'error' => $e->getMessage(),
                'path' => $path
            ]);
            
            return false;
        }
    }

    /**
     * Retrieve any secret from Vault
     */
    public function getSecret(string $path): ?array
    {
        try {
            $response = $this->client->get("{$this->baseUrl}/v1/{$this->mountPath}/data/{$path}");
            $data = json_decode($response->getBody(), true);
            
            if (isset($data['data']['data'])) {
                return $data['data']['data'];
            }

            return null;

        } catch (RequestException $e) {
            Log::error('Failed to retrieve secret from Vault', [
                'error' => $e->getMessage(),
                'path' => $path
            ]);
            
            return null;
        }
    }

    /**
     * Check if Vault is accessible and healthy
     */
    public function checkHealth(): array
    {
        try {
            $response = $this->client->get("{$this->baseUrl}/v1/sys/health");
            $data = json_decode($response->getBody(), true);
            
            return [
                'status' => 'ok',
                'message' => 'Vault is healthy',
                'initialized' => $data['initialized'] ?? false,
                'sealed' => $data['sealed'] ?? true,
                'standby' => $data['standby'] ?? false,
                'version' => $data['version'] ?? 'unknown'
            ];
        } catch (RequestException $e) {
            Log::error('Vault health check failed', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'status' => 'error',
                'message' => 'Vault is not available',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Check if Vault is available
     */
    public function isAvailable(): bool
    {
        $health = $this->checkHealth();
        return $health['status'] === 'ok' && !($health['sealed'] ?? true);
    }

    /**
     * Rotate encryption keys
     */
    public function rotateCertificateKey(string $certificateId, string $newPrivateKey): bool
    {
        try {
            // Store the new key
            $stored = $this->storePrivateKey($certificateId, $newPrivateKey);
            
            if ($stored) {
                Log::info('Certificate key rotated in Vault', ['certificate_id' => $certificateId]);
            }
            
            return $stored;

        } catch (\Exception $e) {
            Log::error('Failed to rotate certificate key', [
                'error' => $e->getMessage(),
                'certificate_id' => $certificateId
            ]);
            
            return false;
        }
    }

    /**
     * List all stored certificate keys
     */
    public function listCertificateKeys(): array
    {
        try {
            $response = $this->client->request('LIST', "{$this->baseUrl}/v1/{$this->mountPath}/metadata/certificates");
            $data = json_decode($response->getBody(), true);
            
            return $data['data']['keys'] ?? [];

        } catch (RequestException $e) {
            Log::error('Failed to list certificate keys from Vault', [
                'error' => $e->getMessage()
            ]);
            
            return [];
        }
    }

    /**
     * Generate encryption key for certificate data
     */
    public function generateDataKey(string $context = 'certificates'): array
    {
        try {
            $response = $this->client->post("{$this->baseUrl}/v1/transit/datakey/plaintext/{$context}");
            $data = json_decode($response->getBody(), true);
            
            return [
                'plaintext' => $data['data']['plaintext'] ?? null,
                'ciphertext' => $data['data']['ciphertext'] ?? null
            ];

        } catch (RequestException $e) {
            Log::error('Failed to generate data key from Vault', [
                'error' => $e->getMessage()
            ]);
            
            throw new \Exception('Failed to generate encryption key: ' . $e->getMessage());
        }
    }

    /**
     * Encrypt data using Vault's transit engine
     */
    public function encrypt(string $plaintext, string $keyName = 'certificates'): ?string
    {
        try {
            $response = $this->client->post("{$this->baseUrl}/v1/transit/encrypt/{$keyName}", [
                'json' => [
                    'plaintext' => base64_encode($plaintext)
                ]
            ]);
            
            $data = json_decode($response->getBody(), true);
            return $data['data']['ciphertext'] ?? null;

        } catch (RequestException $e) {
            Log::error('Failed to encrypt data in Vault', [
                'error' => $e->getMessage()
            ]);
            
            return null;
        }
    }

    /**
     * Decrypt data using Vault's transit engine
     */
    public function decrypt(string $ciphertext, string $keyName = 'certificates'): ?string
    {
        try {
            $response = $this->client->post("{$this->baseUrl}/v1/transit/decrypt/{$keyName}", [
                'json' => [
                    'ciphertext' => $ciphertext
                ]
            ]);
            
            $data = json_decode($response->getBody(), true);
            $plaintext = $data['data']['plaintext'] ?? null;
            
            return $plaintext ? base64_decode($plaintext) : null;

        } catch (RequestException $e) {
            Log::error('Failed to decrypt data in Vault', [
                'error' => $e->getMessage()
            ]);
            
            return null;
        }
    }
}

