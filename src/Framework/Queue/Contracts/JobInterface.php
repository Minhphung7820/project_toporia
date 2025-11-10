<?php

declare(strict_types=1);

namespace Toporia\Framework\Queue\Contracts;

/**
 * Job Interface
 *
 * Defines contract for queued jobs.
 * Jobs encapsulate a unit of work to be executed asynchronously.
 *
 * Note: The handle() method is intentionally NOT defined in this interface
 * to allow child classes to use different signatures for dependency injection.
 * The Worker uses the container to call handle() with automatic DI.
 */
interface JobInterface
{
    // Note: handle() method signature varies per implementation for DI support

    /**
     * Get the job identifier
     *
     * @return string
     */
    public function getId(): string;

    /**
     * Get the queue name
     *
     * @return string
     */
    public function getQueue(): string;

    /**
     * Get the number of times the job has been attempted
     *
     * @return int
     */
    public function attempts(): int;

    /**
     * Get the maximum number of attempts
     *
     * @return int
     */
    public function getMaxAttempts(): int;

    /**
     * Increment the attempt counter
     *
     * @return void
     */
    public function incrementAttempts(): void;

    /**
     * Handle a job failure
     *
     * @param \Throwable $exception
     * @return void
     */
    public function failed(\Throwable $exception): void;
}
