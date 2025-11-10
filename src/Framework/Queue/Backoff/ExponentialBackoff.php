<?php

declare(strict_types=1);

namespace Toporia\Framework\Queue\Backoff;

/**
 * Exponential Backoff Strategy
 *
 * Increases delay exponentially with each retry attempt.
 * Industry-standard backoff algorithm for distributed systems.
 *
 * Formula: delay = base^attempt (capped at max)
 *
 * Performance: O(1) - pow() is constant time
 *
 * Use Cases:
 * - External API calls (give service time to recover)
 * - Distributed system retries
 * - Network failures
 * - Database connection retries
 *
 * Benefits:
 * - Reduces load on failing services
 * - Gives systems time to recover
 * - Industry best practice
 *
 * @package Toporia\Framework\Queue\Backoff
 */
final class ExponentialBackoff implements BackoffStrategy
{
    /**
     * @param int $base Base delay in seconds (default: 2)
     * @param int $max Maximum delay cap in seconds (default: 300 = 5 minutes)
     */
    public function __construct(
        private int $base = 2,
        private int $max = 300
    ) {}

    /**
     * {@inheritdoc}
     *
     * Calculate exponential delay with max cap.
     *
     * Performance: O(1) - pow() is constant time
     *
     * @example
     * $backoff = new ExponentialBackoff(base: 2, max: 300);
     * $backoff->calculate(1); // 2 seconds (2^1)
     * $backoff->calculate(2); // 4 seconds (2^2)
     * $backoff->calculate(3); // 8 seconds (2^3)
     * $backoff->calculate(4); // 16 seconds (2^4)
     * $backoff->calculate(10); // 300 seconds (capped at max)
     *
     * @param int $attempts Current attempt number (1-indexed)
     * @return int Delay in seconds
     */
    public function calculate(int $attempts): int
    {
        // Calculate: base^attempts
        $delay = (int) pow($this->base, $attempts);

        // Cap at maximum to prevent infinite delays
        return min($delay, $this->max);
    }
}
