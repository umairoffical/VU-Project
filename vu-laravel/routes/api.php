<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Simplified VuProject API
|--------------------------------------------------------------------------
*/

// Health check endpoint
Route::get('/health', function () {
    try {
        $dbConnected = \DB::connection()->getPdo() ? 'connected' : 'disconnected';
    } catch (\Exception $e) {
        $dbConnected = 'disconnected';
    }
    
    return response()->json([
        'status' => 'ok',
        'timestamp' => now(),
        'service' => 'VuProject Laravel API',
        'database' => $dbConnected
    ]);
});

// CA Server health check (proxy to avoid CORS issues)
Route::get('/ca-health', function () {
    try {
        $client = new \GuzzleHttp\Client(['verify' => false, 'timeout' => 5]);
        $response = $client->get('https://localhost:8443/health');
        $data = json_decode($response->getBody(), true);
        
        return response()->json([
            'status' => 'online',
            'message' => '✅ ACTIVE - Generating real certificates',
            'ca_status' => 'ok',
            'ca_name' => $data['name'] ?? 'VuProject CA',
            'ca_version' => $data['version'] ?? '1.0.0',
            'port' => '8443',
            'url' => 'https://localhost:8443',
            'mode' => 'Real-time certificate generation',
            'timestamp' => $data['timestamp'] ?? now()->toISOString()
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'offline',
            'message' => 'CA Server offline - Using database fallback',
            'error' => $e->getMessage()
        ], 503);
    }
});

// Get certificates - Real CA Server if available, Database if not (single source)
Route::get('/live-certificates', function () {
    // Try Real CA Server first
    try {
        $client = new \GuzzleHttp\Client(['verify' => false, 'timeout' => 5]);
        $response = $client->get('https://localhost:8443/certificates');
        $data = json_decode($response->getBody(), true);
        
        if (isset($data['data']) && is_array($data['data'])) {
            // CA Server is online - return ONLY Real CA certificates
            return response()->json([
                'success' => true,
                'source' => 'real-ca-server',
                'data' => $data['data'],
                'total' => count($data['data']),
                'ca_server_active' => true,
                'timestamp' => $data['timestamp'] ?? time()
            ]);
        }
    } catch (\Exception $e) {
        \Log::info('Real CA Server not available, using database fallback');
    }
    
    // CA Server offline - return ONLY Database certificates
    $dbCertificates = \App\Models\Certificate::orderBy('created_at', 'desc')->get();
    $certificates = [];
    
    foreach ($dbCertificates as $cert) {
        $certificates[] = [
            'id' => $cert->certificate_id,
            'commonName' => $cert->common_name,
            'status' => $cert->status === 'issued' ? 'Valid' : 
                       ($cert->status === 'expired' ? 'Expired' :
                       ($cert->status === 'revoked' ? 'Revoked' : 'Pending')),
            'validFrom' => $cert->issued_at ? $cert->issued_at->toISOString() : $cert->created_at->toISOString(),
            'validTo' => $cert->expires_at ? $cert->expires_at->toISOString() : '',
            'issuer' => $cert->issuer ?? 'VuProject CA',
            'serialNumber' => $cert->serial_number ?? 'N/A',
            'subjectAltNames' => is_string($cert->subject_alt_names) ? json_decode($cert->subject_alt_names, true) : ($cert->subject_alt_names ?? []),
            'keyUsage' => is_string($cert->key_usage) ? json_decode($cert->key_usage, true) : ($cert->key_usage ?? []),
            'extendedKeyUsage' => is_string($cert->extended_key_usage) ? json_decode($cert->extended_key_usage, true) : ($cert->extended_key_usage ?? []),
            'source' => 'database',
            'notes' => $cert->notes
        ];
    }
    
    return response()->json([
        'success' => true,
        'source' => 'database',
        'data' => $certificates,
        'total' => count($certificates),
        'ca_server_active' => false,
        'timestamp' => time()
    ]);
});

Route::get('/test-certificates', function () {
    $certificates = \App\Models\Certificate::all();
    return response()->json([
        'success' => true,
        'count' => $certificates->count(),
        'data' => $certificates->toArray()
    ]);
});

