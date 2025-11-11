<?php

declare(strict_types=1);

namespace Toporia\Framework\Process\Contracts;

/**
 * Worker Interface
 *
 * Contract for workers that process jobs in separate processes.
 */
interface WorkerInterface
{
    /**
     * Process a single job.
     *
     * @param mixed $job
     * @return mixed Result
     */
    public function process(mixed $job): mixed;

    /**
     * Handle worker initialization (in child process).
     *
     * @return void
     */
    public function initialize(): void;

    /**
     * Handle worker shutdown (before process exits).
     *
     * @return void
     */
    public function shutdown(): void;

    /**
     * Handle errors during processing.
     *
     * @param \Throwable $e
     * @param mixed $job
     * @return void
     */
    public function handleError(\Throwable $e, mixed $job): void;
}
