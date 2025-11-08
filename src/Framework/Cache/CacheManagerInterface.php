<?php

declare(strict_types=1);

namespace Toporia\Framework\Cache;

/**
 * Cache Manager Interface
 *
 * Contract for multi-driver cache management.
 */
interface CacheManagerInterface extends CacheInterface
{
    /**
     * Get a cache driver instance
     *
     * @param string|null $driver Driver name (null = default)
     * @return CacheInterface
     */
    public function driver(?string $driver = null): CacheInterface;

    /**
     * Get default driver name
     *
     * @return string
     */
    public function getDefaultDriver(): string;
}
