<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ACMEService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * ACME Protocol Controller (RFC 8555)
 * Automated Certificate Management Environment
 */
class ACMEController extends Controller
{
    private $acmeService;

    public function __construct(ACMEService $acmeService)
    {
        $this->acmeService = $acmeService;
    }

    /**
     * Get ACME directory
     * GET /acme/directory
     */
    public function directory(): JsonResponse
    {
        return response()->json($this->acmeService->getDirectory());
    }

    /**
     * Get new nonce
     * HEAD /acme/new-nonce
     * GET /acme/new-nonce
     */
    public function newNonce(): JsonResponse
    {
        $nonce = $this->acmeService->generateNonce();
        
        return response()->json(['nonce' => $nonce], 200, [
            'Replay-Nonce' => $nonce,
            'Cache-Control' => 'no-store'
        ]);
    }

    /**
     * Create new account
     * POST /acme/new-account
     */
    public function newAccount(Request $request): JsonResponse
    {
        try {
            $contact = $request->input('contact', []);
            $termsOfServiceAgreed = $request->input('termsOfServiceAgreed');

            $account = $this->acmeService->createAccount($contact, $termsOfServiceAgreed);
            
            $nonce = $this->acmeService->generateNonce();

            return response()->json($account, 201, [
                'Location' => route('acme.account', ['accountId' => $account['id']]),
                'Replay-Nonce' => $nonce
            ]);

        } catch (\Exception $e) {
            Log::error('ACME new-account failed', ['error' => $e->getMessage()]);
            
            return response()->json([
                'type' => 'urn:ietf:params:acme:error:malformed',
                'detail' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Create new order
     * POST /acme/new-order
     */
    public function newOrder(Request $request): JsonResponse
    {
        try {
            $accountId = $request->input('account_id');
            $identifiers = $request->input('identifiers', []);

            if (empty($identifiers)) {
                throw new \Exception('At least one identifier is required');
            }

            $order = $this->acmeService->createOrder($accountId, $identifiers);
            
            $nonce = $this->acmeService->generateNonce();

            return response()->json($order, 201, [
                'Location' => route('acme.order', ['orderId' => $order['id']]),
                'Replay-Nonce' => $nonce
            ]);

        } catch (\Exception $e) {
            Log::error('ACME new-order failed', ['error' => $e->getMessage()]);
            
            return response()->json([
                'type' => 'urn:ietf:params:acme:error:malformed',
                'detail' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get order
     * POST /acme/order/{orderId}
     */
    public function getOrder(string $orderId): JsonResponse
    {
        try {
            $order = $this->acmeService->getOrder($orderId);
            
            if (!$order) {
                return response()->json([
                    'type' => 'urn:ietf:params:acme:error:notFound',
                    'detail' => 'Order not found'
                ], 404);
            }

            $nonce = $this->acmeService->generateNonce();

            return response()->json($order, 200, [
                'Replay-Nonce' => $nonce
            ]);

        } catch (\Exception $e) {
            Log::error('ACME get-order failed', ['error' => $e->getMessage()]);
            
            return response()->json([
                'type' => 'urn:ietf:params:acme:error:serverInternal',
                'detail' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get authorization
     * POST /acme/authz/{authId}
     */
    public function getAuthorization(string $authId): JsonResponse
    {
        try {
            $authz = $this->acmeService->getAuthorization($authId);
            
            if (!$authz) {
                return response()->json([
                    'type' => 'urn:ietf:params:acme:error:notFound',
                    'detail' => 'Authorization not found'
                ], 404);
            }

            $nonce = $this->acmeService->generateNonce();

            return response()->json($authz, 200, [
                'Replay-Nonce' => $nonce
            ]);

        } catch (\Exception $e) {
            Log::error('ACME get-authorization failed', ['error' => $e->getMessage()]);
            
            return response()->json([
                'type' => 'urn:ietf:params:acme:error:serverInternal',
                'detail' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Respond to challenge
     * POST /acme/challenge/{challengeId}
     */
    public function respondToChallenge(Request $request, string $challengeId): JsonResponse
    {
        try {
            $accountId = $request->input('account_id');
            $token = $request->input('token');
            $type = $request->input('type', 'http-01');

            $validated = false;

            if ($type === 'http-01') {
                $validated = $this->acmeService->validateHttpChallenge($token, $accountId);
            } elseif ($type === 'dns-01') {
                $domain = $request->input('domain');
                $validated = $this->acmeService->validateDnsChallenge($token, $domain, $accountId);
            }

            $nonce = $this->acmeService->generateNonce();

            if ($validated) {
                return response()->json([
                    'type' => $type,
                    'status' => 'valid',
                    'url' => route('acme.challenge', ['challengeId' => $challengeId]),
                    'token' => $token,
                    'validated' => now()->toISOString()
                ], 200, ['Replay-Nonce' => $nonce]);
            }

            return response()->json([
                'type' => $type,
                'status' => 'invalid',
                'error' => [
                    'type' => 'urn:ietf:params:acme:error:incorrectResponse',
                    'detail' => 'Challenge validation failed'
                ]
            ], 400, ['Replay-Nonce' => $nonce]);

        } catch (\Exception $e) {
            Log::error('ACME challenge response failed', ['error' => $e->getMessage()]);
            
            return response()->json([
                'type' => 'urn:ietf:params:acme:error:serverInternal',
                'detail' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Finalize order
     * POST /acme/order/{orderId}/finalize
     */
    public function finalizeOrder(Request $request, string $orderId): JsonResponse
    {
        try {
            $csr = $request->input('csr');

            if (!$csr) {
                throw new \Exception('CSR is required');
            }

            $order = $this->acmeService->finalizeOrder($orderId, $csr);
            
            $nonce = $this->acmeService->generateNonce();

            return response()->json($order, 200, [
                'Replay-Nonce' => $nonce
            ]);

        } catch (\Exception $e) {
            Log::error('ACME finalize-order failed', ['error' => $e->getMessage()]);
            
            return response()->json([
                'type' => 'urn:ietf:params:acme:error:malformed',
                'detail' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Download certificate
     * POST /acme/cert/{certId}
     */
    public function getCertificate(string $certId): JsonResponse
    {
        try {
            $certificate = $this->acmeService->getCertificate($certId);
            
            if (!$certificate) {
                return response()->json([
                    'type' => 'urn:ietf:params:acme:error:notFound',
                    'detail' => 'Certificate not found'
                ], 404);
            }

            $nonce = $this->acmeService->generateNonce();

            return response($certificate, 200, [
                'Content-Type' => 'application/pem-certificate-chain',
                'Replay-Nonce' => $nonce
            ]);

        } catch (\Exception $e) {
            Log::error('ACME get-certificate failed', ['error' => $e->getMessage()]);
            
            return response()->json([
                'type' => 'urn:ietf:params:acme:error:serverInternal',
                'detail' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Revoke certificate
     * POST /acme/revoke-cert
     */
    public function revokeCertificate(Request $request): JsonResponse
    {
        try {
            $certificate = $request->input('certificate');
            $reason = $request->input('reason', 0);

            if (!$certificate) {
                throw new \Exception('Certificate is required');
            }

            $revoked = $this->acmeService->revokeCertificate($certificate, $reason);
            
            $nonce = $this->acmeService->generateNonce();

            if ($revoked) {
                return response()->json([
                    'status' => 'revoked'
                ], 200, ['Replay-Nonce' => $nonce]);
            }

            throw new \Exception('Certificate revocation failed');

        } catch (\Exception $e) {
            Log::error('ACME revoke-certificate failed', ['error' => $e->getMessage()]);
            
            return response()->json([
                'type' => 'urn:ietf:params:acme:error:malformed',
                'detail' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Key change
     * POST /acme/key-change
     */
    public function keyChange(Request $request): JsonResponse
    {
        try {
            $accountId = $request->input('account_id');
            $newKey = $request->input('new_key');

            if (!$accountId || !$newKey) {
                throw new \Exception('Account ID and new key are required');
            }

            $changed = $this->acmeService->keyChange($accountId, $newKey);
            
            $nonce = $this->acmeService->generateNonce();

            if ($changed) {
                return response()->json([
                    'status' => 'ok'
                ], 200, ['Replay-Nonce' => $nonce]);
            }

            throw new \Exception('Key change failed');

        } catch (\Exception $e) {
            Log::error('ACME key-change failed', ['error' => $e->getMessage()]);
            
            return response()->json([
                'type' => 'urn:ietf:params:acme:error:malformed',
                'detail' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get account orders
     * POST /acme/account/{accountId}/orders
     */
    public function getAccountOrders(string $accountId): JsonResponse
    {
        try {
            $orders = $this->acmeService->getAccountOrders($accountId);
            
            $nonce = $this->acmeService->generateNonce();

            return response()->json($orders, 200, [
                'Replay-Nonce' => $nonce
            ]);

        } catch (\Exception $e) {
            Log::error('ACME get-account-orders failed', ['error' => $e->getMessage()]);
            
            return response()->json([
                'type' => 'urn:ietf:params:acme:error:serverInternal',
                'detail' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Deactivate account
     * POST /acme/account/{accountId}/deactivate
     */
    public function deactivateAccount(string $accountId): JsonResponse
    {
        try {
            $deactivated = $this->acmeService->deactivateAccount($accountId);
            
            $nonce = $this->acmeService->generateNonce();

            if ($deactivated) {
                return response()->json([
                    'status' => 'deactivated'
                ], 200, ['Replay-Nonce' => $nonce]);
            }

            throw new \Exception('Account deactivation failed');

        } catch (\Exception $e) {
            Log::error('ACME deactivate-account failed', ['error' => $e->getMessage()]);
            
            return response()->json([
                'type' => 'urn:ietf:params:acme:error:malformed',
                'detail' => $e->getMessage()
            ], 500);
        }
    }
}

