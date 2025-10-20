<?php

namespace App\Services;

use App\Models\Certificate;
use App\Models\CertificateRequest;
use App\Models\Notification;
use App\Services\StepCAService;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

class CertificateService
{
    private $stepCAService;
    private $notificationService;

    public function __construct(StepCAService $stepCAService, NotificationService $notificationService)
    {
        $this->stepCAService = $stepCAService;
        $this->notificationService = $notificationService;
    }

    public function generateCertificate(array $data): Certificate
    {
        try {
            // Generate CSR first
            $csrData = $this->generateCSR($data);
            
            // Generate certificate using Step-CA
            $certData = $this->stepCAService->generateCertificate([
                'common_name' => $data['common_name'],
                'subject_alt_names' => $data['subject_alt_names'] ?? [],
                'validity_days' => $data['validity_days'] ?? 365,
                'key_type' => $data['key_type'] ?? 'RSA',
                'key_size' => $data['key_size'] ?? 2048,
                'key_usage' => $data['key_usage'] ?? ['Digital Signature', 'Key Encipherment'],
                'extended_key_usage' => $data['extended_key_usage'] ?? ['TLS Web Server Authentication']
            ]);

            // Create certificate record
            $certificate = Certificate::create([
                'certificate_id' => $this->generateCertificateId(),
                'common_name' => $data['common_name'],
                'subject_alt_names' => $data['subject_alt_names'] ?? [],
                'csr' => $csrData['csr'],
                'certificate' => $certData['certificate'],
                'private_key' => $csrData['private_key'],
                'status' => 'issued',
                'type' => 'ca_signed',
                'serial_number' => $certData['serial_number'],
                'fingerprint' => $certData['fingerprint'],
                'issuer' => $certData['issuer'],
                'issued_at' => now(),
                'expires_at' => Carbon::now()->addDays($data['validity_days'] ?? 365),
                'validity_days' => $data['validity_days'] ?? 365,
                'key_usage' => $data['key_usage'] ?? ['Digital Signature', 'Key Encipherment'],
                'extended_key_usage' => $data['extended_key_usage'] ?? ['TLS Web Server Authentication'],
                'signature_algorithm' => $certData['signature_algorithm'] ?? 'SHA256withRSA',
                'key_size' => $data['key_size'] ?? 2048,
                'key_type' => $data['key_type'] ?? 'RSA',
                'user_id' => $data['user_id'],
                'approved_by' => $data['approved_by'],
                'notes' => $data['notes'] ?? null,
                'metadata' => $certData
            ]);

            // Schedule expiry notification
            $this->scheduleExpiryNotification($certificate);

            return $certificate;

        } catch (\Exception $e) {
            Log::error('Certificate generation failed', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            throw $e;
        }
    }

    public function revokeCertificate(Certificate $certificate, string $reason, $user): bool
    {
        try {
            // Revoke in Step-CA
            $this->stepCAService->revokeCertificate($certificate->certificate_id, $reason);

            // Update certificate status
            $certificate->update([
                'status' => 'revoked',
                'revoked_at' => now(),
                'revocation_reason' => $reason
            ]);

            // Send notification
            $this->notificationService->sendCertificateRevokedNotification($certificate, $user);

            return true;

        } catch (\Exception $e) {
            Log::error('Certificate revocation failed', [
                'error' => $e->getMessage(),
                'certificate_id' => $certificate->certificate_id
            ]);
            throw $e;
        }
    }

    public function renewCertificate(Certificate $oldCertificate, array $data): Certificate
    {
        try {
            // Mark old certificate as renewed
            $oldCertificate->update(['status' => 'renewed']);

            // Generate new certificate
            $newCertificateData = array_merge($data, [
                'common_name' => $oldCertificate->common_name,
                'subject_alt_names' => $oldCertificate->subject_alt_names,
                'key_usage' => $oldCertificate->key_usage,
                'extended_key_usage' => $oldCertificate->extended_key_usage,
                'key_type' => $oldCertificate->key_type,
                'key_size' => $oldCertificate->key_size,
                'notes' => "Renewed from certificate {$oldCertificate->certificate_id}"
            ]);

            $newCertificate = $this->generateCertificate($newCertificateData);

            // Send notification
            $this->notificationService->sendCertificateRenewedNotification($oldCertificate, $newCertificate);

            return $newCertificate;

        } catch (\Exception $e) {
            Log::error('Certificate renewal failed', [
                'error' => $e->getMessage(),
                'old_certificate_id' => $oldCertificate->certificate_id
            ]);
            throw $e;
        }
    }

    public function exportCertificate(Certificate $certificate, string $format = 'pem'): string
    {
        switch ($format) {
            case 'pem':
                return $certificate->certificate;
            case 'der':
                return $this->convertPemToDer($certificate->certificate);
            case 'p12':
            case 'pfx':
                return $this->createP12($certificate);
            default:
                throw new \InvalidArgumentException("Unsupported format: {$format}");
        }
    }

    public function generateCSR(array $data): array
    {
        $config = [
            'digest_alg' => 'sha256',
            'x509_extensions' => 'v3_req',
            'req_extensions' => 'v3_req',
            'private_key_bits' => $data['key_size'] ?? 2048,
            'private_key_type' => $this->getOpenSSLKeyType($data['key_type'] ?? 'RSA'),
        ];

        // Create private key
        $privateKey = openssl_pkey_new($config);
        if (!$privateKey) {
            throw new \Exception('Failed to generate private key: ' . openssl_error_string());
        }

        // Extract private key
        openssl_pkey_export($privateKey, $privateKeyPem);

        // Create CSR
        $dn = [
            'countryName' => 'US',
            'stateOrProvinceName' => 'State',
            'localityName' => 'City',
            'organizationName' => 'Organization',
            'organizationalUnitName' => 'IT Department',
            'commonName' => $data['common_name'],
            'emailAddress' => 'admin@example.com'
        ];

        $csr = openssl_csr_new($dn, $privateKey, $config);
        if (!$csr) {
            throw new \Exception('Failed to generate CSR: ' . openssl_error_string());
        }

        openssl_csr_export($csr, $csrPem);

        return [
            'csr' => $csrPem,
            'private_key' => $privateKeyPem
        ];
    }

    public function validateCertificate(string $certificatePem): array
    {
        $cert = openssl_x509_parse($certificatePem);
        if (!$cert) {
            throw new \Exception('Invalid certificate format');
        }

        $now = time();
        $validFrom = $cert['validFrom_time_t'];
        $validTo = $cert['validTo_time_t'];

        return [
            'valid' => $now >= $validFrom && $now <= $validTo,
            'expired' => $now > $validTo,
            'not_yet_valid' => $now < $validFrom,
            'valid_from' => date('Y-m-d H:i:s', $validFrom),
            'valid_to' => date('Y-m-d H:i:s', $validTo),
            'days_until_expiry' => max(0, floor(($validTo - $now) / 86400)),
            'subject' => $cert['subject'],
            'issuer' => $cert['issuer'],
            'serial_number' => $cert['serialNumberHex'],
            'signature_algorithm' => $cert['signatureTypeSN'],
            'key_type' => $this->getKeyTypeFromCertificate($certificatePem),
            'key_size' => $this->getKeySizeFromCertificate($certificatePem)
        ];
    }

    public function checkExpiringCertificates(int $days = 30): array
    {
        $expiringCertificates = Certificate::expiringSoon($days)->get();
        
        foreach ($expiringCertificates as $certificate) {
            $this->notificationService->sendCertificateExpiryNotification($certificate);
        }

        return $expiringCertificates->toArray();
    }

    public function cleanupExpiredCertificates(): int
    {
        $expiredCertificates = Certificate::expired()->where('status', 'issued')->get();
        $count = 0;

        foreach ($expiredCertificates as $certificate) {
            $certificate->update(['status' => 'expired']);
            $this->notificationService->sendCertificateExpiredNotification($certificate);
            $count++;
        }

        return $count;
    }

    private function generateCertificateId(): string
    {
        return 'cert_' . time() . '_' . Str::random(8);
    }

    private function getOpenSSLKeyType(string $keyType): int
    {
        return match($keyType) {
            'RSA' => OPENSSL_KEYTYPE_RSA,
            'ECDSA' => OPENSSL_KEYTYPE_EC,
            'ED25519' => OPENSSL_KEYTYPE_EC,
            default => OPENSSL_KEYTYPE_RSA
        };
    }

    private function convertPemToDer(string $pem): string
    {
        $pem = str_replace(['-----BEGIN CERTIFICATE-----', '-----END CERTIFICATE-----', "\n", "\r"], '', $pem);
        return base64_decode($pem);
    }

    private function createP12(Certificate $certificate): string
    {
        // This is a simplified implementation
        // In production, you'd want to use a proper PKCS#12 library
        $cert = $certificate->certificate;
        $key = $certificate->private_key;
        
        // Create temporary files
        $certFile = tmpfile();
        $keyFile = tmpfile();
        
        fwrite($certFile, $cert);
        fwrite($keyFile, $key);
        
        $certPath = stream_get_meta_data($certFile)['uri'];
        $keyPath = stream_get_meta_data($keyFile)['uri'];
        
        // Generate P12
        $p12Path = tempnam(sys_get_temp_dir(), 'cert') . '.p12';
        $command = "openssl pkcs12 -export -in {$certPath} -inkey {$keyPath} -out {$p12Path} -passout pass:";
        
        exec($command, $output, $returnCode);
        
        if ($returnCode !== 0) {
            throw new \Exception('Failed to create P12 file');
        }
        
        $p12Content = file_get_contents($p12Path);
        
        // Cleanup
        unlink($p12Path);
        fclose($certFile);
        fclose($keyFile);
        
        return $p12Content;
    }

    private function getKeyTypeFromCertificate(string $certificatePem): string
    {
        $cert = openssl_x509_read($certificatePem);
        $key = openssl_pkey_get_public($cert);
        $details = openssl_pkey_get_details($key);
        
        return match($details['type']) {
            OPENSSL_KEYTYPE_RSA => 'RSA',
            OPENSSL_KEYTYPE_EC => 'ECDSA',
            default => 'Unknown'
        };
    }

    private function getKeySizeFromCertificate(string $certificatePem): int
    {
        $cert = openssl_x509_read($certificatePem);
        $key = openssl_pkey_get_public($cert);
        $details = openssl_pkey_get_details($key);
        
        return $details['bits'] ?? 0;
    }

    private function scheduleExpiryNotification(Certificate $certificate): void
    {
        // Schedule notifications at 90, 30, 7, and 1 days before expiry
        $notificationDays = [90, 30, 7, 1];
        
        foreach ($notificationDays as $days) {
            $notificationDate = $certificate->expires_at->subDays($days);
            
            if ($notificationDate->isFuture()) {
                Notification::create([
                    'type' => 'certificate_expiry',
                    'title' => "Certificate Expiring in {$days} Days",
                    'message' => "Certificate {$certificate->common_name} will expire in {$days} days",
                    'priority' => $days <= 7 ? 'high' : 'medium',
                    'channel' => 'email',
                    'scheduled_at' => $notificationDate,
                    'user_id' => $certificate->user_id,
                    'certificate_id' => $certificate->id,
                    'data' => [
                        'days_until_expiry' => $days,
                        'expiry_date' => $certificate->expires_at->toISOString()
                    ]
                ]);
            }
        }
    }
}
