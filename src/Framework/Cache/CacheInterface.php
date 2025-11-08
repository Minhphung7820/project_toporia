<?php

declare(strict_types=1);

namespace Toporia\Framework\Cache;

/**
 * Cache Interface
 *
 * PSR-16 inspired simple cache interface with TTL support.
 */
interface CacheInterface
{
    /**
     * Get a value from cache
     *
     * @param string $key Cache key
     * @param mixed $default Default value if key doesn't exist
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Set a value in cache
     *
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int|null $ttl Time to live in seconds (null = forever)
     * @return bool True on success
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool;

    /**
     * Check if a key exists in cache
     *
     * @param string $key Cache key
     * @return bool
     */
    public function has(string $key): bool;

    /**
     * Delete a value from cache
     *
     * @param string $key Cache key
     * @return bool True on success
     */
    public function delete(string $key): bool;

    /**
     * Clear all cache
     *
     * @return bool True on success
     */
    public function clear(): bool;

    /**
     * Get multiple values from cache
     *
     * @param array $keys Array of cache keys
     * @param mixed $default Default value
     * @return array Key-value pairs
     */
    public function getMultiple(array $keys, mixed $default = null): array;

    /**
     * Set multiple values in cache
     *
     * @param array $values Key-value pairs
     * @param int|null $ttl Time to live in seconds
     * @return bool True on success
     */
    public function setMultiple(array $values, ?int $ttl = null): bool;

    /**
     * Delete multiple values from cache
     *
     * @param array $keys Array of cache keys
     * @return bool True on success
     */
    public function deleteMultiple(array $keys): bool;

    /**
     * Increment a numeric value
     *
     * @param string $key Cache key
     * @param int $value Amount to increment
     * @return int|false New value or false on failure
     */
    public function increment(string $key, int $value = 1): int|false;

    /**
     * Decrement a numeric value
     *
     * @param string $key Cache key
     * @param int $value Amount to decrement
     * @return int|false New value or false on failure
     */
    public function decrement(string $key, int $value = 1): int|false;

    /**
     * Remember a value in cache using a closure
     *
     * @param string $key Cache key
     * @param int|null $ttl Time to live in seconds
     * @param callable $callback Callback to generate value if not cached
     * @return mixed
     */
    public function remember(string $key, ?int $ttl, callable $callback): mixed;

    /**
     * Remember a value in cache forever
     *
     * @param string $key Cache key
     * @param callable $callback Callback to generate value if not cached
     * @return mixed
     */
    public function rememberForever(string $key, callable $callback): mixed;

    /**
     * Store a value in cache forever
     *
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @return bool True on success
     */
    public function forever(string $key, mixed $value): bool;

    /**
     * Get and delete a value from cache
     *
     * @param string $key Cache key
     * @param mixed $default Default value
     * @return mixed
     */
    public function pull(string $key, mixed $default = null): mixed;
}
