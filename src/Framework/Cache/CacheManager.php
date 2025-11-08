<?php

declare(strict_types=1);

namespace Toporia\Framework\Cache;

/**
 * Cache Manager
 *
 * Manages multiple cache drivers and provides a unified interface.
 * Supports driver switching and fallback mechanisms.
 */
final class CacheManager implements CacheManagerInterface
{
    private array $drivers = [];
    private ?string $defaultDriver = null;
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->defaultDriver = $config['default'] ?? 'file';
    }

    /**
     * Get a cache driver instance
     *
     * @param string|null $driver Driver name (null = default)
     * @return CacheInterface
     */
    public function driver(?string $driver = null): CacheInterface
    {
        $driver = $driver ?? $this->defaultDriver;

        if (!isset($this->drivers[$driver])) {
            $this->drivers[$driver] = $this->createDriver($driver);
        }

        return $this->drivers[$driver];
    }

    /**
     * Create a cache driver instance
     *
     * @param string $driver
     * @return CacheInterface
     */
    private function createDriver(string $driver): CacheInterface
    {
        $config = $this->config['stores'][$driver] ?? [];

        return match ($config['driver'] ?? $driver) {
            'file' => new FileCache($config['path'] ?? sys_get_temp_dir() . '/cache'),
            'redis' => RedisCache::fromConfig($config),
            'memory', 'array' => new MemoryCache(),
            default => throw new \InvalidArgumentException("Unsupported cache driver: {$driver}"),
        };
    }

    // Proxy methods to default driver

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->driver()->get($key, $default);
    }

    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        return $this->driver()->set($key, $value, $ttl);
    }

    public function has(string $key): bool
    {
        return $this->driver()->has($key);
    }

    public function delete(string $key): bool
    {
        return $this->driver()->delete($key);
    }

    public function clear(): bool
    {
        return $this->driver()->clear();
    }

    public function getMultiple(array $keys, mixed $default = null): array
    {
        return $this->driver()->getMultiple($keys, $default);
    }

    public function setMultiple(array $values, ?int $ttl = null): bool
    {
        return $this->driver()->setMultiple($values, $ttl);
    }

    public function deleteMultiple(array $keys): bool
    {
        return $this->driver()->deleteMultiple($keys);
    }

    public function increment(string $key, int $value = 1): int|false
    {
        return $this->driver()->increment($key, $value);
    }

    public function decrement(string $key, int $value = 1): int|false
    {
        return $this->driver()->decrement($key, $value);
    }

    public function remember(string $key, ?int $ttl, callable $callback): mixed
    {
        return $this->driver()->remember($key, $ttl, $callback);
    }

    public function rememberForever(string $key, callable $callback): mixed
    {
        return $this->driver()->rememberForever($key, $callback);
    }

    public function forever(string $key, mixed $value): bool
    {
        return $this->driver()->forever($key, $value);
    }

    public function pull(string $key, mixed $default = null): mixed
    {
        return $this->driver()->pull($key, $default);
    }

    /**
     * Flush all cache stores
     *
     * @return void
     */
    public function flushAll(): void
    {
        foreach ($this->drivers as $driver) {
            $driver->clear();
        }
    }

    /**
     * Get default driver name
     *
     * @return string
     */
    public function getDefaultDriver(): string
    {
        return $this->defaultDriver ?? 'file';
    }
}
