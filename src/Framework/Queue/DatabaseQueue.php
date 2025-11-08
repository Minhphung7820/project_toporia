<?php

declare(strict_types=1);

namespace Toporia\Framework\Queue;

use Toporia\Framework\Database\Connection;
use Toporia\Framework\Database\Query\QueryBuilder;

/**
 * Database Queue Driver
 *
 * Stores jobs in a database table.
 * Requires a 'jobs' table with proper schema.
 */
final class DatabaseQueue implements QueueInterface
{
    private QueryBuilder $query;

    public function __construct(Connection $connection)
    {
        $this->query = new QueryBuilder($connection);
    }

    public function push(JobInterface $job, string $queue = 'default'): string
    {
        $this->query->table('jobs')->insert([
            'id' => $job->getId(),
            'queue' => $queue,
            'payload' => serialize($job),
            'attempts' => 0,
            'available_at' => time(),
            'created_at' => time(),
        ]);

        return $job->getId();
    }

    public function later(JobInterface $job, int $delay, string $queue = 'default'): string
    {
        $this->query->table('jobs')->insert([
            'id' => $job->getId(),
            'queue' => $queue,
            'payload' => serialize($job),
            'attempts' => 0,
            'available_at' => time() + $delay,
            'created_at' => time(),
        ]);

        return $job->getId();
    }

    public function pop(string $queue = 'default'): ?JobInterface
    {
        // Get the next available job
        $record = $this->query->table('jobs')
            ->where('queue', $queue)
            ->where('available_at', '<=', time())
            ->orderBy('id', 'ASC')
            ->first();

        if ($record === null) {
            return null;
        }

        // Delete the job from the queue
        $this->query->table('jobs')
            ->where('id', $record['id'])
            ->delete();

        // Unserialize and return the job
        return unserialize($record['payload']);
    }

    public function size(string $queue = 'default'): int
    {
        return $this->query->table('jobs')
            ->where('queue', $queue)
            ->count();
    }

    public function clear(string $queue = 'default'): void
    {
        $this->query->table('jobs')
            ->where('queue', $queue)
            ->delete();
    }

    /**
     * Get failed jobs
     *
     * @param int $limit
     * @return array
     */
    public function getFailedJobs(int $limit = 100): array
    {
        return $this->query->table('failed_jobs')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Store a failed job
     *
     * @param JobInterface $job
     * @param \Throwable $exception
     * @return void
     */
    public function storeFailed(JobInterface $job, \Throwable $exception): void
    {
        $this->query->table('failed_jobs')->insert([
            'id' => uniqid('failed_', true),
            'queue' => $job->getQueue(),
            'payload' => serialize($job),
            'exception' => $exception->getMessage(),
            'failed_at' => time(),
        ]);
    }
}
