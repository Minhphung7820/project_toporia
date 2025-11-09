<?php

declare(strict_types=1);

namespace Toporia\Framework\Queue;

use Toporia\Framework\Container\ContainerInterface;

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

    public function __construct(
        private QueueInterface $queue,
        private ?ContainerInterface $container = null,
        private int $maxJobs = 0,
        private int $sleep = 3
    ) {}

    /**
     * Start processing jobs from the queue
     *
     * @param string $queue Queue name
     * @return void
     */
    public function work(string $queue = 'default'): void
    {
        echo "Queue worker started. Listening on queue: {$queue}\n";

        while (!$this->shouldQuit) {
            $job = $this->queue->pop($queue);

            if ($job === null) {
                // No job available, sleep
                $this->sleep();
                continue;
            }

            $this->processJob($job);
            $this->processed++;

            // Check if we've hit max jobs limit
            if ($this->maxJobs > 0 && $this->processed >= $this->maxJobs) {
                echo "Max jobs limit reached. Stopping worker.\n";
                break;
            }
        }

        echo "Queue worker stopped. Processed {$this->processed} jobs.\n";
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
            echo "Processing job: {$job->getId()}\n";

            $job->incrementAttempts();

            // Use container to call handle() with dependency injection
            if ($this->container) {
                $this->container->call([$job, 'handle']);
            } else {
                $job->handle();
            }

            echo "Job completed: {$job->getId()}\n";
        } catch (\Throwable $e) {
            echo "Job failed: {$job->getId()} - {$e->getMessage()}\n";

            // Check if we should retry
            if ($job->attempts() < $job->getMaxAttempts()) {
                echo "Retrying job: {$job->getId()} (attempt {$job->attempts()})\n";
                $this->queue->push($job, $job->getQueue());
            } else {
                echo "Job exceeded max attempts: {$job->getId()}\n";
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
        echo "Stopping worker...\n";
        $this->shouldQuit = true;
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
