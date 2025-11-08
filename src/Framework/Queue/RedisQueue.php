<?php

declare(strict_types=1);

namespace Toporia\Framework\Queue;

use Redis;

/**
 * Redis Queue Driver
 *
 * High-performance queue using Redis lists.
 * Supports delayed jobs using sorted sets.
 */
final class RedisQueue implements QueueInterface
{
    private string $prefix = 'queue:';

    public function __construct(
        private Redis $redis
    ) {}

    public function push(JobInterface $job, string $queue = 'default'): string
    {
        $this->redis->rPush(
            $this->getQueueKey($queue),
            serialize($job)
        );

        return $job->getId();
    }

    public function later(JobInterface $job, int $delay, string $queue = 'default'): string
    {
        $availableAt = time() + $delay;

        $this->redis->zAdd(
            $this->getDelayedKey($queue),
            $availableAt,
            serialize($job)
        );

        return $job->getId();
    }

    public function pop(string $queue = 'default'): ?JobInterface
    {
        // First, migrate delayed jobs that are now available
        $this->migrateDelayedJobs($queue);

        // Pop from the queue
        $payload = $this->redis->lPop($this->getQueueKey($queue));

        if ($payload === false) {
            return null;
        }

        return unserialize($payload);
    }

    public function size(string $queue = 'default'): int
    {
        $queueSize = $this->redis->lLen($this->getQueueKey($queue));
        $delayedSize = $this->redis->zCard($this->getDelayedKey($queue));

        return $queueSize + $delayedSize;
    }

    public function clear(string $queue = 'default'): void
    {
        $this->redis->del($this->getQueueKey($queue));
        $this->redis->del($this->getDelayedKey($queue));
    }

    /**
     * Migrate delayed jobs that are now available
     *
     * @param string $queue
     * @return void
     */
    private function migrateDelayedJobs(string $queue): void
    {
        $now = time();
        $delayedKey = $this->getDelayedKey($queue);

        // Get jobs that are ready to be processed
        $jobs = $this->redis->zRangeByScore($delayedKey, '-inf', (string)$now);

        foreach ($jobs as $job) {
            // Move to the main queue
            $this->redis->rPush($this->getQueueKey($queue), $job);

            // Remove from delayed set
            $this->redis->zRem($delayedKey, $job);
        }
    }

    /**
     * Get the Redis key for a queue
     *
     * @param string $queue
     * @return string
     */
    private function getQueueKey(string $queue): string
    {
        return $this->prefix . $queue;
    }

    /**
     * Get the Redis key for delayed jobs
     *
     * @param string $queue
     * @return string
     */
    private function getDelayedKey(string $queue): string
    {
        return $this->prefix . $queue . ':delayed';
    }

    /**
     * Create from config
     *
     * @param array $config
     * @return self
     */
    public static function fromConfig(array $config): self
    {
        $redis = new Redis();
        $redis->connect(
            $config['host'] ?? '127.0.0.1',
            $config['port'] ?? 6379
        );

        if (!empty($config['password'])) {
            $redis->auth($config['password']);
        }

        if (isset($config['database'])) {
            $redis->select($config['database']);
        }

        return new self($redis);
    }
}
