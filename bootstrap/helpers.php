<?php

declare(strict_types=1);

/**
 * Application Helper Functions
 *
 * Global helper functions for convenient access to application services.
 */

use Toporia\Framework\Foundation\Application;

if (!function_exists('app')) {
    /**
     * Get the application instance or resolve a service.
     *
     * @param string|null $id Service identifier or null for application.
     * @return mixed
     */
    function app(?string $id = null): mixed
    {
        static $instance = null;

        if ($instance === null) {
            global $app;
            $instance = $app;
        }

        return $id !== null ? $instance->make($id) : $instance;
    }
}

if (!function_exists('event')) {
    /**
     * Dispatch an event.
     *
     * @param string|\Toporia\Framework\Events\EventInterface $event Event name or object.
     * @param array $payload Event payload data.
     * @return \Toporia\Framework\Events\EventInterface
     */
    function event(string|\Toporia\Framework\Events\EventInterface $event, array $payload = []): \Toporia\Framework\Events\EventInterface
    {
        return app('events')->dispatch($event, $payload);
    }
}

if (!function_exists('auth')) {
    /**
     * Get the authentication service.
     *
     * @return mixed
     */
    function auth(): mixed
    {
        return app('auth');
    }
}

if (!function_exists('container')) {
    /**
     * Get the container instance or resolve a service.
     *
     * @param string|null $id Service identifier or null for container.
     * @return mixed
     */
    function container(?string $id = null): mixed
    {
        $container = app()->getContainer();
        return $id !== null ? $container->get($id) : $container;
    }
}

if (!function_exists('config')) {
    /**
     * Get configuration value using dot notation.
     *
     * @param string $key Configuration key (e.g., 'app.name', 'database.default')
     * @param mixed $default Default value if not found
     * @return mixed
     */
    function config(string $key, mixed $default = null): mixed
    {
        // Future: Implement configuration service
        return $default;
    }
}

if (!function_exists('env')) {
    /**
     * Get environment variable value.
     *
     * @param string $key Environment variable name
     * @param mixed $default Default value if not found
     * @return mixed
     */
    function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? getenv($key);

        if ($value === false) {
            return $default;
        }

        // Parse boolean values
        return match (strtolower($value)) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'empty', '(empty)' => '',
            'null', '(null)' => null,
            default => $value,
        };
    }
}
