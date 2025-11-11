<?php

declare(strict_types=1);

namespace Toporia\Framework\Process;

use Toporia\Framework\Process\Contracts\WorkerInterface;

/**
 * Abstract Worker
 *
 * Base class for implementing workers that process jobs in parallel.
 * Provides default implementations for lifecycle hooks.
 *
 * Example:
 * ```php
 * class ImageProcessor extends AbstractWorker
 * {
 *     public function process(mixed $job): mixed
 *     {
 *         // Process image file
 *         return $this->resizeImage($job['path']);
 *     }
 * }
 *
 * $pool = new ProcessPool(workers: 4, worker: new ImageProcessor());
 * $results = $pool->process($images);
 * ```
 */
abstract class AbstractWorker implements WorkerInterface
{
    /**
     * Process a single job.
     *
     * Override this method with your processing logic.
     *
     * @param mixed $job
     * @return mixed
     */
    abstract public function process(mixed $job): mixed;

    /**
     * Initialize worker (called once when process starts).
     *
     * Override to setup resources (database connections, file handles, etc.)
     *
     * @return void
     */
    public function initialize(): void
    {
        // Default: no initialization
    }

    /**
     * Shutdown worker (called before process exits).
     *
     * Override to cleanup resources.
     *
     * @return void
     */
    public function shutdown(): void
    {
        // Default: no cleanup
    }

    /**
     * Handle errors during processing.
     *
     * Override to customize error handling (logging, retries, etc.)
     *
     * @param \Throwable $e
     * @param mixed $job
     * @return void
     */
    public function handleError(\Throwable $e, mixed $job): void
    {
        // Default: log to error_log
        error_log(sprintf(
            "Worker error: %s\nJob: %s\nTrace: %s",
            $e->getMessage(),
            json_encode($job),
            $e->getTraceAsString()
        ));
    }
}
