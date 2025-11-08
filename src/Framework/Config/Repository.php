<?php

declare(strict_types=1);

namespace Framework\Config;

/**
 * Configuration Repository
 *
 * Manages application configuration with dot notation access.
 */
class Repository
{
    /**
     * @var array<string, mixed> Configuration items
     */
    private array $items = [];

    /**
     * @param array<string, mixed> $items Initial configuration items
     */
    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    /**
     * Get a configuration value using dot notation.
     *
     * @param string $key Configuration key (e.g., 'app.name', 'database.default')
     * @param mixed $default Default value if not found
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->items, $key, $default);
    }

    /**
     * Set a configuration value using dot notation.
     *
     * @param string $key Configuration key
     * @param mixed $value Configuration value
     * @return void
     */
    public function set(string $key, mixed $value): void
    {
        data_set($this->items, $key, $value);
    }

    /**
     * Check if a configuration key exists.
     *
     * @param string $key Configuration key
     * @return bool
     */
    public function has(string $key): bool
    {
        return data_get($this->items, $key) !== null;
    }

    /**
     * Get all configuration items.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * Load configuration from a file.
     *
     * @param string $name Configuration name (file basename without .php)
     * @param string $path File path
     * @return void
     */
    public function load(string $name, string $path): void
    {
        if (!file_exists($path)) {
            return;
        }

        $config = require $path;

        if (is_array($config)) {
            $this->items[$name] = $config;
        }
    }

    /**
     * Load all configuration files from a directory.
     *
     * @param string $directory Configuration directory path
     * @return void
     */
    public function loadDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $files = glob($directory . '/*.php');

        foreach ($files as $file) {
            $name = basename($file, '.php');
            $this->load($name, $file);
        }
    }
}
