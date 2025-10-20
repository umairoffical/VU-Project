<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OAuth2Service
{
    private $providers;

    public function __construct()
    {
        $this->providers = [
            'google' => [
                'client_id' => env('GOOGLE_CLIENT_ID'),
                'client_secret' => env('GOOGLE_CLIENT_SECRET'),
                'redirect_uri' => env('GOOGLE_REDIRECT_URI', config('app.url') . '/auth/google/callback'),
                'auth_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
                'token_url' => 'https://oauth2.googleapis.com/token',
                'user_url' => 'https://www.googleapis.com/oauth2/v2/userinfo',
                'scopes' => ['openid', 'email', 'profile']
            ],
            'github' => [
                'client_id' => env('GITHUB_CLIENT_ID'),
                'client_secret' => env('GITHUB_CLIENT_SECRET'),
                'redirect_uri' => env('GITHUB_REDIRECT_URI', config('app.url') . '/auth/github/callback'),
                'auth_url' => 'https://github.com/login/oauth/authorize',
                'token_url' => 'https://github.com/login/oauth/access_token',
                'user_url' => 'https://api.github.com/user',
                'scopes' => ['user:email']
            ],
            'microsoft' => [
                'client_id' => env('MICROSOFT_CLIENT_ID'),
                'client_secret' => env('MICROSOFT_CLIENT_SECRET'),
                'redirect_uri' => env('MICROSOFT_REDIRECT_URI', config('app.url') . '/auth/microsoft/callback'),
                'auth_url' => 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize',
                'token_url' => 'https://login.microsoftonline.com/common/oauth2/v2.0/token',
                'user_url' => 'https://graph.microsoft.com/v1.0/me',
                'scopes' => ['openid', 'email', 'profile']
            ],
            'okta' => [
                'client_id' => env('OKTA_CLIENT_ID'),
                'client_secret' => env('OKTA_CLIENT_SECRET'),
                'redirect_uri' => env('OKTA_REDIRECT_URI', config('app.url') . '/auth/okta/callback'),
                'auth_url' => env('OKTA_DOMAIN') . '/oauth2/default/v1/authorize',
                'token_url' => env('OKTA_DOMAIN') . '/oauth2/default/v1/token',
                'user_url' => env('OKTA_DOMAIN') . '/oauth2/default/v1/userinfo',
                'scopes' => ['openid', 'email', 'profile']
            ]
        ];
    }

    /**
     * Get authorization URL for OAuth provider
     */
    public function getAuthorizationUrl(string $provider, ?string $state = null): ?string
    {
        if (!isset($this->providers[$provider])) {
            Log::error('OAuth provider not configured', ['provider' => $provider]);
            return null;
        }

        $config = $this->providers[$provider];
        $state = $state ?? Str::random(40);

        // Store state in cache for verification
        cache()->put("oauth:state:{$state}", $provider, now()->addMinutes(10));

        $params = http_build_query([
            'client_id' => $config['client_id'],
            'redirect_uri' => $config['redirect_uri'],
            'response_type' => 'code',
            'scope' => implode(' ', $config['scopes']),
            'state' => $state
        ]);

        return $config['auth_url'] . '?' . $params;
    }

    /**
     * Exchange authorization code for access token
     */
    public function getAccessToken(string $provider, string $code): ?array
    {
        try {
            if (!isset($this->providers[$provider])) {
                throw new \Exception('OAuth provider not configured');
            }

            $config = $this->providers[$provider];

            $response = Http::asForm()->post($config['token_url'], [
                'client_id' => $config['client_id'],
                'client_secret' => $config['client_secret'],
                'code' => $code,
                'grant_type' => 'authorization_code',
                'redirect_uri' => $config['redirect_uri']
            ]);

            if ($response->failed()) {
                throw new \Exception('Failed to get access token: ' . $response->body());
            }

            $data = $response->json();

            Log::info('OAuth access token obtained', ['provider' => $provider]);

            return [
                'access_token' => $data['access_token'],
                'token_type' => $data['token_type'] ?? 'Bearer',
                'expires_in' => $data['expires_in'] ?? 3600,
                'refresh_token' => $data['refresh_token'] ?? null,
                'scope' => $data['scope'] ?? null
            ];

        } catch (\Exception $e) {
            Log::error('OAuth token exchange failed', [
                'error' => $e->getMessage(),
                'provider' => $provider
            ]);

            return null;
        }
    }

    /**
     * Get user information from OAuth provider
     */
    public function getUserInfo(string $provider, string $accessToken): ?array
    {
        try {
            if (!isset($this->providers[$provider])) {
                throw new \Exception('OAuth provider not configured');
            }

            $config = $this->providers[$provider];

            $response = Http::withToken($accessToken)->get($config['user_url']);

            if ($response->failed()) {
                throw new \Exception('Failed to get user info: ' . $response->body());
            }

            $data = $response->json();

            // Normalize user data across providers
            $normalized = $this->normalizeUserData($provider, $data);

            Log::info('OAuth user info retrieved', ['provider' => $provider, 'email' => $normalized['email']]);

            return $normalized;

        } catch (\Exception $e) {
            Log::error('OAuth user info retrieval failed', [
                'error' => $e->getMessage(),
                'provider' => $provider
            ]);

            return null;
        }
    }

    /**
     * Normalize user data from different OAuth providers
     */
    private function normalizeUserData(string $provider, array $data): array
    {
        switch ($provider) {
            case 'google':
                return [
                    'id' => $data['id'],
                    'email' => $data['email'],
                    'name' => $data['name'],
                    'first_name' => $data['given_name'] ?? null,
                    'last_name' => $data['family_name'] ?? null,
                    'avatar' => $data['picture'] ?? null,
                    'email_verified' => $data['verified_email'] ?? false
                ];

            case 'github':
                return [
                    'id' => $data['id'],
                    'email' => $data['email'],
                    'name' => $data['name'] ?? $data['login'],
                    'first_name' => null,
                    'last_name' => null,
                    'avatar' => $data['avatar_url'] ?? null,
                    'email_verified' => true // GitHub verifies emails
                ];

            case 'microsoft':
                return [
                    'id' => $data['id'],
                    'email' => $data['mail'] ?? $data['userPrincipalName'],
                    'name' => $data['displayName'],
                    'first_name' => $data['givenName'] ?? null,
                    'last_name' => $data['surname'] ?? null,
                    'avatar' => null,
                    'email_verified' => true
                ];

            case 'okta':
                return [
                    'id' => $data['sub'],
                    'email' => $data['email'],
                    'name' => $data['name'],
                    'first_name' => $data['given_name'] ?? null,
                    'last_name' => $data['family_name'] ?? null,
                    'avatar' => null,
                    'email_verified' => $data['email_verified'] ?? false
                ];

            default:
                return $data;
        }
    }

    /**
     * Create or update user from OAuth data
     */
    public function createOrUpdateUser(string $provider, array $userData): User
    {
        $email = $userData['email'];

        // Check if user exists
        $user = User::where('email', $email)->first();

        if ($user) {
            // Update existing user
            $user->update([
                'name' => $userData['name'],
                'oauth_provider' => $provider,
                'oauth_id' => $userData['id'],
                'avatar' => $userData['avatar'] ?? $user->avatar,
                'last_login_at' => now()
            ]);

            Log::info('OAuth user updated', ['email' => $email, 'provider' => $provider]);
        } else {
            // Create new user
            $user = User::create([
                'name' => $userData['name'],
                'email' => $email,
                'password' => bcrypt(Str::random(32)), // Random password for OAuth users
                'role' => 'regular_user',
                'oauth_provider' => $provider,
                'oauth_id' => $userData['id'],
                'avatar' => $userData['avatar'],
                'email_verified_at' => $userData['email_verified'] ? now() : null,
                'is_active' => true,
                'last_login_at' => now()
            ]);

            Log::info('OAuth user created', ['email' => $email, 'provider' => $provider]);
        }

        return $user;
    }

    /**
     * Refresh access token
     */
    public function refreshToken(string $provider, string $refreshToken): ?array
    {
        try {
            if (!isset($this->providers[$provider])) {
                throw new \Exception('OAuth provider not configured');
            }

            $config = $this->providers[$provider];

            $response = Http::asForm()->post($config['token_url'], [
                'client_id' => $config['client_id'],
                'client_secret' => $config['client_secret'],
                'refresh_token' => $refreshToken,
                'grant_type' => 'refresh_token'
            ]);

            if ($response->failed()) {
                throw new \Exception('Failed to refresh token: ' . $response->body());
            }

            $data = $response->json();

            Log::info('OAuth token refreshed', ['provider' => $provider]);

            return [
                'access_token' => $data['access_token'],
                'token_type' => $data['token_type'] ?? 'Bearer',
                'expires_in' => $data['expires_in'] ?? 3600,
                'refresh_token' => $data['refresh_token'] ?? $refreshToken
            ];

        } catch (\Exception $e) {
            Log::error('OAuth token refresh failed', [
                'error' => $e->getMessage(),
                'provider' => $provider
            ]);

            return null;
        }
    }

    /**
     * Verify OAuth state parameter
     */
    public function verifyState(string $state): ?string
    {
        $provider = cache()->get("oauth:state:{$state}");
        
        if ($provider) {
            cache()->forget("oauth:state:{$state}");
        }

        return $provider;
    }

    /**
     * Get list of configured providers
     */
    public function getConfiguredProviders(): array
    {
        $configured = [];

        foreach ($this->providers as $name => $config) {
            if (!empty($config['client_id']) && !empty($config['client_secret'])) {
                $configured[] = $name;
            }
        }

        return $configured;
    }
}