Route::post('/certificates/generate', function (Request $request) {
    try {
        $commonName = $request->input('commonName');
        $subjectAltNames = $request->input('subjectAltNames', []);
        $validityDays = $request->input('validityDays', 365);

        $useRealCA = false;
        $caData = null;

        // Try Real CA Server first
        try {
            $caClient = new \GuzzleHttp\Client(['verify' => false, 'timeout' => 5]);
            $caResponse = $caClient->post('https://localhost:8443/certificates/generate', [
                'json' => [
                    'commonName' => $commonName,
                    'subjectAltNames' => $subjectAltNames,
                    'validityDays' => $validityDays
                ]
            ]);
            $caData = json_decode($caResponse->getBody(), true);

            if ($caData && isset($caData['certificate'])) {
                $useRealCA = true;
            }
        } catch (\Exception $caError) {
            \Log::info('Real CA not available, using database fallback', ['error' => $caError->getMessage()]);
        }

        // Always save to database so certs persist and appear in DB fallback
        $certificate = \App\Models\Certificate::create([
            'certificate_id' => $useRealCA ? ($caData['id'] ?? 'CERT-' . strtoupper(substr(md5($commonName . time()), 0, 8))) : 'CERT-' . strtoupper(substr(md5($commonName . time()), 0, 8)),
            'common_name' => $commonName,
            'subject_alt_names' => json_encode($subjectAltNames),
            'csr' => $useRealCA && isset($caData['csr']) ? (is_string($caData['csr']) ? $caData['csr'] : json_encode($caData['csr'])) : null,
            'certificate' => $useRealCA && isset($caData['certificate']) ? (is_string($caData['certificate']) ? $caData['certificate'] : json_encode($caData['certificate'])) : null,
            'private_key' => $useRealCA && isset($caData['privateKey']) ? (is_string($caData['privateKey']) ? $caData['privateKey'] : json_encode($caData['privateKey'])) : null,
            'status' => 'issued',
            'type' => $useRealCA ? 'ca_signed' : 'self_signed',
            'serial_number' => $useRealCA && isset($caData['serialNumber']) ? $caData['serialNumber'] : strtoupper(substr(md5(time() . $commonName), 0, 16)),
            'fingerprint' => $useRealCA && isset($caData['fingerprint']) ? $caData['fingerprint'] : 'SHA256:' . strtoupper(substr(md5($commonName . microtime()), 0, 40)),
            'issuer' => 'VuProject CA',
            'issued_at' => now(),
            'expires_at' => now()->addDays($validityDays),
            'validity_days' => $validityDays,
            'signature_algorithm' => 'SHA256withRSA',
            'key_size' => 2048,
            'key_type' => 'RSA',
            'user_id' => 1,
            'approved_by' => 1,
            'notes' => $useRealCA ? 'Generated via Real CA Server' : 'Generated via web interface (Dummy)',
            'metadata' => json_encode([
                'organization' => 'Auto-Generated',
                'country' => 'US',
                'real_ca' => $useRealCA,
            ]),
        ]);

        return response()->json([
            'success' => true,
            'message' => $useRealCA ? 'Certificate generated via Real CA Server and saved to database' : 'Certificate generated and saved to database (CA Server offline)',
            'certificate_id' => $certificate->certificate_id,
            'common_name' => $certificate->common_name,
            'source' => $useRealCA ? 'real-ca-server' : 'database',
            'real_ca_used' => $useRealCA,
            'data' => $useRealCA ? $caData : $certificate
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to generate certificate: ' . $e->getMessage()
        ], 500);
    }
});

// CSR Management
Route::post('/csr/generate', function (Request $request) {
    try {
        $data = $request->all();
        
        // Create CSR record in certificate_requests table
        $csrRequest = \App\Models\CertificateRequest::create([
            'request_id' => 'CSR-' . strtoupper(substr(md5($data['commonName'] . time()), 0, 8)),
            'common_name' => $data['commonName'],
            'subject_alt_names' => json_encode($data['subjectAltNames'] ?? []),
            'organization' => $data['organization'] ?? null,
            'organizational_unit' => $data['organizationalUnit'] ?? null,
            'country' => $data['country'] ?? null,
            'state' => $data['state'] ?? null,
            'city' => $data['city'] ?? null,
            'email' => $data['email'] ?? null,
            'key_size' => $data['keySize'] ?? 2048,
            'validity_days' => $data['validityDays'] ?? 365,
            'status' => 'pending',
            'user_id' => 1, // Current user
            'metadata' => json_encode($data),
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'CSR generated and submitted for approval',
            'data' => $csrRequest
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to generate CSR: ' . $e->getMessage()
        ], 500);
    }
});

