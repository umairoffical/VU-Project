<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function __construct()
    {
        // Simple constructor without 2FA dependency
    }

    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|unique:users|min:3|max:50',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:6|confirmed',
            'first_name' => 'required|string|max:50',
            'last_name' => 'required|string|max:50',
            'phone' => 'nullable|string|max:20',
            'department' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Create user in database
            $user = User::create([
                'username' => $request->username,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'phone' => $request->phone,
                'department' => $request->department,
                'role' => 'regular_user',
                'is_active' => true,
            ]);

            // Log the registration
            $this->logAuditEvent('user_registered', 'authentication', 'New user registered', [
                'user_id' => $user->id,
                'username' => $user->username,
                'email' => $user->email
            ], $request);

            return response()->json([
                'success' => true,
                'message' => 'Registration successful! You can now login.',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'username' => $user->username,
                        'email' => $user->email,
                        'role' => $user->role,
                        'first_name' => $user->first_name,
                        'last_name' => $user->last_name,
                        'full_name' => $user->first_name . ' ' . $user->last_name,
                    ]
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Registration failed: ' . $e->getMessage()
            ], 500);
        }
    }

    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $credentials = $request->only('username', 'password');
        
        // Find user by username or email
        $user = User::where('username', $credentials['username'])
                   ->orWhere('email', $credentials['username'])
                   ->first();

        // Check if user exists
        if (!$user) {
            $this->logAuditEvent('login_failed', 'authentication', 'Failed login attempt - user not found', [
                'username' => $credentials['username'],
                'ip_address' => $request->ip()
            ], $request);

            return response()->json([
                'success' => false,
                'message' => 'Invalid username or password'
            ], 401);
        }

        // Check password
        if (!Hash::check($credentials['password'], $user->password)) {
            $this->logAuditEvent('login_failed', 'authentication', 'Failed login attempt - wrong password', [
                'user_id' => $user->id,
                'username' => $credentials['username'],
                'ip_address' => $request->ip()
            ], $request);

            return response()->json([
                'success' => false,
                'message' => 'Invalid username or password'
            ], 401);
        }

        // Check if account is active
        if (!$user->is_active) {
            $this->logAuditEvent('login_blocked', 'authentication', 'Login blocked - account inactive', [
                'user_id' => $user->id,
                'username' => $user->username
            ], $request);

            return response()->json([
                'success' => false,
                'message' => 'Your account is inactive. Please contact administrator.'
            ], 403);
        }

        try {
            // Generate simple token (session-based for simplicity)
            $token = base64_encode($user->id . '|' . $user->username . '|' . time());
            
            // Update last login in database
            $user->update([
                'last_login_at' => now(),
                'last_login_ip' => $request->ip()
            ]);

            // Log successful login
            $this->logAuditEvent('login_success', 'authentication', 'User logged in successfully', [
                'user_id' => $user->id,
                'username' => $user->username,
                'role' => $user->role
            ], $request);

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'username' => $user->username,
                        'email' => $user->email,
                        'role' => $user->role,
                        'first_name' => $user->first_name,
                        'last_name' => $user->last_name,
                        'full_name' => $user->first_name . ' ' . $user->last_name,
                        'last_login_at' => $user->last_login_at,
                    ],
                    'token' => $token,
                    'token_type' => 'bearer'
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Login failed: ' . $e->getMessage()
            ], 500);
        }
    }

    public function logout(Request $request): JsonResponse
    {
        try {
            // Simple logout - just return success
            // In a real app, you would invalidate the token from session/cache
            
            return response()->json([
                'success' => true,
                'message' => 'Logout successful'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Could not logout: ' . $e->getMessage()
            ], 500);
        }
    }

    public function me(Request $request): JsonResponse
    {
        // For now, return a simple response
        // In production, you would verify the token and get the actual user
        return response()->json([
            'success' => true,
            'message' => 'Please provide valid authentication token',
            'data' => null
        ]);
    }

    public function refresh(): JsonResponse
    {
        try {
            $token = JWTAuth::refresh(JWTAuth::getToken());
            
            return response()->json([
                'success' => true,
                'data' => [
                    'token' => $token,
                    'token_type' => 'bearer',
                    'expires_in' => config('jwt.ttl') * 60
                ]
            ]);

        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Could not refresh token'
            ], 401);
        }
    }

    public function setupTwoFactor(): JsonResponse
    {
        $user = auth()->user();
        
        if ($user->hasTwoFactorEnabled()) {
            return response()->json([
                'success' => false,
                'message' => 'Two-factor authentication is already enabled'
            ], 400);
        }

        $secret = $user->generateTwoFactorSecret();
        $qrCodeUrl = $this->google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret
        );

        return response()->json([
            'success' => true,
            'data' => [
                'secret' => $secret,
                'qr_code_url' => $qrCodeUrl,
                'recovery_codes' => $user->generateRecoveryCodes()
            ]
        ]);
    }

    public function verifyTwoFactor(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = auth()->user();
        
        if ($user->verifyTwoFactorCode($request->code)) {
            $user->update(['two_factor_enabled' => true]);
            
            $this->logAuditEvent('2fa_enabled', 'authentication', 'Two-factor authentication enabled', [
                'user_id' => $user->id,
                'username' => $user->username
            ], $request);

            return response()->json([
                'success' => true,
                'message' => 'Two-factor authentication enabled successfully'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Invalid verification code'
        ], 400);
    }

    public function disableTwoFactor(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'password' => 'required|string',
            'code' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = auth()->user();
        
        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid password'
            ], 400);
        }

        if (!$user->verifyTwoFactorCode($request->code)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid verification code'
            ], 400);
        }

        $user->update([
            'two_factor_enabled' => false,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null
        ]);

        $this->logAuditEvent('2fa_disabled', 'authentication', 'Two-factor authentication disabled', [
            'user_id' => $user->id,
            'username' => $user->username
        ], $request);

        return response()->json([
            'success' => true,
            'message' => 'Two-factor authentication disabled successfully'
        ]);
    }

    private function getUserPermissions(User $user): array
    {
        $permissions = [];

        if ($user->isAdmin()) {
            $permissions = [
                'manage_users',
                'manage_certificates',
                'approve_requests',
                'view_audit_logs',
                'manage_system_settings',
                'view_all_certificates',
                'revoke_certificates',
                'generate_certificates'
            ];
        } elseif ($user->isCertificateManager()) {
            $permissions = [
                'manage_certificates',
                'approve_requests',
                'view_audit_logs',
                'view_all_certificates',
                'revoke_certificates',
                'generate_certificates'
            ];
        } else {
            $permissions = [
                'view_own_certificates',
                'request_certificates',
                'view_own_requests'
            ];
        }

        return $permissions;
    }

    private function logAuditEvent(string $eventType, string $category, string $description, array $metadata = [], Request $request = null): void
    {
        AuditLog::create([
            'event_type' => $eventType,
            'event_category' => $category,
            'description' => $description,
            'ip_address' => $request ? $request->ip() : '127.0.0.1',
            'user_agent' => $request ? $request->userAgent() : null,
            'user_id' => auth()->id(),
            'metadata' => $metadata,
            'severity' => $this->getSeverityFromEventType($eventType)
        ]);
    }

    private function getSeverityFromEventType(string $eventType): string
    {
        return match($eventType) {
            'login_success', 'logout' => 'low',
            'login_failed', '2fa_failed' => 'medium',
            'login_blocked', '2fa_enabled', '2fa_disabled' => 'high',
            default => 'medium'
        };
    }
}