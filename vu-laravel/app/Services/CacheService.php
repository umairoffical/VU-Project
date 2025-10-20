<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

class CacheService
{
    private $defaultTTL;
    private $prefix;

    public function __construct()
    {
        $this->defaultTTL = 3600; // 1 hour
        $this->prefix = 'vuproject:';
    }

    /**
     * Get a value from cache
     */
    public function get(string $key, $default = null)
    {
        try {
            return Cache::get($this->prefix . $key, $default);
        } catch (\Exception $e) {
            Log::error('Cache get failed', ['key' => $key, 'error' => $e->getMessage()]);
            return $default;
        }
    }

    /**
     * Set a value in cache
     */
    public function set(string $key, $value, ?int $ttl = null): bool
    {
        try {
            $ttl = $ttl ?? $this->defaultTTL;
            return Cache::put($this->prefix . $key, $value, now()->addSeconds($ttl));
        } catch (\Exception $e) {
            Log::error('Cache set failed', ['key' => $key, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Remember (get or set)
     */
    public function remember(string $key, callable $callback, ?int $ttl = null)
    {
        try {
            $ttl = $ttl ?? $this->defaultTTL;
            return Cache::remember($this->prefix . $key, now()->addSeconds($ttl), $callback);
        } catch (\Exception $e) {
            Log::error('Cache remember failed', ['key' => $key, 'error' => $e->getMessage()]);
            return $callback();
        }
    }

    /**
     * Delete a value from cache
     */
    public function forget(string $key): bool
    {
        try {
            return Cache::forget($this->prefix . $key);
        } catch (\Exception $e) {
            Log::error('Cache forget failed', ['key' => $key, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Clear all cache
     */
    public function flush(): bool
    {
        try {
            return Cache::flush();
        } catch (\Exception $e) {
            Log::error('Cache flush failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Cache certificate data
     */
    public function cacheCertificate(string $certificateId, array $data, int $ttl = 3600): bool
    {
        return $this->set("certificate:{$certificateId}", $data, $ttl);
    }

    /**
     * Get cached certificate
     */
    public function getCachedCertificate(string $certificateId): ?array
    {
        return $this->get("certificate:{$certificateId}");
    }

    /**
     * Cache certificate list
     */
    public function cacheCertificateList(string $filterKey, array $certificates, int $ttl = 600): bool
    {
        return $this->set("certificates:list:{$filterKey}", $certificates, $ttl);
    }

    /**
     * Get cached certificate list
     */
    public function getCachedCertificateList(string $filterKey): ?array
    {
        return $this->get("certificates:list:{$filterKey}");
    }

    /**
     * Invalidate certificate caches
     */
    public function invalidateCertificateCaches(string $certificateId): void
    {
        $this->forget("certificate:{$certificateId}");
        
        // Clear list caches
        $patterns = [
            'certificates:list:*',
            'certificates:stats:*',
            'certificates:expiring:*'
        ];
        
        foreach ($patterns as $pattern) {
            $this->forgetPattern($pattern);
        }
    }

    /**
     * Cache statistics
     */
    public function cacheStats(string $key, array $stats, int $ttl = 300): bool
    {
        return $this->set("stats:{$key}", $stats, $ttl);
    }

    /**
     * Get cached statistics
     */
    public function getCachedStats(string $key): ?array
    {
        return $this->get("stats:{$key}");
    }

    /**
     * Cache user data
     */
    public function cacheUser(int $userId, array $data, int $ttl = 1800): bool
    {
        return $this->set("user:{$userId}", $data, $ttl);
    }

    /**
     * Get cached user
     */
    public function getCachedUser(int $userId): ?array
    {
        return $this->get("user:{$userId}");
    }

    /**
     * Invalidate user cache
     */
    public function invalidateUserCache(int $userId): void
    {
        $this->forget("user:{$userId}");
        $this->forget("user:{$userId}:permissions");
        $this->forget("user:{$userId}:certificates");
    }

    /**
     * Rate limiting
     */
    public function rateLimit(string $key, int $maxAttempts, int $decaySeconds): bool
    {
        try {
            $attempts = Cache::increment($this->prefix . "rate_limit:{$key}");
            
            if ($attempts === 1) {
                Cache::put($this->prefix . "rate_limit:{$key}", 1, now()->addSeconds($decaySeconds));
            }
            
            return $attempts <= $maxAttempts;
        } catch (\Exception $e) {
            Log::error('Rate limit check failed', ['key' => $key, 'error' => $e->getMessage()]);
            return true; // Allow on error
        }
    }

    /**
     * Get rate limit attempts
     */
    public function getRateLimitAttempts(string $key): int
    {
        return (int) $this->get("rate_limit:{$key}", 0);
    }

    /**
     * Clear rate limit
     */
    public function clearRateLimit(string $key): bool
    {
        return $this->forget("rate_limit:{$key}");
    }

    /**
     * Lock mechanism
     */
    public function lock(string $key, int $seconds = 10): bool
    {
        try {
            return Cache::add($this->prefix . "lock:{$key}", true, now()->addSeconds($seconds));
        } catch (\Exception $e) {
            Log::error('Lock failed', ['key' => $key, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Release lock
     */
    public function unlock(string $key): bool
    {
        return $this->forget("lock:{$key}");
    }

    /**
     * Check if key is locked
     */
    public function isLocked(string $key): bool
    {
        return $this->get("lock:{$key}") !== null;
    }

    /**
     * Forget keys matching pattern (Redis only)
     */
    private function forgetPattern(string $pattern): void
    {
        try {
            if (config('cache.default') === 'redis') {
                $keys = Redis::keys($this->prefix . $pattern);
                if (!empty($keys)) {
                    foreach ($keys as $key) {
                        // Remove prefix from key
                        $cleanKey = str_replace($this->prefix, '', $key);
                        $this->forget($cleanKey);
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('Forget pattern failed', ['pattern' => $pattern, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Get cache statistics
     */
    public function getCacheStats(): array
    {
        try {
            if (config('cache.default') === 'redis') {
                $info = Redis::info();
                
                return [
                    'connected' => true,
                    'used_memory' => $info['used_memory_human'] ?? 'N/A',
                    'connected_clients' => $info['connected_clients'] ?? 0,
                    'total_keys' => $this->getTotalKeys(),
                    'hit_rate' => $this->calculateHitRate(),
                    'driver' => 'redis'
                ];
            }
            
            return [
                'connected' => true,
                'driver' => config('cache.default'),
                'total_keys' => 'N/A'
            ];
        } catch (\Exception $e) {
            Log::error('Get cache stats failed', ['error' => $e->getMessage()]);
            
            return [
                'connected' => false,
                'error' => $e->getMessage(),
                'driver' => config('cache.default')
            ];
        }
    }

    /**
     * Get total number of keys
     */
    private function getTotalKeys(): int
    {
        try {
            if (config('cache.default') === 'redis') {
                $keys = Redis::keys($this->prefix . '*');
                return count($keys);
            }
            return 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Calculate cache hit rate
     */
    private function calculateHitRate(): string
    {
        try {
            if (config('cache.default') === 'redis') {
                $info = Redis::info('stats');
                $hits = $info['keyspace_hits'] ?? 0;
                $misses = $info['keyspace_misses'] ?? 0;
                $total = $hits + $misses;
                
                if ($total > 0) {
                    $rate = ($hits / $total) * 100;
                    return number_format($rate, 2) . '%';
                }
            }
            return 'N/A';
        } catch (\Exception $e) {
            return 'N/A';
        }
    }

    /**
     * Warm up cache with common data
     */
    public function warmUp(): array
    {
        $warmedUp = [];
        
        try {
            // Cache common queries
            // This would be implemented based on your specific needs
            
            Log::info('Cache warm-up completed', ['items' => count($warmedUp)]);
            
            return [
                'success' => true,
                'items_cached' => count($warmedUp),
                'items' => $warmedUp
            ];
        } catch (\Exception $e) {
            Log::error('Cache warm-up failed', ['error' => $e->getMessage()]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}

