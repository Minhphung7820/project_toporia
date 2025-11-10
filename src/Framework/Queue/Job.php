<?php

declare(strict_types=1);

namespace Toporia\Framework\Queue;

use Toporia\Framework\Queue\Contracts\JobInterface;

/**
 * Abstract Job
 *
 * Base class for queued jobs.
 * Provides common functionality for job management.
 *
 * Note: The handle() method signature can vary in child classes to accept
 * dependencies via type-hinted parameters. The Worker uses the container
 * to automatically inject dependencies.
 */
abstract class Job implements JobInterface
{
    protected string $id;
    protected string $queue = 'default';
    protected int $attempts = 0;
    protected int $maxAttempts = 3;

    public function __construct()
    {
        $this->id = uniqid('job_', true);
    }

    /**
     * Handle the job execution
     *
     * This method must be implemented in concrete job classes.
     * The signature can vary to accept dependencies via type-hinted parameters.
     * The Worker will use the container to inject dependencies automatically.
     *
     * Examples:
     *   public function handle(): void { ... }
     *   public function handle(MailerInterface $mailer): void { ... }
     *   public function handle(Repository $repo, Logger $logger): void { ... }
     *
     * Note: PHP doesn't support covariant method signatures in abstract classes,
     * so we can't enforce this signature. Child classes MUST implement handle().
     */

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

    /**
     * Dispatch the job to the queue (Laravel-style static dispatch).
     *
     * Usage:
     * ```php
     * // Simple dispatch
     * SendEmailJob::dispatch($to, $subject, $message);
     *
     * // With fluent API
     * SendEmailJob::dispatch($to, $subject, $message)
     *     ->onQueue('emails')
     *     ->delay(60);
     * ```
     *
     * Performance: O(1) - Creates job instance and returns PendingDispatch
     * SOLID: Single Responsibility - each job knows how to dispatch itself
     *
     * @param mixed ...$args Constructor arguments
     * @return PendingDispatch
     */
    public static function dispatch(...$args): PendingDispatch
    {
        if (!function_exists('app') || !app()->has('dispatcher')) {
            throw new \RuntimeException('Job dispatcher not available in container. Register JobDispatcher in QueueServiceProvider.');
        }

        // Create job instance with constructor arguments
        $job = new static(...$args);

        // Return PendingDispatch for fluent API (auto-dispatches on destruct)
        $dispatcher = app('dispatcher');
        return new PendingDispatch($job, $dispatcher);
    }

    /**
     * Dispatch the job synchronously (execute immediately).
     *
     * Usage:
     * ```php
     * $result = SendEmailJob::dispatchSync($to, $subject, $message);
     * ```
     *
     * Performance: O(N) where N = job execution time (blocking)
     *
     * @param mixed ...$args Constructor arguments
     * @return mixed Job result
     */
    public static function dispatchSync(...$args): mixed
    {
        if (!function_exists('app') || !app()->has('dispatcher')) {
            throw new \RuntimeException('Job dispatcher not available in container.');
        }

        $job = new static(...$args);
        return app('dispatcher')->dispatchSync($job);
    }

    /**
     * Dispatch the job after a delay.
     *
     * Usage:
     * ```php
     * SendEmailJob::dispatchAfter(60, $to, $subject, $message); // 60 seconds delay
     * ```
     *
     * Performance: O(1) - Queues job with delayed execution
     *
     * @param int $delay Delay in seconds
     * @param mixed ...$args Constructor arguments
     * @return PendingDispatch
     */
    public static function dispatchAfter(int $delay, ...$args): PendingDispatch
    {
        return static::dispatch(...$args)->delay($delay);
    }
}
