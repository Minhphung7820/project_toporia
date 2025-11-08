<?php

declare(strict_types=1);

namespace Toporia\Framework\RateLimit;

/**
 * Rate Limiter Interface
 *
 * Defines contract for rate limiting implementations.
 * Supports various algorithms: Token Bucket, Sliding Window, etc.
 */
interface RateLimiterInterface
{
    /**
     * Attempt to consume tokens from the limiter
     *
     * @param string $key Unique identifier (e.g., user ID, IP address)
     * @param int $maxAttempts Maximum allowed attempts
     * @param int $decaySeconds Time window in seconds
     * @return bool True if allowed, false if rate limited
     */
    public function attempt(string $key, int $maxAttempts, int $decaySeconds): bool;

    /**
     * Check if rate limit has been exceeded without consuming
     *
     * @param string $key
     * @param int $maxAttempts
     * @return bool True if too many attempts
     */
    public function tooManyAttempts(string $key, int $maxAttempts): bool;

    /**
     * Get the number of attempts for a key
     *
     * @param string $key
     * @return int
     */
    public function attempts(string $key): int;

    /**
     * Get the number of remaining attempts
     *
     * @param string $key
     * @param int $maxAttempts
     * @return int
     */
    public function remaining(string $key, int $maxAttempts): int;

    /**
     * Get the time until the rate limit resets
     *
     * @param string $key
     * @return int Seconds until reset
     */
    public function availableIn(string $key): int;

    /**
     * Clear all attempts for a key
     *
     * @param string $key
     * @return void
     */
    public function clear(string $key): void;

    /**
     * Reset attempts for a key
     *
     * @param string $key
     * @return void
     */
    public function resetAttempts(string $key): void;
}
