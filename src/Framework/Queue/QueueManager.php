<?php

declare(strict_types=1);

namespace Toporia\Framework\Queue;

use Toporia\Framework\Database\Connection;

/**
 * Queue Manager
 *
 * Manages multiple queue drivers and provides a unified interface.
 * Factory for creating queue driver instances.
 */
final class QueueManager
{
    private array $drivers = [];
    private ?string $defaultDriver = null;
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->defaultDriver = $config['default'] ?? 'sync';
    }

    /**
     * Get a queue driver instance
     *
     * @param string|null $driver
     * @return QueueInterface
     */
    public function driver(?string $driver = null): QueueInterface
    {
        $driver = $driver ?? $this->defaultDriver;

        if (!isset($this->drivers[$driver])) {
            $this->drivers[$driver] = $this->createDriver($driver);
        }

        return $this->drivers[$driver];
    }

    /**
     * Create a queue driver instance
     *
     * @param string $driver
     * @return QueueInterface
     */
    private function createDriver(string $driver): QueueInterface
    {
        $config = $this->config['connections'][$driver] ?? [];

        return match ($config['driver'] ?? $driver) {
            'sync' => new SyncQueue(),
            'database' => $this->createDatabaseQueue($config),
            'redis' => RedisQueue::fromConfig($config),
            default => throw new \InvalidArgumentException("Unsupported queue driver: {$driver}"),
        };
    }

    /**
     * Create database queue instance
     *
     * @param array $config
     * @return DatabaseQueue
     */
    private function createDatabaseQueue(array $config): DatabaseQueue
    {
        // This would typically get the Connection from the container
        // For now, we'll assume it's passed in the config
        if (!isset($config['connection'])) {
            throw new \InvalidArgumentException('Database queue requires connection in config');
        }

        return new DatabaseQueue($config['connection']);
    }

    /**
     * Push a job onto the default queue
     *
     * @param JobInterface $job
     * @param string $queue
     * @return string
     */
    public function push(JobInterface $job, string $queue = 'default'): string
    {
        return $this->driver()->push($job, $queue);
    }

    /**
     * Push a job with a delay
     *
     * @param JobInterface $job
     * @param int $delay
     * @param string $queue
     * @return string
     */
    public function later(JobInterface $job, int $delay, string $queue = 'default'): string
    {
        return $this->driver()->later($job, $delay, $queue);
    }

    /**
     * Get all configured connection names
     *
     * @return array
     */
    public function getConnections(): array
    {
        return array_keys($this->config['connections'] ?? []);
    }
}
