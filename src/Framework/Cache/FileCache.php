<?php

declare(strict_types=1);

namespace Toporia\Framework\Cache;

/**
 * File-based Cache Driver
 *
 * Stores cache entries as serialized files in the filesystem.
 * Good for simple deployments without external dependencies.
 */
final class FileCache implements CacheInterface
{
    private string $directory;

    public function __construct(string $directory = '/tmp/cache')
    {
        $this->directory = rtrim($directory, '/');
        $this->ensureDirectoryExists();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $file = $this->getFilePath($key);

        if (!file_exists($file)) {
            return $default;
        }

        $data = unserialize(file_get_contents($file));

        // Check if expired
        if ($data['expires_at'] !== null && $data['expires_at'] < time()) {
            $this->delete($key);
            return $default;
        }

        return $data['value'];
    }

    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $file = $this->getFilePath($key);
        $expiresAt = $ttl !== null ? time() + $ttl : null;

        $data = [
            'value' => $value,
            'expires_at' => $expiresAt,
        ];

        $result = file_put_contents($file, serialize($data), LOCK_EX);
        return $result !== false;
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    public function delete(string $key): bool
    {
        $file = $this->getFilePath($key);

        if (file_exists($file)) {
            return unlink($file);
        }

        return true;
    }

    public function clear(): bool
    {
        $files = glob($this->directory . '/*');

        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        return true;
    }

    public function getMultiple(array $keys, mixed $default = null): array
    {
        $result = [];

        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }

        return $result;
    }

    public function setMultiple(array $values, ?int $ttl = null): bool
    {
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
        $success = true;

        foreach ($keys as $key) {
            if (!$this->delete($key)) {
                $success = false;
            }
        }

        return $success;
    }

    public function increment(string $key, int $value = 1): int|false
    {
        $current = $this->get($key, 0);

        if (!is_numeric($current)) {
            return false;
        }

        $new = (int)$current + $value;
        $this->set($key, $new);

        return $new;
    }

    public function decrement(string $key, int $value = 1): int|false
    {
        return $this->increment($key, -$value);
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
        $value = $this->get($key, $default);
        $this->delete($key);
        return $value;
    }

    /**
     * Get the file path for a cache key
     *
     * @param string $key
     * @return string
     */
    private function getFilePath(string $key): string
    {
        $hash = md5($key);
        return $this->directory . '/' . $hash . '.cache';
    }

    /**
     * Ensure the cache directory exists
     *
     * @return void
     */
    private function ensureDirectoryExists(): void
    {
        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0755, true);
        }
    }
}
