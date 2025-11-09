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

if (!function_exists('csrf_token')) {
    /**
     * Generate a CSRF token.
     *
     * @param string $key Token identifier
     * @return string
     */
    function csrf_token(string $key = '_token'): string
    {
        $tokenManager = app('csrf');
        $existing = $tokenManager->getToken($key);

        if ($existing !== null) {
            return $existing;
        }

        return $tokenManager->generate($key);
    }
}

if (!function_exists('csrf_field')) {
    /**
     * Generate a CSRF token hidden input field.
     *
     * @param string $key Token identifier
     * @return string
     */
    function csrf_field(string $key = '_token'): string
    {
        $token = csrf_token($key);
        return '<input type="hidden" name="' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }
}

if (!function_exists('cache')) {
    /**
     * Get the cache service or get/set a cached value.
     *
     * @param string|null $key Cache key
     * @param mixed $default Default value
     * @return mixed
     */
    function cache(?string $key = null, mixed $default = null): mixed
    {
        $cache = app('cache');

        if ($key === null) {
            return $cache;
        }

        return $cache->get($key, $default);
    }
}

if (!function_exists('e')) {
    /**
     * Escape HTML special characters.
     *
     * @param string|null $value
     * @param bool $doubleEncode
     * @return string
     */
    function e(?string $value, bool $doubleEncode = true): string
    {
        return \Toporia\Framework\Security\XssProtection::escape($value, $doubleEncode);
    }
}

if (!function_exists('clean')) {
    /**
     * Remove all HTML tags from a string.
     *
     * @param string|null $value
     * @return string
     */
    function clean(?string $value): string
    {
        return \Toporia\Framework\Security\XssProtection::clean($value);
    }
}

if (!function_exists('mail')) {
    /**
     * Get the mail manager or send an email.
     *
     * @param \Toporia\Framework\Mail\Mailable|null $mailable Mailable to send.
     * @return \Toporia\Framework\Mail\MailManagerInterface|bool
     */
    function mail(?\Toporia\Framework\Mail\Mailable $mailable = null): mixed
    {
        $manager = app('mail');

        if ($mailable === null) {
            return $manager;
        }

        return $manager->sendMailable($mailable);
    }
}

if (!function_exists('http')) {
    /**
     * Get the HTTP client manager or make a request.
     *
     * @param string|null $client Client name.
     * @return \Toporia\Framework\Http\Client\ClientManagerInterface|\Toporia\Framework\Http\Client\HttpClientInterface
     */
    function http(?string $client = null): mixed
    {
        $manager = app('http');

        if ($client === null) {
            return $manager;
        }

        return $manager->client($client);
    }
}
