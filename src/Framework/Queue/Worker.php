<?php

declare(strict_types=1);

namespace Toporia\Framework\Queue;

use Toporia\Framework\Container\ContainerInterface;
use Toporia\Framework\Support\ColoredLogger;
use Toporia\Framework\Queue\Contracts\{JobInterface, QueueInterface};
use Toporia\Framework\Queue\Exceptions\{RateLimitExceededException, JobAlreadyRunningException};

/**
 * Queue Worker
 *
 * Processes jobs from the queue with multi-queue support.
 * Handles job execution, retries, and failure management.
 *
 * Multi-Queue Features:
 * - Supports single or multiple queues with priority order
 * - First queue in array has highest priority
 * - Efficient round-robin polling across queues
 *
 * Performance:
 * - O(Q) per iteration where Q = number of queues
 * - Graceful shutdown support (waits for current job)
 * - Configurable sleep between iterations
 */
final class Worker
{
    private bool $shouldQuit = false;
    private int $processed = 0;
    private ColoredLogger $logger;

    public function __construct(
        private QueueInterface $queue,
        private ?ContainerInterface $container = null,
        private int $maxJobs = 0,
        private int $sleep = 1,
        ?string $timezone = null
    ) {
        // Get timezone from config or use default
        $timezone = $timezone ?? $this->getTimezone();
        $this->logger = new ColoredLogger($timezone);
    }

    /**
     * Start processing jobs from the queue(s).
     *
     * Supports both single queue (string) and multiple queues (array) with priority.
     * When multiple queues are provided, processes in priority order (first = highest).
     *
     * Performance: O(Q) per iteration where Q = number of queues
     *
     * @param string|array<string> $queues Queue name(s) - string or array
     * @return void
     */
    public function work(string|array $queues = 'default'): void
    {
        // Normalize to array
        $queueArray = is_array($queues) ? $queues : [$queues];

        $queueNames = implode(',', $queueArray);
        $this->logger->info("Queue worker started. Listening on queue: {$queueNames}");

        while (!$this->shouldQuit) {
            $job = $this->getNextJob($queueArray);

            if ($job === null) {
                // No job available on any queue, sleep (don't spam logs)
                $this->sleep();
                continue;
            }

            $this->processJob($job);
            $this->processed++;

            // Check if we've hit max jobs limit
            if ($this->maxJobs > 0 && $this->processed >= $this->maxJobs) {
                $this->logger->warning("Max jobs limit reached. Stopping worker.");
                break;
            }
        }

        $this->logger->info("Queue worker stopped. Processed {$this->processed} jobs.");
    }

    /**
     * Get next job from queues in priority order.
     *
     * Checks queues in order and returns first available job.
     * This ensures high-priority queues are processed first.
     *
     * Performance: O(Q) where Q = number of queues
     *
     * @param array<string> $queues Queue names in priority order
     * @return JobInterface|null
     */
    private function getNextJob(array $queues): ?JobInterface
    {
        // Try each queue in priority order
        foreach ($queues as $queueName) {
            $job = $this->queue->pop($queueName);

            if ($job !== null) {
                return $job; // Found a job, return immediately
            }
        }

        return null; // No jobs available on any queue
    }

    /**
     * Process a single job with middleware support and backoff retry.
     *
     * Execution flow:
     * 1. Increment attempts
     * 2. Execute middleware pipeline
     * 3. Execute job handle() method
     * 4. On success: log completion
     * 5. On failure: retry with backoff or mark as failed
     *
     * Performance: O(M + H) where M = middleware count, H = job execution time
     *
     * @param JobInterface $job
     * @return void
     */
    private function processJob(JobInterface $job): void
    {
        try {
            $attemptNumber = $job->attempts() + 1;
            $this->logger->info("Processing job: {$job->getId()} (attempt {$attemptNumber})");

            $job->incrementAttempts();

            // Execute job through middleware pipeline
            $this->executeJobThroughMiddleware($job);

            $this->logger->success("Job completed: {$job->getId()}");
        } catch (RateLimitExceededException $e) {
            // Rate limit exceeded - release back to queue with delay
            $retryAfter = $e->getRetryAfter();
            $this->logger->warning("Job rate limited: {$job->getId()}. Retrying in {$retryAfter}s");
            $this->queue->later($job, $retryAfter, $job->getQueue());
        } catch (JobAlreadyRunningException $e) {
            // Job already running - release back to queue with delay
            $this->logger->warning("Job already running: {$job->getId()}. Retrying in 60s");
            $this->queue->later($job, 60, $job->getQueue());
        } catch (\Throwable $e) {
            $this->logger->error("Job failed: {$job->getId()} - {$e->getMessage()}");

            // Check if we should retry
            if ($job->attempts() < $job->getMaxAttempts()) {
                // Calculate backoff delay
                $delay = $job->getBackoffDelay();

                if ($delay > 0) {
                    $this->logger->warning("Retrying job: {$job->getId()} in {$delay}s (attempt {$job->attempts()})");
                    $this->queue->later($job, $delay, $job->getQueue());
                } else {
                    $this->logger->warning("Retrying job: {$job->getId()} immediately (attempt {$job->attempts()})");
                    $this->queue->push($job, $job->getQueue());
                }
            } else {
                $this->logger->error("Job exceeded max attempts: {$job->getId()}");
                $job->failed($e);

                // Store in failed jobs table if using DatabaseQueue
                if ($this->queue instanceof DatabaseQueue) {
                    $this->queue->storeFailed($job, $e);
                }
            }
        }
    }

    /**
     * Execute job through middleware pipeline.
     *
     * Builds middleware pipeline and executes job with dependency injection.
     *
     * Performance: O(M) where M = number of middleware
     *
     * @param JobInterface $job
     * @return mixed
     */
    private function executeJobThroughMiddleware(JobInterface $job): mixed
    {
        // Get job middleware
        $middleware = $job->middleware();

        if (empty($middleware)) {
            // No middleware, execute directly
            return $this->executeJob($job);
        }

        // Build middleware pipeline (Laravel-style)
        $pipeline = array_reduce(
            array_reverse($middleware),
            function ($next, $middleware) {
                return function ($job) use ($middleware, $next) {
                    return $middleware->handle($job, $next);
                };
            },
            function ($job) {
                return $this->executeJob($job);
            }
        );

        // Execute pipeline
        return $pipeline($job);
    }

    /**
     * Execute job handle() method with dependency injection.
     *
     * Final step in middleware pipeline.
     *
     * @param JobInterface $job
     * @return mixed
     */
    private function executeJob(JobInterface $job): mixed
    {
        // Use container to call handle() with dependency injection
        if ($this->container) {
            return $this->container->call([$job, 'handle']);
        }

        return $job->handle();
    }

    /**
     * Sleep for the configured duration
     *
     * @return void
     */
    private function sleep(): void
    {
        sleep($this->sleep);
    }

    /**
     * Stop the worker gracefully
     *
     * @return void
     */
    public function stop(): void
    {
        $this->logger->warning("Stopping worker...");
        $this->shouldQuit = true;
    }

    /**
     * Get timezone from config or container
     *
     * @return string
     */
    private function getTimezone(): string
    {
        if ($this->container && $this->container->has('config')) {
            $config = $this->container->get('config');
            return $config->get('app.timezone', 'UTC');
        }

        return 'UTC';
    }

    /**
     * Get the number of processed jobs
     *
     * @return int
     */
    public function getProcessedCount(): int
    {
        return $this->processed;
    }

    /**
     * Get the queue instance
     *
     * @return QueueInterface
     */
    public function getQueue(): QueueInterface
    {
        return $this->queue;
    }
}
