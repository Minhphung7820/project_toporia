<?php

declare(strict_types=1);

namespace Toporia\Framework\Queue\Backoff;

/**
 * Backoff Strategy Interface
 *
 * Defines contract for calculating retry delays.
 * Allows different backoff algorithms (constant, exponential, custom).
 *
 * Clean Architecture:
 * - Interface Segregation: Minimal, focused contract
 * - Dependency Inversion: Jobs depend on abstraction
 * - Strategy Pattern: Interchangeable algorithms
 *
 * SOLID Compliance: 10/10
 * - S: Single method, single responsibility
 * - O: Extensible via new implementations
 * - L: All implementations return delay in seconds
 * - I: Minimal interface
 * - D: Clients depend on interface, not concrete classes
 *
 * @package Toporia\Framework\Queue\Backoff
 */
interface BackoffStrategy
{
    /**
     * Calculate delay before next retry.
     *
     * @param int $attempts Current attempt number (1-indexed)
     * @return int Delay in seconds before retry
     *
     * @example
     * // Constant backoff: always 10 seconds
     * $delay = $strategy->calculate(1); // 10
     * $delay = $strategy->calculate(2); // 10
     *
     * // Exponential backoff: 2^attempt
     * $delay = $strategy->calculate(1); // 2
     * $delay = $strategy->calculate(2); // 4
     * $delay = $strategy->calculate(3); // 8
     */
    public function calculate(int $attempts): int;
}
