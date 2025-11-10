<?php

declare(strict_types=1);

namespace Toporia\Framework\Storage;

use Toporia\Framework\Storage\Contracts\FilesystemInterface;

/**
 * Storage Manager
 *
 * Multi-driver storage manager (like Laravel Storage facade).
 *
 * Supports:
 * - Local filesystem
 * - S3 (AWS/DigitalOcean/Minio)
 * - FTP (future)
 *
 * Performance: O(1) driver lookup with caching
 * SOLID: Single Responsibility - manages multiple storage drivers
 */
final class StorageManager
{
    /** @var array<string, FilesystemInterface> */
    private array $disks = [];

    public function __construct(
        private readonly array $config,
        private readonly string $defaultDisk = 'local'
    ) {
    }

    /**
     * Get filesystem disk instance.
     *
     * @param string|null $name Disk name (uses default if null)
     * @return FilesystemInterface
     */
    public function disk(?string $name = null): FilesystemInterface
    {
        $name = $name ?? $this->defaultDisk;

        // Return cached disk
        if (isset($this->disks[$name])) {
            return $this->disks[$name];
        }

        // Create and cache new disk
        $this->disks[$name] = $this->createDisk($name);
        return $this->disks[$name];
    }

    /**
     * Create filesystem disk from config.
     *
     * @param string $name Disk name
     * @return FilesystemInterface
     * @throws \RuntimeException If disk config not found
     */
    private function createDisk(string $name): FilesystemInterface
    {
        if (!isset($this->config['disks'][$name])) {
            throw new \RuntimeException("Disk [{$name}] not configured.");
        }

        $config = $this->config['disks'][$name];
        $driver = $config['driver'] ?? 'local';

        return match ($driver) {
            'local' => $this->createLocalDisk($config),
            's3' => $this->createS3Disk($config),
            default => throw new \RuntimeException("Unsupported driver [{$driver}]"),
        };
    }

    /**
     * Create local filesystem disk.
     *
     * @param array $config Disk configuration
     * @return LocalFilesystem
     */
    private function createLocalDisk(array $config): LocalFilesystem
    {
        return new LocalFilesystem(
            root: $config['root'],
            baseUrl: $config['url'] ?? ''
        );
    }

    /**
     * Create S3 filesystem disk.
     *
     * @param array $config Disk configuration
     * @return S3Filesystem
     */
    private function createS3Disk(array $config): S3Filesystem
    {
        return new S3Filesystem(
            bucket: $config['bucket'],
            region: $config['region'] ?? 'us-east-1',
            key: $config['key'],
            secret: $config['secret'],
            baseUrl: $config['url'] ?? '',
            endpoint: $config['endpoint'] ?? ''
        );
    }

    /**
     * Proxy method calls to default disk.
     *
     * Allows: $storage->put() instead of $storage->disk()->put()
     *
     * @param string $method Method name
     * @param array $parameters Method parameters
     * @return mixed
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->disk()->$method(...$parameters);
    }
}
