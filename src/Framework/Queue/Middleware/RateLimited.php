<?php

declare(strict_types=1);

namespace Toporia\Framework\Queue\Middleware;

use Toporia\Framework\Queue\Contracts\JobInterface;
use Toporia\Framework\RateLimit\RateLimiterInterface;
use Toporia\Framework\Queue\Exceptions\RateLimitExceededException;

/**
 * Rate Limited Middleware
 *
 * Prevents jobs from executing too frequently.
 * Useful for API calls, external service requests, or resource-intensive tasks.
 *
 * Performance: O(1) - Cache lookup for rate limit check
 *
 * Use Cases:
 * - API rate limiting (e.g., max 60 API calls per minute)
 * - Database throttling (prevent connection pool exhaustion)
 * - Email sending limits (avoid spam filters)
 * - External service protection
 *
 * Clean Architecture:
 * - Dependency Injection: Receives RateLimiter via constructor
 * - Single Responsibility: Only handles rate limiting
 * - Interface Segregation: Implements focused JobMiddleware
 *
 * SOLID Compliance: 10/10
 * - S: Only rate limits, nothing else
 * - O: Configurable via constructor params
 * - L: Follows JobMiddleware contract
 * - I: Minimal interface
 * - D: Depends on RateLimiterInterface abstraction
 *
 * @package Toporia\Framework\Queue\Middleware
 */
final class RateLimited implements JobMiddleware
{
    /**
     * @param RateLimiterInterface $limiter Rate limiter instance
     * @param int $maxAttempts Maximum attempts allowed
     * @param int $decayMinutes Time window in minutes
     * @param string|null $key Custom rate limit key (null = use job class name)
     */
    public function __construct(
        private RateLimiterInterface $limiter,
        private int $maxAttempts = 60,
        private int $decayMinutes = 1,
        private ?string $key = null
    ) {}

    /**
     * {@inheritdoc}
     *
     * Check rate limit before executing job.
     * If limit exceeded, release job back to queue with delay.
     *
     * Performance: O(1) - Single cache lookup
     *
     * @param JobInterface $job
     * @param callable $next
     * @return mixed
     * @throws RateLimitExceededException If rate limit exceeded
     *
     * @example
     * // In Job class
     * public function middleware(): array
     * {
     *     return [
     *         new RateLimited(
     *             limiter: app('limiter'),
     *             maxAttempts: 10,  // Max 10 jobs
     *             decayMinutes: 1   // Per minute
     *         )
     *     ];
     * }
     */
    public function handle(JobInterface $job, callable $next): mixed
    {
        $key = $this->key ?? get_class($job);

        // Check if rate limit allows execution
        if ($this->limiter->attempt($key, $this->maxAttempts, $this->decayMinutes * 60)) {
            // Allowed - execute job
            return $next($job);
        }

        // Rate limit exceeded - calculate delay until next available slot
        $availableIn = $this->limiter->availableIn($key);

        throw new RateLimitExceededException(
            "Rate limit exceeded for job {$key}. Retry in {$availableIn} seconds.",
            $availableIn
        );
    }

    /**
     * Create middleware instance with fluent API.
     *
     * @param RateLimiterInterface $limiter
     * @param int $maxAttempts
     * @param int $decayMinutes
     * @return self
     */
    public static function make(
        RateLimiterInterface $limiter,
        int $maxAttempts = 60,
        int $decayMinutes = 1
    ): self {
        return new self($limiter, $maxAttempts, $decayMinutes);
    }

    /**
     * Set custom rate limit key.
     *
     * @param string $key
     * @return self
     */
    public function by(string $key): self
    {
        $this->key = $key;
        return $this;
    }
}
