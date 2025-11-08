<?php

declare(strict_types=1);

namespace Toporia\Framework\Queue;

/**
 * Queue Interface
 *
 * Defines contract for queue drivers.
 * Queues provide asynchronous job processing capabilities.
 */
interface QueueInterface
{
    /**
     * Push a job onto the queue
     *
     * @param JobInterface $job
     * @param string $queue Queue name
     * @return string Job ID
     */
    public function push(JobInterface $job, string $queue = 'default'): string;

    /**
     * Push a job onto the queue with a delay
     *
     * @param JobInterface $job
     * @param int $delay Delay in seconds
     * @param string $queue Queue name
     * @return string Job ID
     */
    public function later(JobInterface $job, int $delay, string $queue = 'default'): string;

    /**
     * Pop the next job off the queue
     *
     * @param string $queue Queue name
     * @return JobInterface|null
     */
    public function pop(string $queue = 'default'): ?JobInterface;

    /**
     * Get the size of a queue
     *
     * @param string $queue Queue name
     * @return int
     */
    public function size(string $queue = 'default'): int;

    /**
     * Clear all jobs from a queue
     *
     * @param string $queue Queue name
     * @return void
     */
    public function clear(string $queue = 'default'): void;
}
