<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Certificate;
use App\Models\User;
use Carbon\Carbon;

class DummyCertificatesSeeder extends Seeder
{
    public function run()
    {
        // Get the admin user
        $admin = User::where('username', 'admin')->first();
        
        if (!$admin) {
            $this->command->error('Admin user not found. Please run DefaultAdminSeeder first.');
            return;
        }

        $certificates = [
            [
                'certificate_id' => 'CERT-001',
                'common_name' => 'example.com',
                'subject_alt_names' => json_encode(['www.example.com', 'mail.example.com']),
                'status' => 'issued',
                'type' => 'ca_signed',
                'serial_number' => '1234567890ABCDEF',
                'fingerprint' => 'SHA256:ABCD1234567890ABCD1234567890ABCD12345678',
                'issuer' => 'VuProject CA',
                'issued_at' => Carbon::now()->subDays(30),
                'expires_at' => Carbon::now()->addYear(),
                'validity_days' => 365,
                'signature_algorithm' => 'SHA256withRSA',
                'key_size' => 2048,
                'key_type' => 'RSA',
                'user_id' => $admin->id,
                'approved_by' => $admin->id,
                'notes' => 'Production SSL certificate for main website',
                'metadata' => json_encode([
                    'organization' => 'Example Corp',
                    'organizational_unit' => 'IT Department',
                    'country' => 'US',
                    'state' => 'California',
                    'city' => 'San Francisco',
                    'email' => 'admin@example.com'
                ]),
                'created_at' => Carbon::now()->subDays(30),
                'updated_at' => Carbon::now()->subDays(30),
            ],
            [
                'certificate_id' => 'CERT-002',
                'common_name' => 'api.example.com',
                'subject_alt_names' => json_encode(['api.example.com', 'v1.api.example.com']),
                'status' => 'issued',
                'type' => 'ca_signed',
                'serial_number' => 'FEDCBA0987654321',
                'fingerprint' => 'SHA256:FEDCBA0987654321FEDCBA0987654321FEDCBA09',
                'issuer' => 'VuProject CA',
                'issued_at' => Carbon::now()->subDays(15),
                'expires_at' => Carbon::now()->addMonths(6),
                'validity_days' => 180,
                'signature_algorithm' => 'SHA256withRSA',
                'key_size' => 4096,
                'key_type' => 'RSA',
                'user_id' => $admin->id,
                'approved_by' => $admin->id,
                'notes' => 'API endpoint SSL certificate',
                'metadata' => json_encode([
                    'organization' => 'Example Corp',
                    'organizational_unit' => 'API Team',
                    'country' => 'US',
                    'state' => 'California',
                    'city' => 'San Francisco',
                    'email' => 'api@example.com'
                ]),
                'created_at' => Carbon::now()->subDays(15),
                'updated_at' => Carbon::now()->subDays(15),
            ],
            [
                'certificate_id' => 'CERT-003',
                'common_name' => 'mail.example.com',
                'subject_alt_names' => json_encode(['mail.example.com', 'smtp.example.com', 'imap.example.com']),
                'status' => 'issued',
                'type' => 'ca_signed',
                'serial_number' => '1122334455667788',
                'fingerprint' => 'SHA256:1122334455667788112233445566778811223344',
                'issuer' => 'VuProject CA',
                'issued_at' => Carbon::now()->subDays(7),
                'expires_at' => Carbon::now()->addMonths(3),
                'validity_days' => 90,
                'signature_algorithm' => 'SHA256withRSA',
                'key_size' => 2048,
                'key_type' => 'RSA',
                'user_id' => $admin->id,
                'approved_by' => $admin->id,
                'notes' => 'Email server SSL certificate',
                'metadata' => json_encode([
                    'organization' => 'Example Corp',
                    'organizational_unit' => 'Email Services',
                    'country' => 'US',
                    'state' => 'California',
                    'city' => 'San Francisco',
                    'email' => 'postmaster@example.com'
                ]),
                'created_at' => Carbon::now()->subDays(7),
                'updated_at' => Carbon::now()->subDays(7),
            ],
            [
                'certificate_id' => 'CERT-004',
                'common_name' => 'internal.company.com',
                'subject_alt_names' => json_encode(['internal.company.com', '*.internal.company.com']),
                'status' => 'expired',
                'type' => 'ca_signed',
                'serial_number' => 'AABBCCDDEEFF1122',
                'fingerprint' => 'SHA256:AABBCCDDEEFF1122AABBCCDDEEFF1122AABBCCDD',
                'issuer' => 'VuProject CA',
                'issued_at' => Carbon::now()->subYear(),
                'expires_at' => Carbon::now()->subDays(5),
                'validity_days' => 365,
                'signature_algorithm' => 'SHA256withRSA',
                'key_size' => 2048,
                'key_type' => 'RSA',
                'user_id' => $admin->id,
                'approved_by' => $admin->id,
                'notes' => 'Internal company certificate (expired)',
                'metadata' => json_encode([
                    'organization' => 'Company Inc',
                    'organizational_unit' => 'Internal IT',
                    'country' => 'US',
                    'state' => 'New York',
                    'city' => 'New York',
                    'email' => 'it@company.com'
                ]),
                'created_at' => Carbon::now()->subYear(),
                'updated_at' => Carbon::now()->subDays(5),
            ],
            [
                'certificate_id' => 'CERT-005',
                'common_name' => 'test.example.org',
                'subject_alt_names' => json_encode(['test.example.org']),
                'status' => 'revoked',
                'type' => 'ca_signed',
                'serial_number' => '9988776655443322',
                'fingerprint' => 'SHA256:9988776655443322998877665544332299887766',
                'issuer' => 'VuProject CA',
                'issued_at' => Carbon::now()->subMonths(3),
                'expires_at' => Carbon::now()->addMonths(9),
                'revoked_at' => Carbon::now()->subDays(1),
                'revocation_reason' => 'Security compromise',
                'validity_days' => 365,
                'signature_algorithm' => 'SHA256withRSA',
                'key_size' => 2048,
                'key_type' => 'RSA',
                'user_id' => $admin->id,
                'approved_by' => $admin->id,
                'notes' => 'Test certificate (revoked due to security issue)',
                'metadata' => json_encode([
                    'organization' => 'Test Organization',
                    'organizational_unit' => 'Development',
                    'country' => 'CA',
                    'state' => 'Ontario',
                    'city' => 'Toronto',
                    'email' => 'dev@example.org'
                ]),
                'created_at' => Carbon::now()->subMonths(3),
                'updated_at' => Carbon::now()->subDays(1),
            ],
            [
                'certificate_id' => 'CERT-006',
                'common_name' => 'staging.app.com',
                'subject_alt_names' => json_encode(['staging.app.com', 'stg.app.com']),
                'status' => 'pending',
                'type' => 'ca_signed',
                'serial_number' => 'PENDING123456789',
                'fingerprint' => null,
                'issuer' => 'VuProject CA',
                'issued_at' => null,
                'expires_at' => null,
                'validity_days' => 365,
                'signature_algorithm' => 'SHA256withRSA',
                'key_size' => 4096,
                'key_type' => 'RSA',
                'user_id' => $admin->id,
                'approved_by' => null,
                'notes' => 'Staging environment certificate (pending approval)',
                'metadata' => json_encode([
                    'organization' => 'App Corp',
                    'organizational_unit' => 'Staging Environment',
                    'country' => 'US',
                    'state' => 'Texas',
                    'city' => 'Austin',
                    'email' => 'staging@app.com'
                ]),
                'created_at' => Carbon::now()->subHours(2),
                'updated_at' => Carbon::now()->subHours(2),
            ],
            [
                'certificate_id' => 'CERT-007',
                'common_name' => 'wildcard.example.net',
                'subject_alt_names' => json_encode(['*.example.net', 'example.net']),
                'status' => 'issued',
                'type' => 'ca_signed',
                'serial_number' => 'WILDCARD123456789',
                'fingerprint' => 'SHA256:WILDCARD123456789WILDCARD123456789WILDCARD',
                'issuer' => 'VuProject CA',
                'issued_at' => Carbon::now()->subDays(3),
                'expires_at' => Carbon::now()->addYear(),
                'validity_days' => 365,
                'signature_algorithm' => 'SHA256withRSA',
                'key_size' => 4096,
                'key_type' => 'RSA',
                'user_id' => $admin->id,
                'approved_by' => $admin->id,
                'notes' => 'Wildcard certificate for subdomains',
                'metadata' => json_encode([
                    'organization' => 'Example Network',
                    'organizational_unit' => 'Wildcard Services',
                    'country' => 'US',
                    'state' => 'Washington',
                    'city' => 'Seattle',
                    'email' => 'wildcard@example.net'
                ]),
                'created_at' => Carbon::now()->subDays(3),
                'updated_at' => Carbon::now()->subDays(3),
            ],
            [
                'certificate_id' => 'CERT-008',
                'common_name' => 'code-signing.app',
                'subject_alt_names' => json_encode([]),
                'status' => 'issued',
                'type' => 'ca_signed',
                'serial_number' => 'CODESIGN123456',
                'fingerprint' => 'SHA256:CODESIGN123456CODESIGN123456CODESIGN123456',
                'issuer' => 'VuProject CA',
                'issued_at' => Carbon::now()->subWeeks(2),
                'expires_at' => Carbon::now()->addMonths(6),
                'validity_days' => 180,
                'signature_algorithm' => 'SHA256withRSA',
                'key_size' => 2048,
                'key_type' => 'RSA',
                'user_id' => $admin->id,
                'approved_by' => $admin->id,
                'notes' => 'Code signing certificate for application',
                'key_usage' => json_encode(['digitalSignature', 'keyEncipherment']),
                'extended_key_usage' => json_encode(['codeSigning']),
                'metadata' => json_encode([
                    'organization' => 'App Development Co',
                    'organizational_unit' => 'Code Signing',
                    'country' => 'US',
                    'state' => 'Florida',
                    'city' => 'Miami',
                    'email' => 'codesign@app.com'
                ]),
                'created_at' => Carbon::now()->subWeeks(2),
                'updated_at' => Carbon::now()->subWeeks(2),
            ]
        ];

        foreach ($certificates as $certData) {
            Certificate::create($certData);
        }

        $this->command->info('✅ Created ' . count($certificates) . ' dummy certificates!');
        $this->command->info('   📊 Certificate types: CA Signed');
        $this->command->info('   📈 Statuses: Issued, Expired, Revoked, Pending');
        $this->command->info('   🌍 Various countries: US, Canada');
        $this->command->info('   🔐 Key sizes: 2048-bit, 4096-bit');
        $this->command->info('   🎯 Key types: RSA');
        $this->command->info('   📝 Includes: Wildcard, Code Signing, Email certificates');
    }
}