Route::get('/csr/list', function () {
    $requests = \App\Models\CertificateRequest::orderBy('created_at', 'desc')->get();
    return response()->json([
        'success' => true,
        'data' => $requests
    ]);
});

Route::post('/csr/approve/{id}', function ($id) {
    try {
        $csrRequest = \App\Models\CertificateRequest::findOrFail($id);

        if ($csrRequest->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'CSR has already been processed'
            ], 400);
        }

        $useRealCA = false;
        $caData = null;

        // Try to push to Real CA Server so it appears in live view
        try {
            $sans = is_string($csrRequest->subject_alt_names) ? json_decode($csrRequest->subject_alt_names, true) : ($csrRequest->subject_alt_names ?? []);
            $caClient = new \GuzzleHttp\Client(['verify' => false, 'timeout' => 5]);
            $caResponse = $caClient->post('https://localhost:8443/certificates/generate', [
                'json' => [
                    'commonName' => $csrRequest->common_name,
                    'subjectAltNames' => $sans,
                    'validityDays' => $csrRequest->validity_days
                ]
            ]);
            $caData = json_decode($caResponse->getBody(), true);
            if ($caData && isset($caData['certificate'])) {
                $useRealCA = true;
            }
        } catch (\Exception $caError) {
            \Log::info('Real CA not available for CSR approval', ['error' => $caError->getMessage()]);
        }

        // Always save certificate to database
        $certificate = \App\Models\Certificate::create([
            'certificate_id' => $useRealCA ? ($caData['id'] ?? 'CERT-' . strtoupper(substr(md5($csrRequest->common_name . time()), 0, 8))) : 'CERT-' . strtoupper(substr(md5($csrRequest->common_name . time()), 0, 8)),
            'common_name' => $csrRequest->common_name,
            'subject_alt_names' => $csrRequest->subject_alt_names,
            'csr' => $useRealCA && isset($caData['csr']) ? (is_string($caData['csr']) ? $caData['csr'] : json_encode($caData['csr'])) : null,
            'certificate' => $useRealCA && isset($caData['certificate']) ? (is_string($caData['certificate']) ? $caData['certificate'] : json_encode($caData['certificate'])) : null,
            'private_key' => $useRealCA && isset($caData['privateKey']) ? (is_string($caData['privateKey']) ? $caData['privateKey'] : json_encode($caData['privateKey'])) : null,
            'status' => 'issued',
            'type' => 'ca_signed',
            'serial_number' => $useRealCA && isset($caData['serialNumber']) ? $caData['serialNumber'] : strtoupper(substr(md5(time() . $csrRequest->common_name), 0, 16)),
            'fingerprint' => $useRealCA && isset($caData['fingerprint']) ? $caData['fingerprint'] : 'SHA256:' . strtoupper(substr(md5($csrRequest->common_name . microtime()), 0, 40)),
            'issuer' => 'VuProject CA',
            'issued_at' => now(),
            'expires_at' => now()->addDays($csrRequest->validity_days),
            'validity_days' => $csrRequest->validity_days,
            'signature_algorithm' => 'SHA256withRSA',
            'key_size' => $csrRequest->key_size,
            'key_type' => 'RSA',
            'user_id' => $csrRequest->user_id,
            'approved_by' => 1,
            'notes' => 'Approved from CSR Request: ' . $csrRequest->request_id,
            'metadata' => $csrRequest->metadata,
        ]);

        // Update CSR request status
        $csrRequest->update([
            'status' => 'approved',
            'approved_by' => 1,
            'approved_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'CSR approved and certificate issued' . ($useRealCA ? ' via Real CA Server' : ''),
            'data' => $certificate
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to approve CSR: ' . $e->getMessage()
        ], 500);
    }
});

