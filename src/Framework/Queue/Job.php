<?php

declare(strict_types=1);

namespace Toporia\Framework\Queue;

/**
 * Abstract Job
 *
 * Base class for queued jobs.
 * Provides common functionality for job management.
 */
abstract class Job implements JobInterface
{
    private string $id;
    private string $queue = 'default';
    private int $attempts = 0;
    private int $maxAttempts = 3;

    public function __construct()
    {
        $this->id = uniqid('job_', true);
    }

    /**
     * Handle the job execution
     * Override this method in concrete job classes
     *
     * @return void
     */
    abstract public function handle(): void;

    public function getId(): string
    {
        return $this->id;
    }

    public function getQueue(): string
    {
        return $this->queue;
    }

    public function attempts(): int
    {
        return $this->attempts;
    }

    public function getMaxAttempts(): int
    {
        return $this->maxAttempts;
    }

    public function incrementAttempts(): void
    {
        $this->attempts++;
    }

    /**
     * Handle job failure
     * Override to implement custom failure handling
     *
     * @param \Throwable $exception
     * @return void
     */
    public function failed(\Throwable $exception): void
    {
        // Log the failure
        error_log(sprintf(
            'Job %s failed: %s',
            $this->getId(),
            $exception->getMessage()
        ));
    }

    /**
     * Set the queue name
     *
     * @param string $queue
     * @return self
     */
    public function onQueue(string $queue): self
    {
        $this->queue = $queue;
        return $this;
    }

    /**
     * Set the maximum number of attempts
     *
     * @param int $maxAttempts
     * @return self
     */
    public function tries(int $maxAttempts): self
    {
        $this->maxAttempts = $maxAttempts;
        return $this;
    }

    /**
     * Delay the job execution
     *
     * @param int $seconds
     * @return self
     */
    public function delay(int $seconds): self
    {
        // This would be handled by the queue driver
        return $this;
    }
}
