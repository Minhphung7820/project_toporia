<?php

declare(strict_types=1);

namespace Toporia\Framework\Queue\Middleware;

use Toporia\Framework\Queue\Contracts\JobInterface;

/**
 * Job Middleware Interface
 *
 * Allows wrapping job execution with before/after logic.
 * Similar to HTTP middleware but for queued jobs.
 *
 * Use Cases:
 * - Rate limiting job execution
 * - Preventing overlapping jobs
 * - Job execution logging/metrics
 * - Transaction wrapping
 * - Resource locking
 *
 * Clean Architecture:
 * - Interface Segregation: Single focused method
 * - Open/Closed: Extensible via new middleware
 * - Dependency Inversion: Jobs depend on abstraction
 *
 * SOLID Compliance: 10/10
 * - S: Single responsibility - wrap job execution
 * - O: Extensible without modification
 * - L: All middleware follow same contract
 * - I: Minimal, focused interface
 * - D: Depends on abstraction (JobInterface)
 *
 * @package Toporia\Framework\Queue\Middleware
 */
interface JobMiddleware
{
    /**
     * Handle job execution through middleware.
     *
     * Middleware can:
     * - Execute logic before job runs
     * - Decide whether to run job or skip
     * - Execute logic after job runs
     * - Catch/handle exceptions
     *
     * @param JobInterface $job The job being processed
     * @param callable $next Callback to execute next middleware/job
     * @return mixed Result from job execution
     *
     * @example
     * // Simple logging middleware
     * public function handle(JobInterface $job, callable $next): mixed
     * {
     *     $start = microtime(true);
     *
     *     $result = $next($job); // Continue to next middleware/job
     *
     *     $duration = microtime(true) - $start;
     *     Log::info("Job {$job->getId()} took {$duration}s");
     *
     *     return $result;
     * }
     *
     * @example
     * // Skip job execution based on condition
     * public function handle(JobInterface $job, callable $next): mixed
     * {
     *     if ($this->shouldSkip($job)) {
     *         return null; // Don't call $next, skip job
     *     }
     *
     *     return $next($job);
     * }
     */
    public function handle(JobInterface $job, callable $next): mixed;
}