Route::post('/csr/reject/{id}', function (Request $request, $id) {
    try {
        $csrRequest = \App\Models\CertificateRequest::findOrFail($id);
        
        $csrRequest->update([
            'status' => 'rejected',
            'approved_by' => 1,
            'approved_at' => now(),
            'rejection_reason' => $request->input('reason', 'No reason provided'),
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'CSR rejected'
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to reject CSR: ' . $e->getMessage()
        ], 500);
    }
});

Route::post('/certificates/renew', function (Request $request) {
    try {
        $certificateId = $request->input('certificateId');
        $validityDays = $request->input('validityDays', 365);
        
        // Try Real CA Server first
        try {
            $caClient = new \GuzzleHttp\Client(['verify' => false, 'timeout' => 5]);
            $caResponse = $caClient->post('https://localhost:8443/certificates/renew', [
                'json' => [
                    'certificateId' => $certificateId,
                    'validityDays' => $validityDays
                ]
            ]);
            $caData = json_decode($caResponse->getBody(), true);
            
            if ($caData && $caData['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Certificate renewed successfully on Real CA Server',
                    'source' => 'real-ca-server',
                    'data' => $caData['newCertificate'] ?? null
                ]);
            }
        } catch (\Exception $caError) {
            \Log::info('Real CA not available for renew, using database', ['error' => $caError->getMessage()]);
        }
        
        // Fallback to database
        $oldCertificate = \App\Models\Certificate::where('id', $certificateId)
            ->orWhere('certificate_id', $certificateId)
            ->first();
        
        if (!$oldCertificate) {
            return response()->json([
                'success' => false,
                'message' => 'Certificate not found'
            ], 404);
        }
        
        // Create renewed certificate
        $newCertificate = \App\Models\Certificate::create([
            'certificate_id' => 'CERT-' . strtoupper(substr(md5($oldCertificate->common_name . time()), 0, 8)),
            'common_name' => $oldCertificate->common_name,
            'subject_alt_names' => $oldCertificate->subject_alt_names,
            'status' => 'issued',
            'type' => $oldCertificate->type,
            'serial_number' => strtoupper(substr(md5(time() . $oldCertificate->common_name), 0, 16)),
            'fingerprint' => 'SHA256:' . strtoupper(substr(md5($oldCertificate->common_name . microtime()), 0, 40)),
            'issuer' => 'VuProject CA',
            'issued_at' => now(),
            'expires_at' => now()->addDays($validityDays),
            'validity_days' => $validityDays,
            'signature_algorithm' => $oldCertificate->signature_algorithm,
            'key_size' => $oldCertificate->key_size,
            'key_type' => $oldCertificate->key_type,
            'user_id' => $oldCertificate->user_id,
            'approved_by' => 1,
            'notes' => 'Renewed from certificate: ' . $oldCertificate->certificate_id,
            'metadata' => json_encode(array_merge(
                json_decode($oldCertificate->metadata, true) ?? [],
                ['renewed_from' => $oldCertificate->certificate_id]
            )),
        ]);
        
        // Mark old certificate as renewed
        $oldCertificate->update([
            'status' => 'renewed',
            'notes' => ($oldCertificate->notes ?? '') . ' | Renewed to: ' . $newCertificate->certificate_id,
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Certificate renewed successfully',
            'source' => 'database',
            'data' => $newCertificate
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to renew certificate: ' . $e->getMessage()
        ], 500);
    }
});

Route::post('/certificates/revoke', function (Request $request) {
    try {
        $certificateId = $request->input('certificateId');
        $reason = $request->input('reason', 'Revoked by admin');
        
        // Try Real CA Server first
        try {
            $caClient = new \GuzzleHttp\Client(['verify' => false, 'timeout' => 5]);
            $caResponse = $caClient->post('https://localhost:8443/certificates/revoke', [
                'json' => [
                    'certificateId' => $certificateId,
                    'reason' => $reason
                ]
            ]);
            $caData = json_decode($caResponse->getBody(), true);
            
            if ($caData && $caData['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Certificate revoked successfully on Real CA Server',
                    'source' => 'real-ca-server'
                ]);
            }
        } catch (\Exception $caError) {
            \Log::info('Real CA not available for revoke, using database', ['error' => $caError->getMessage()]);
        }
        
        // Fallback to database
        $certificate = \App\Models\Certificate::where('id', $certificateId)
            ->orWhere('certificate_id', $certificateId)
            ->first();
        
        if (!$certificate) {
            return response()->json([
                'success' => false,
                'message' => 'Certificate not found'
            ], 404);
        }
        
        $certificate->update([
            'status' => 'revoked',
            'revoked_at' => now(),
            'revocation_reason' => $reason,
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Certificate revoked successfully',
            'source' => 'database'
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to revoke certificate: ' . $e->getMessage()
        ], 500);
    }
});

