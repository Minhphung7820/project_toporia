<?php

declare(strict_types=1);

namespace Toporia\Framework\Schedule;

use Toporia\Framework\Cache\CacheInterface;

/**
 * Cache-based Mutex
 *
 * Implements mutex using cache backend (file, Redis, etc.)
 * for preventing task overlaps across multiple servers.
 */
final class CacheMutex implements MutexInterface
{
    public function __construct(
        private CacheInterface $cache
    ) {}

    /**
     * {@inheritdoc}
     */
    public function create(string $name, int $expiresAfter = 1440): bool
    {
        $key = $this->getKey($name);

        // Check if lock already exists
        if ($this->cache->has($key)) {
            return false;
        }

        // Create lock with expiration
        $this->cache->set($key, time(), $expiresAfter * 60); // Convert minutes to seconds

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function exists(string $name): bool
    {
        return $this->cache->has($this->getKey($name));
    }

    /**
     * {@inheritdoc}
     */
    public function forget(string $name): bool
    {
        return $this->cache->delete($this->getKey($name));
    }

    /**
     * Get cache key for mutex
     *
     * @param string $name
     * @return string
     */
    private function getKey(string $name): string
    {
        return "mutex:{$name}";
    }
}
