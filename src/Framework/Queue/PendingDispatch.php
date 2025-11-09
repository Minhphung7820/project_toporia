<?php

declare(strict_types=1);

namespace Toporia\Framework\Queue;

use Toporia\Framework\Queue\Contracts\Dispatcher;

/**
 * Pending Job Dispatch
 *
 * Provides fluent API for configuring job dispatch.
 * Inspired by Laravel's PendingDispatch.
 *
 * Usage:
 * ```php
 * dispatch(new SendEmailJob($to, $subject, $body))
 *     ->onQueue('emails')
 *     ->delay(60)
 *     ->afterCommit();
 * ```
 *
 * SOLID Principles:
 * - Single Responsibility: Only configures and dispatches jobs
 * - Open/Closed: Extend without modifying
 * - Dependency Inversion: Depends on Dispatcher interface
 *
 * @package Toporia\Framework\Queue
 */
final class PendingDispatch
{
    private ?string $queue = null;
    private ?int $delay = null;
    private bool $afterCommit = false;
    private bool $dispatched = false;

    public function __construct(
        private readonly object $job,
        private readonly Dispatcher $dispatcher
    ) {
    }

    /**
     * Set the queue for the job.
     *
     * @param string $queue Queue name
     * @return $this
     */
    public function onQueue(string $queue): self
    {
        $this->queue = $queue;
        return $this;
    }

    /**
     * Set delay for the job.
     *
     * @param int $seconds Delay in seconds
     * @return $this
     */
    public function delay(int $seconds): self
    {
        $this->delay = $seconds;
        return $this;
    }

    /**
     * Dispatch job after database commit.
     *
     * @return $this
     */
    public function afterCommit(): self
    {
        $this->afterCommit = true;
        return $this;
    }

    /**
     * Dispatch the job immediately (sync).
     *
     * @return mixed
     */
    public function dispatchSync(): mixed
    {
        return $this->dispatcher->dispatchSync($this->job);
    }

    /**
     * Explicitly dispatch the job now.
     *
     * Call this to dispatch immediately instead of waiting for destructor.
     * Prevents duplicate dispatch on destruct.
     *
     * @return mixed Job ID
     */
    public function dispatch(): mixed
    {
        if ($this->dispatched) {
            return null; // Already dispatched
        }

        $this->dispatched = true;

        if ($this->delay !== null) {
            return $this->dispatcher->dispatchAfter($this->job, $this->delay, $this->queue);
        }

        return $this->dispatcher->dispatchToQueue($this->job, $this->queue);
    }

    /**
     * Destructor - automatically dispatches job when object is destroyed.
     *
     * Laravel-style implicit dispatch:
     * ```php
     * dispatch(new SendEmailJob(...));  // Auto-dispatches, no ->dispatch() needed!
     * ```
     *
     * Fluent API still works:
     * ```php
     * dispatch(new Job())->onQueue('emails')->delay(60);  // Auto-dispatches with config
     * ```
     *
     * Performance: O(1) - destructor is called when PendingDispatch goes out of scope.
     */
    public function __destruct()
    {
        // Prevent double dispatch (if ->dispatch() was called explicitly)
        if ($this->dispatched) {
            return;
        }

        // Mark as dispatched to prevent duplicate dispatch
        $this->dispatched = true;

        // Dispatch with configured options
        if ($this->delay !== null) {
            $this->dispatcher->dispatchAfter($this->job, $this->delay, $this->queue);
        } else {
            $this->dispatcher->dispatchToQueue($this->job, $this->queue);
        }
    }
}