// Notifications
Route::get('/notifications', function () {
    $notifications = \App\Models\Notification::orderBy('created_at', 'desc')->take(50)->get();
    return response()->json([
        'success' => true,
        'data' => $notifications
    ]);
});

Route::post('/notifications/check-expiring', function () {
    try {
        \Artisan::call('certificates:check-expiring');
        $output = \Artisan::output();
        
        return response()->json([
            'success' => true,
            'message' => 'Expiry check completed',
            'output' => $output
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to check expiring certificates: ' . $e->getMessage()
        ], 500);
    }
});

// Public Authentication routes
Route::prefix('auth')->group(function () {
    Route::post('/register', [App\Http\Controllers\Api\AuthController::class, 'register']);
    Route::post('/login', [App\Http\Controllers\Api\AuthController::class, 'login']);
    Route::post('/logout', [App\Http\Controllers\Api\AuthController::class, 'logout'])->middleware('auth:api');
    Route::get('/me', [App\Http\Controllers\Api\AuthController::class, 'me'])->middleware('auth:api');
});

// Protected Certificate routes
Route::middleware('auth:api')->prefix('certificates')->group(function () {
    Route::get('/', [App\Http\Controllers\Api\CertificateController::class, 'index']);
    Route::post('/', [App\Http\Controllers\Api\CertificateController::class, 'store']);
    Route::get('/{id}', [App\Http\Controllers\Api\CertificateController::class, 'show']);
    Route::delete('/{id}', [App\Http\Controllers\Api\CertificateController::class, 'destroy']);
});

// ACME Protocol Endpoints (RFC 8555)
Route::prefix('acme')->name('acme.')->group(function () {
    Route::get('/directory', [App\Http\Controllers\Api\ACMEController::class, 'directory'])->name('directory');
    Route::match(['head', 'get'], '/new-nonce', [App\Http\Controllers\Api\ACMEController::class, 'newNonce'])->name('new-nonce');
    Route::post('/new-account', [App\Http\Controllers\Api\ACMEController::class, 'newAccount'])->name('new-account');
    Route::post('/new-order', [App\Http\Controllers\Api\ACMEController::class, 'newOrder'])->name('new-order');
    Route::post('/order/{orderId}', [App\Http\Controllers\Api\ACMEController::class, 'getOrder'])->name('order');
    Route::post('/authz/{authId}', [App\Http\Controllers\Api\ACMEController::class, 'getAuthorization'])->name('authorization');
    Route::post('/challenge/{challengeId}', [App\Http\Controllers\Api\ACMEController::class, 'respondToChallenge'])->name('challenge');
    Route::post('/order/{orderId}/finalize', [App\Http\Controllers\Api\ACMEController::class, 'finalizeOrder'])->name('finalize');
    Route::post('/cert/{certId}', [App\Http\Controllers\Api\ACMEController::class, 'getCertificate'])->name('certificate');
    Route::post('/revoke-cert', [App\Http\Controllers\Api\ACMEController::class, 'revokeCertificate'])->name('revoke-cert');
    Route::post('/key-change', [App\Http\Controllers\Api\ACMEController::class, 'keyChange'])->name('key-change');
    Route::post('/account/{accountId}', [App\Http\Controllers\Api\ACMEController::class, 'getAccount'])->name('account');
    Route::post('/account/{accountId}/orders', [App\Http\Controllers\Api\ACMEController::class, 'getAccountOrders'])->name('orders');
    Route::post('/account/{accountId}/deactivate', [App\Http\Controllers\Api\ACMEController::class, 'deactivateAccount'])->name('deactivate');
});