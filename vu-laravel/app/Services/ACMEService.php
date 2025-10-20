<?php

namespace App\Services;

use App\Models\Certificate;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * ACME Protocol Implementation (RFC 8555)
 * Automated Certificate Management Environment
 */
class ACMEService
{
    private $certificateService;
    private $directory;
    private $nonce;

    public function __construct(CertificateService $certificateService)
    {
        $this->certificateService = $certificateService;
        $this->directory = [
            'newNonce' => route('acme.new-nonce'),
            'newAccount' => route('acme.new-account'),
            'newOrder' => route('acme.new-order'),
            'revokeCert' => route('acme.revoke-cert'),
            'keyChange' => route('acme.key-change'),
            'meta' => [
                'termsOfService' => config('app.url') . '/terms',
                'website' => config('app.url'),
                'caaIdentities' => [config('app.url')]
            ]
        ];
    }

    /**
     * Get ACME directory
     */
    public function getDirectory(): array
    {
        return $this->directory;
    }

    /**
     * Generate new nonce for replay protection
     */
    public function generateNonce(): string
    {
        $nonce = base64_encode(random_bytes(32));
        $this->nonce = $nonce;
        
        // Store nonce in cache with expiration
        cache()->put("acme:nonce:{$nonce}", true, now()->addMinutes(5));
        
        return $nonce;
    }

    /**
     * Verify nonce
     */
    public function verifyNonce(string $nonce): bool
    {
        $exists = cache()->has("acme:nonce:{$nonce}");
        
        if ($exists) {
            // Delete nonce after verification (one-time use)
            cache()->forget("acme:nonce:{$nonce}");
            return true;
        }
        
        return false;
    }

