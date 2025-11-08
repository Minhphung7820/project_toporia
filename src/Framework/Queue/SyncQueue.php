<?php

declare(strict_types=1);

namespace Toporia\Framework\Queue;

/**
 * Synchronous Queue Driver
 *
 * Executes jobs immediately without queueing.
 * Useful for testing and development.
 */
final class SyncQueue implements QueueInterface
{
    public function push(JobInterface $job, string $queue = 'default'): string
    {
        $this->executeJob($job);
        return $job->getId();
    }

    public function later(JobInterface $job, int $delay, string $queue = 'default'): string
    {
        // In sync mode, ignore delay and execute immediately
        return $this->push($job, $queue);
    }

    public function pop(string $queue = 'default'): ?JobInterface
    {
        // Sync queue doesn't store jobs
        return null;
    }

    public function size(string $queue = 'default'): int
    {
        return 0;
    }

    public function clear(string $queue = 'default'): void
    {
        // Nothing to clear
    }

    /**
     * Execute a job
     *
     * @param JobInterface $job
     * @return void
     */
    private function executeJob(JobInterface $job): void
    {
        try {
            $job->handle();
        } catch (\Throwable $e) {
            $job->failed($e);
            throw $e;
        }
    }
}
