<?php

declare(strict_types=1);

namespace Toporia\Framework\Queue;

use Toporia\Framework\Container\ContainerInterface;
use Toporia\Framework\Support\ColoredLogger;

/**
 * Queue Worker
 *
 * Processes jobs from the queue.
 * Handles job execution, retries, and failure management.
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
     * Start processing jobs from the queue
     *
     * @param string $queue Queue name
     * @return void
     */
    public function work(string $queue = 'default'): void
    {
        $this->logger->info("Queue worker started. Listening on queue: {$queue}");

        while (!$this->shouldQuit) {
            $job = $this->queue->pop($queue);

            if ($job === null) {
                // No job available, sleep (don't spam logs)
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