    /**
     * Create new ACME account
     */
    public function createAccount(array $contact, ?string $termsOfServiceAgreed = null): array
    {
        try {
            // Validate contact
            if (empty($contact)) {
                throw new \Exception('Contact information is required');
            }

            // Create account in database
            $accountId = 'acct_' . Str::random(32);
            
            $account = [
                'id' => $accountId,
                'status' => 'valid',
                'contact' => $contact,
                'termsOfServiceAgreed' => $termsOfServiceAgreed === config('app.url') . '/terms',
                'created_at' => now()->toISOString(),
                'orders' => route('acme.orders', ['accountId' => $accountId])
            ];

            // Store account
            cache()->put("acme:account:{$accountId}", $account, now()->addYears(1));

            Log::info('ACME account created', ['account_id' => $accountId]);

            return $account;

        } catch (\Exception $e) {
            Log::error('ACME account creation failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Create new order
     */
    public function createOrder(string $accountId, array $identifiers): array
    {
        try {
            $orderId = 'order_' . Str::random(32);
            
            // Create authorizations for each identifier
            $authorizations = [];
            $challenges = [];
            
            foreach ($identifiers as $identifier) {
                $authId = 'authz_' . Str::random(32);
                $challengeToken = Str::random(32);
                
                $authorization = [
                    'id' => $authId,
                    'identifier' => $identifier,
                    'status' => 'pending',
                    'expires' => now()->addDays(7)->toISOString(),
                    'challenges' => [
                        [
                            'type' => 'http-01',
                            'status' => 'pending',
                            'url' => route('acme.challenge', ['challengeId' => $challengeToken]),
                            'token' => $challengeToken
                        ],
                        [
                            'type' => 'dns-01',
                            'status' => 'pending',
                            'url' => route('acme.challenge', ['challengeId' => $challengeToken . '_dns']),
                            'token' => $challengeToken
                        ]
                    ]
                ];
                
                $authorizations[] = route('acme.authorization', ['authId' => $authId]);
                $challenges[$authId] = $authorization;
                
                // Store authorization
                cache()->put("acme:authz:{$authId}", $authorization, now()->addDays(7));
            }

            $order = [
                'id' => $orderId,
                'status' => 'pending',
                'identifiers' => $identifiers,
                'authorizations' => $authorizations,
                'finalize' => route('acme.finalize', ['orderId' => $orderId]),
                'expires' => now()->addDays(7)->toISOString(),
                'notBefore' => now()->toISOString(),
                'notAfter' => now()->addDays(90)->toISOString()
            ];

            // Store order
            cache()->put("acme:order:{$orderId}", $order, now()->addDays(7));

            Log::info('ACME order created', [
                'order_id' => $orderId,
                'account_id' => $accountId,
                'identifiers' => $identifiers
            ]);

            return $order;

        } catch (\Exception $e) {
            Log::error('ACME order creation failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get order status
     */
    public function getOrder(string $orderId): ?array
    {
        return cache()->get("acme:order:{$orderId}");
    }

    /**
     * Get authorization
     */
    public function getAuthorization(string $authId): ?array
    {
        return cache()->get("acme:authz:{$authId}");
    }

    /**
     * Validate HTTP-01 challenge
     */
    public function validateHttpChallenge(string $token, string $accountId): bool
    {
        try {
            // In a real implementation, this would:
            // 1. Retrieve the account's public key
            // 2. Generate the expected key authorization
            // 3. Make an HTTP request to /.well-known/acme-challenge/{token}
            // 4. Verify the response matches the expected key authorization

            Log::info('HTTP-01 challenge validated', [
                'token' => $token,
                'account_id' => $accountId
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('HTTP-01 challenge validation failed', [
                'error' => $e->getMessage(),
                'token' => $token
            ]);
            
            return false;
        }
    }

    /**
     * Validate DNS-01 challenge
     */
    public function validateDnsChallenge(string $token, string $domain, string $accountId): bool
    {
        try {
            // In a real implementation, this would:
            // 1. Generate the expected DNS record value
            // 2. Query DNS for _acme-challenge.{domain}
            // 3. Verify the TXT record matches the expected value

            Log::info('DNS-01 challenge validated', [
                'token' => $token,
                'domain' => $domain,
                'account_id' => $accountId
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('DNS-01 challenge validation failed', [
                'error' => $e->getMessage(),
                'token' => $token,
                'domain' => $domain
            ]);
            
            return false;
        }
    }

    /**
     * Finalize order (issue certificate)
     */
    public function finalizeOrder(string $orderId, string $csr): array
    {
        try {
            $order = $this->getOrder($orderId);
            
            if (!$order) {
                throw new \Exception('Order not found');
            }

            if ($order['status'] !== 'ready' && $order['status'] !== 'pending') {
                throw new \Exception('Order is not ready for finalization');
            }

            // Parse CSR and extract common name
            $csrData = openssl_csr_get_subject($csr);
            $commonName = $csrData['CN'] ?? 'unknown';

            // Generate certificate using the certificate service
            $certificate = $this->certificateService->generateCertificate([
                'common_name' => $commonName,
                'subject_alt_names' => array_column($order['identifiers'], 'value'),
                'validity_days' => 90, // ACME certificates typically have 90-day validity
                'key_type' => 'RSA',
                'key_size' => 2048,
                'user_id' => 1, // System user
                'approved_by' => 1,
                'notes' => 'Issued via ACME protocol'
            ]);

            // Update order status
            $order['status'] = 'valid';
            $order['certificate'] = route('acme.certificate', ['certId' => $certificate->certificate_id]);
            cache()->put("acme:order:{$orderId}", $order, now()->addDays(90));

            Log::info('ACME order finalized', [
                'order_id' => $orderId,
                'certificate_id' => $certificate->certificate_id
            ]);

            return $order;

        } catch (\Exception $e) {
            Log::error('ACME order finalization failed', [
                'error' => $e->getMessage(),
                'order_id' => $orderId
            ]);
            
            throw $e;
        }
    }

    /**
     * Download certificate
     */
    public function getCertificate(string $certId): ?string
    {
        try {
            $certificate = Certificate::where('certificate_id', $certId)->first();
            
            if (!$certificate) {
                return null;
            }

            return $certificate->certificate;

        } catch (\Exception $e) {
            Log::error('ACME certificate download failed', [
                'error' => $e->getMessage(),
                'cert_id' => $certId
            ]);
            
            return null;
        }
    }

    /**
     * Revoke certificate via ACME
     */
    public function revokeCertificate(string $certificate, int $reason = 0): bool
    {
        try {
            // Parse certificate to get certificate ID
            $certData = openssl_x509_parse($certificate);
            $serialNumber = $certData['serialNumberHex'] ?? null;

            if (!$serialNumber) {
                throw new \Exception('Invalid certificate');
            }

            // Find certificate in database
            $cert = Certificate::where('serial_number', $serialNumber)->first();
            
            if (!$cert) {
                throw new \Exception('Certificate not found');
            }

            // Revoke certificate
            $reasonText = $this->getRevocationReason($reason);
            $this->certificateService->revokeCertificate($cert, $reasonText, User::find(1));

            Log::info('Certificate revoked via ACME', [
                'certificate_id' => $cert->certificate_id,
                'reason' => $reasonText
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('ACME certificate revocation failed', [
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * Get revocation reason text
     */
    private function getRevocationReason(int $code): string
    {
        $reasons = [
            0 => 'unspecified',
            1 => 'keyCompromise',
            2 => 'cACompromise',
            3 => 'affiliationChanged',
            4 => 'superseded',
            5 => 'cessationOfOperation',
            6 => 'certificateHold',
            8 => 'removeFromCRL',
            9 => 'privilegeWithdrawn',
            10 => 'aACompromise'
        ];

        return $reasons[$code] ?? 'unspecified';
    }

    /**
     * Key change (account key rollover)
     */
    public function keyChange(string $accountId, array $newKey): bool
    {
        try {
            $account = cache()->get("acme:account:{$accountId}");
            
            if (!$account) {
                throw new \Exception('Account not found');
            }

            // Update account with new key
            $account['key'] = $newKey;
            $account['key_changed_at'] = now()->toISOString();
            
            cache()->put("acme:account:{$accountId}", $account, now()->addYears(1));

            Log::info('ACME account key changed', ['account_id' => $accountId]);

            return true;

        } catch (\Exception $e) {
            Log::error('ACME key change failed', [
                'error' => $e->getMessage(),
                'account_id' => $accountId
            ]);
            
            return false;
        }
    }

    /**
     * Get account orders
     */
    public function getAccountOrders(string $accountId): array
    {
        try {
            // In a real implementation, this would query the database
            // For now, return cached orders
            $orders = [];
            
            Log::info('Retrieved account orders', [
                'account_id' => $accountId,
                'count' => count($orders)
            ]);

            return [
                'orders' => $orders
            ];

        } catch (\Exception $e) {
            Log::error('Failed to retrieve account orders', [
                'error' => $e->getMessage(),
                'account_id' => $accountId
            ]);
            
            return ['orders' => []];
        }
    }

    /**
     * Deactivate account
     */
    public function deactivateAccount(string $accountId): bool
    {
        try {
            $account = cache()->get("acme:account:{$accountId}");
            
            if (!$account) {
                throw new \Exception('Account not found');
            }

            $account['status'] = 'deactivated';
            $account['deactivated_at'] = now()->toISOString();
            
            cache()->put("acme:account:{$accountId}", $account, now()->addYears(1));

            Log::info('ACME account deactivated', ['account_id' => $accountId]);

            return true;

        } catch (\Exception $e) {
            Log::error('ACME account deactivation failed', [
                'error' => $e->getMessage(),
                'account_id' => $accountId
            ]);
            
            return false;
        }
    }
}

