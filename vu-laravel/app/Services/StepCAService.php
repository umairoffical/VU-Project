<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

class StepCAService
{
    private $client;
    private $baseUrl;
    private $timeout;

    public function __construct()
    {
        $this->baseUrl = config('services.step_ca.url', 'https://step-ca:8443');
        $this->timeout = config('services.step_ca.timeout', 30);
        
        $this->client = new Client([
            'verify' => false, // For self-signed certificates
            'timeout' => $this->timeout,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ]
        ]);
    }

    public function checkHealth(): array
    {
        try {
            $response = $this->client->get("{$this->baseUrl}/health");
            $data = json_decode($response->getBody(), true);
            
            return [
                'status' => 'ok',
                'message' => 'Step-CA is running',
                'data' => $data
            ];
        } catch (RequestException $e) {
            Log::error('Step-CA health check failed', [
                'error' => $e->getMessage(),
                'url' => "{$this->baseUrl}/health"
            ]);
            
            return [
                'status' => 'error',
                'message' => 'Step-CA is not available',
                'error' => $e->getMessage()
            ];
        }
    }

    public function generateCertificate(array $data): array
    {
        try {
            $response = $this->client->post("{$this->baseUrl}/certificates", [
                'json' => [
                    'common_name' => $data['common_name'],
                    'subject_alt_names' => $data['subject_alt_names'] ?? [],
                    'validity_days' => $data['validity_days'] ?? 365,
                    'key_type' => $data['key_type'] ?? 'RSA',
                    'key_size' => $data['key_size'] ?? 2048,
                    'key_usage' => $data['key_usage'] ?? ['Digital Signature', 'Key Encipherment'],
                    'extended_key_usage' => $data['extended_key_usage'] ?? ['TLS Web Server Authentication']
                ]
            ]);

            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            Log::error('Step-CA certificate generation failed', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            
            throw new \Exception('Failed to generate certificate: ' . $e->getMessage());
        }
    }

    public function revokeCertificate(string $certificateId, string $reason = 'unspecified'): array
    {
        try {
            $response = $this->client->post("{$this->baseUrl}/certificates/revoke", [
                'json' => [
                    'certificate_id' => $certificateId,
                    'reason' => $reason
                ]
            ]);

            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            Log::error('Step-CA certificate revocation failed', [
                'error' => $e->getMessage(),
                'certificate_id' => $certificateId
            ]);
            
            throw new \Exception('Failed to revoke certificate: ' . $e->getMessage());
        }
    }

    public function getCertificate(string $certificateId): array
    {
        try {
            $response = $this->client->get("{$this->baseUrl}/certificates/{$certificateId}");
            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            Log::error('Step-CA certificate retrieval failed', [
                'error' => $e->getMessage(),
                'certificate_id' => $certificateId
            ]);
            
            throw new \Exception('Failed to retrieve certificate: ' . $e->getMessage());
        }
    }

    public function listCertificates(array $filters = []): array
    {
        try {
            $queryParams = http_build_query($filters);
            $url = "{$this->baseUrl}/certificates" . ($queryParams ? "?{$queryParams}" : '');
            
            $response = $this->client->get($url);
            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            Log::error('Step-CA certificate listing failed', [
                'error' => $e->getMessage(),
                'filters' => $filters
            ]);
            
            throw new \Exception('Failed to list certificates: ' . $e->getMessage());
        }
    }

    public function renewCertificate(string $certificateId, array $data): array
    {
        try {
            $response = $this->client->post("{$this->baseUrl}/certificates/{$certificateId}/renew", [
                'json' => $data
            ]);

            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            Log::error('Step-CA certificate renewal failed', [
                'error' => $e->getMessage(),
                'certificate_id' => $certificateId,
                'data' => $data
            ]);
            
            throw new \Exception('Failed to renew certificate: ' . $e->getMessage());
        }
    }

    public function generateCSR(array $data): array
    {
        try {
            $response = $this->client->post("{$this->baseUrl}/csr", [
                'json' => $data
            ]);

            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            Log::error('Step-CA CSR generation failed', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            
            throw new \Exception('Failed to generate CSR: ' . $e->getMessage());
        }
    }

    public function validateCertificate(string $certificatePem): array
    {
        try {
            $response = $this->client->post("{$this->baseUrl}/certificates/validate", [
                'json' => [
                    'certificate' => $certificatePem
                ]
            ]);

            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            Log::error('Step-CA certificate validation failed', [
                'error' => $e->getMessage()
            ]);
            
            throw new \Exception('Failed to validate certificate: ' . $e->getMessage());
        }
    }

    public function getCAChain(): array
    {
        try {
            $response = $this->client->get("{$this->baseUrl}/ca/chain");
            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            Log::error('Step-CA CA chain retrieval failed', [
                'error' => $e->getMessage()
            ]);
            
            throw new \Exception('Failed to retrieve CA chain: ' . $e->getMessage());
        }
    }

    public function getRootCA(): array
    {
        try {
            $response = $this->client->get("{$this->baseUrl}/ca/root");
            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            Log::error('Step-CA root CA retrieval failed', [
                'error' => $e->getMessage()
            ]);
            
            throw new \Exception('Failed to retrieve root CA: ' . $e->getMessage());
        }
    }

    public function isAvailable(): bool
    {
        $health = $this->checkHealth();
        return $health['status'] === 'ok';
    }
}
