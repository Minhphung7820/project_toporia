<?php

declare(strict_types=1);

namespace Toporia\Framework\RateLimit;

use Toporia\Framework\Cache\CacheInterface;

/**
 * Cache-based Rate Limiter
 *
 * Uses cache backend for rate limiting with sliding window algorithm.
 * Works with any cache driver (File, Redis, Memory).
 */
final class CacheRateLimiter implements RateLimiterInterface
{
    public function __construct(
        private CacheInterface $cache
    ) {}

    public function attempt(string $key, int $maxAttempts, int $decaySeconds): bool
    {
        if ($this->tooManyAttempts($key, $maxAttempts)) {
            return false;
        }

        $this->hit($key, $decaySeconds);
        return true;
    }

    public function tooManyAttempts(string $key, int $maxAttempts): bool
    {
        return $this->attempts($key) >= $maxAttempts;
    }

    public function attempts(string $key): int
    {
        return (int) $this->cache->get($this->attemptsKey($key), 0);
    }

    public function remaining(string $key, int $maxAttempts): int
    {
        $attempts = $this->attempts($key);
        return max(0, $maxAttempts - $attempts);
    }

    public function availableIn(string $key): int
    {
        $resetTime = $this->cache->get($this->resetTimeKey($key));

        if ($resetTime === null) {
            return 0;
        }

        return max(0, $resetTime - time());
    }

    public function clear(string $key): void
    {
        $this->resetAttempts($key);
    }

    public function resetAttempts(string $key): void
    {
        $this->cache->delete($this->attemptsKey($key));
        $this->cache->delete($this->resetTimeKey($key));
    }

    /**
     * Increment the hit counter
     *
     * @param string $key
     * @param int $decaySeconds
     * @return int New attempt count
     */
    public function hit(string $key, int $decaySeconds = 60): int
    {
        $attemptsKey = $this->attemptsKey($key);
        $resetTimeKey = $this->resetTimeKey($key);

        // Set reset time if not exists
        if (!$this->cache->has($resetTimeKey)) {
            $this->cache->set($resetTimeKey, time() + $decaySeconds, $decaySeconds);
        }

        // Increment attempts
        $attempts = $this->cache->increment($attemptsKey, 1);

        if ($attempts === false) {
            $this->cache->set($attemptsKey, 1, $decaySeconds);
            return 1;
        }

        return $attempts;
    }

    /**
     * Get the cache key for attempts counter
     *
     * @param string $key
     * @return string
     */
    private function attemptsKey(string $key): string
    {
        return "rate_limit:{$key}:attempts";
    }

    /**
     * Get the cache key for reset time
     *
     * @param string $key
     * @return string
     */
    private function resetTimeKey(string $key): string
    {
        return "rate_limit:{$key}:reset";
    }
}
