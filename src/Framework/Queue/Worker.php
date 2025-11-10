<?php

declare(strict_types=1);

namespace Toporia\Framework\Queue;

use Toporia\Framework\Container\ContainerInterface;
use Toporia\Framework\Support\ColoredLogger;
use Toporia\Framework\Queue\Contracts\{JobInterface, QueueInterface};

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
     * Process a single job
     *
     * @param JobInterface $job
     * @return void
     */
    private function processJob(JobInterface $job): void
    {
        try {
            $this->logger->info("Processing job: {$job->getId()}");

            $job->incrementAttempts();

            // Use container to call handle() with dependency injection
            if ($this->container) {
                $this->container->call([$job, 'handle']);
            } else {
                $job->handle();
            }

            $this->logger->success("Job completed: {$job->getId()}");
        } catch (\Throwable $e) {
            $this->logger->error("Job failed: {$job->getId()} - {$e->getMessage()}");

            // Check if we should retry
            if ($job->attempts() < $job->getMaxAttempts()) {
                $this->logger->warning("Retrying job: {$job->getId()} (attempt {$job->attempts()})");
                $this->queue->push($job, $job->getQueue());
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
