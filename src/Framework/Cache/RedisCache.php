<?php

declare(strict_types=1);

namespace Toporia\Framework\Cache;

use Redis;

/**
 * Redis Cache Driver
 *
 * High-performance caching using Redis.
 * Requires phpredis extension.
 */
final class RedisCache implements CacheInterface
{
    private Redis $redis;
    private string $prefix;

    public function __construct(Redis $redis, string $prefix = 'cache:')
    {
        $this->redis = $redis;
        $this->prefix = $prefix;
    }

    /**
     * Create RedisCache from connection config
     *
     * @param array $config ['host' => '127.0.0.1', 'port' => 6379, 'password' => null, 'database' => 0]
     * @param string $prefix
     * @return self
     */
    public static function fromConfig(array $config, string $prefix = 'cache:'): self
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

        return new self($redis, $prefix);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->redis->get($this->prefixKey($key));

        if ($value === false) {
            return $default;
        }

        return unserialize($value);
    }

    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $key = $this->prefixKey($key);
        $value = serialize($value);

        if ($ttl === null) {
            return $this->redis->set($key, $value);
        }

        return $this->redis->setex($key, $ttl, $value);
    }

    public function has(string $key): bool
    {
        return $this->redis->exists($this->prefixKey($key)) > 0;
    }

    public function delete(string $key): bool
    {
        return $this->redis->del($this->prefixKey($key)) > 0;
    }

    public function clear(): bool
    {
        // Clear all keys with the prefix
        $pattern = $this->prefixKey('*');
        $keys = $this->redis->keys($pattern);

        if (empty($keys)) {
            return true;
        }

        return $this->redis->del($keys) > 0;
    }

    public function getMultiple(array $keys, mixed $default = null): array
    {
        $prefixedKeys = array_map([$this, 'prefixKey'], $keys);
        $values = $this->redis->mGet($prefixedKeys);

        $result = [];
        foreach ($keys as $i => $key) {
            $result[$key] = $values[$i] !== false ? unserialize($values[$i]) : $default;
        }

        return $result;
    }

    public function setMultiple(array $values, ?int $ttl = null): bool
    {
        if ($ttl === null) {
            $prefixed = [];
            foreach ($values as $key => $value) {
                $prefixed[$this->prefixKey($key)] = serialize($value);
            }
            return $this->redis->mSet($prefixed);
        }

        // With TTL, set individually
        $success = true;
        foreach ($values as $key => $value) {
            if (!$this->set($key, $value, $ttl)) {
                $success = false;
            }
        }

        return $success;
    }

    public function deleteMultiple(array $keys): bool
    {
        $prefixedKeys = array_map([$this, 'prefixKey'], $keys);
        return $this->redis->del($prefixedKeys) > 0;
    }

    public function increment(string $key, int $value = 1): int|false
    {
        $result = $this->redis->incrBy($this->prefixKey($key), $value);
        return $result !== false ? (int)$result : false;
    }

    public function decrement(string $key, int $value = 1): int|false
    {
        $result = $this->redis->decrBy($this->prefixKey($key), $value);
        return $result !== false ? (int)$result : false;
    }

    public function remember(string $key, ?int $ttl, callable $callback): mixed
    {
        $value = $this->get($key);

        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        $this->set($key, $value, $ttl);

        return $value;
    }

    public function rememberForever(string $key, callable $callback): mixed
    {
        return $this->remember($key, null, $callback);
    }

    public function forever(string $key, mixed $value): bool
    {
        return $this->set($key, $value, null);
    }

    public function pull(string $key, mixed $default = null): mixed
    {
        $prefixedKey = $this->prefixKey($key);
        $value = $this->redis->get($prefixedKey);

        if ($value !== false) {
            $this->redis->del($prefixedKey);
            return unserialize($value);
        }

        return $default;
    }

    /**
     * Get Redis instance for advanced operations
     *
     * @return Redis
     */
    public function getRedis(): Redis
    {
        return $this->redis;
    }

    /**
     * Add prefix to cache key
     *
     * @param string $key
     * @return string
     */
    private function prefixKey(string $key): string
    {
        return $this->prefix . $key;
    }
}
