<?php

declare(strict_types=1);

/**
 * Application Helper Functions
 *
 * Global helper functions for convenient access to application services.
 */

use Toporia\Framework\Events\Contracts\EventInterface;

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
     * @param string|EventInterface $event Event name or object.
     * @param array $payload Event payload data.
     * @return EventInterface
     */
    function event(string|EventInterface $event, array $payload = []): EventInterface
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

if (!function_exists('storage')) {
    /**
     * Get the storage manager or a specific disk.
     *
     * Usage:
     * - storage() - Get StorageManager
     * - storage('local') - Get specific disk
     * - storage()->disk('s3') - Get S3 disk
     *
     * @param string|null $disk Disk name
     * @return \Toporia\Framework\Storage\StorageManager|\Toporia\Framework\Storage\Contracts\FilesystemInterface
     */
    function storage(?string $disk = null): mixed
    {
        $manager = app('storage');

        if ($disk === null) {
            return $manager;
        }

        return $manager->disk($disk);
    }
}

if (!function_exists('dd')) {
    /**
     * Dump the given variables and end the script (Laravel-style).
     *
     * @param mixed ...$vars Variables to dump
     * @return never
     */
    function dd(mixed ...$vars): never
    {
        http_response_code(500);

        foreach ($vars as $var) {
            dump($var);
        }

        exit(1);
    }
}

if (!function_exists('dump')) {
    /**
     * Dump the given variable (Laravel-style).
     *
     * @param mixed $var Variable to dump
     * @return mixed Returns the dumped variable for chaining
     */
    function dump(mixed $var): mixed
    {
        echo '<pre style="background: #18171B; color: #FF8C00; padding: 10px; border-radius: 5px; margin: 10px 0; font-family: monospace; font-size: 14px; line-height: 1.5; overflow: auto; max-height: 600px;">';

        // Check if it's a CLI environment
        if (php_sapi_name() === 'cli') {
            echo "\n";
            var_dump($var);
            echo "\n";
        } else {
            // Web environment - use fancy output
            echo htmlspecialchars(var_export($var, true), ENT_QUOTES, 'UTF-8');
        }

        echo '</pre>';

        return $var;
    }
}

if (!function_exists('notify')) {
    /**
     * Send a notification to a notifiable entity.
     *
     * @param \Toporia\Framework\Notification\Contracts\NotifiableInterface $notifiable
     * @param \Toporia\Framework\Notification\Contracts\NotificationInterface $notification
     * @return void
     */
    function notify(
        \Toporia\Framework\Notification\Contracts\NotifiableInterface $notifiable,
        \Toporia\Framework\Notification\Contracts\NotificationInterface $notification
    ): void {
        app('notification')->send($notifiable, $notification);
    }
}

if (!function_exists('broadcast')) {
    /**
     * Broadcast realtime event to a channel.
     *
     * @param string $channel Channel name
     * @param string $event Event name
     * @param mixed $data Event data
     * @return void
     */
    function broadcast(string $channel, string $event, mixed $data): void
    {
        app('realtime')->broadcast($channel, $event, $data);
    }
}

if (!function_exists('config')) {
    /**
     * Get configuration value.
     *
     * Usage:
     * - config('app.name') - Get specific config value
     * - config('app.name', 'default') - With default value
     *
     * @param string $key Config key in dot notation (e.g., 'app.name')
     * @param mixed $default Default value
     * @return mixed
     */
    function config(string $key, mixed $default = null): mixed
    {
        return app('config')->get($key, $default);
    }
}

if (!function_exists('hash_make')) {
    /**
     * Hash the given value.
     *
     * @param string $value Plain text value to hash
     * @param array $options Hashing options
     * @return string Hashed value
     */
    function hash_make(string $value, array $options = []): string
    {
        return app('hash')->make($value, $options);
    }
}

if (!function_exists('hash_check')) {
    /**
     * Check the given plain value against a hash.
     *
     * @param string $value Plain text value
     * @param string $hashedValue Hashed value
     * @return bool True if match
     */
    function hash_check(string $value, string $hashedValue): bool
    {
        return app('hash')->check($value, $hashedValue);
    }
}

if (!function_exists('hash_needs_rehash')) {
    /**
     * Check if the given hash needs to be rehashed.
     *
     * @param string $hashedValue Hashed value
     * @param array $options Current options
     * @return bool True if rehash needed
     */
    function hash_needs_rehash(string $hashedValue, array $options = []): bool
    {
        return app('hash')->needsRehash($hashedValue, $options);
    }
}

// ============================================================================
// URL Generation Helpers
// ============================================================================

if (!function_exists('url')) {
    /**
     * Generate a URL to a path.
     *
     * Usage:
     * - url() - Get UrlGenerator instance
     * - url('/path') - Generate URL to path
     * - url('/path', ['key' => 'value']) - With query parameters
     *
     * @param string|null $path URL path
     * @param array<string, mixed> $query Query parameters
     * @param bool $absolute Generate absolute URL (default: true)
     * @return \Toporia\Framework\Routing\UrlGeneratorInterface|string
     */
    function url(?string $path = null, array $query = [], bool $absolute = true): mixed
    {
        $generator = app('url');

        if ($path === null) {
            return $generator;
        }

        return $generator->to($path, $query, $absolute);
    }
}

if (!function_exists('route')) {
    /**
     * Generate a URL to a named route.
     *
     * @param string $name Route name
     * @param array<string, mixed> $parameters Route parameters
     * @param bool $absolute Generate absolute URL (default: true)
     * @return string Generated URL
     */
    function route(string $name, array $parameters = [], bool $absolute = true): string
    {
        return app('url')->route($name, $parameters, $absolute);
    }
}

if (!function_exists('asset')) {
    /**
     * Generate an asset URL.
     *
     * @param string $path Asset path
     * @param bool $absolute Generate absolute URL (default: false)
     * @return string Generated URL
     */
    function asset(string $path, bool $absolute = false): string
    {
        return app('url')->asset($path, $absolute);
    }
}

if (!function_exists('secure_asset')) {
    /**
     * Generate a secure asset URL (HTTPS).
     *
     * @param string $path Asset path
     * @return string Generated URL
     */
    function secure_asset(string $path): string
    {
        return app('url')->secureAsset($path);
    }
}

if (!function_exists('secure_url')) {
    /**
     * Generate a secure URL to a path (HTTPS).
     *
     * @param string $path URL path
     * @param array<string, mixed> $query Query parameters
     * @return string Generated URL
     */
    function secure_url(string $path, array $query = []): string
    {
        $generator = app('url');
        $generator->forceScheme('https');
        return $generator->to($path, $query, true);
    }
}

if (!function_exists('url_current')) {
    /**
     * Get the current URL.
     *
     * @return string Current URL
     */
    function url_current(): string
    {
        return app('url')->current();
    }
}

if (!function_exists('url_previous')) {
    /**
     * Get the previous URL.
     *
     * @param string|null $default Default URL if no previous
     * @return string Previous URL
     */
    function url_previous(?string $default = null): string
    {
        return app('url')->previous($default);
    }
}

if (!function_exists('url_full')) {
    /**
     * Get the full URL for the current request with query string.
     *
     * @return string Full URL
     */
    function url_full(): string
    {
        return app('url')->full();
    }
}

if (!function_exists('signed_route')) {
    /**
     * Generate a signed URL to a named route.
     *
     * @param string $name Route name
     * @param array<string, mixed> $parameters Route parameters
     * @param int|null $expiration Expiration in seconds from now
     * @param bool $absolute Generate absolute URL (default: true)
     * @return string Signed URL
     */
    function signed_route(string $name, array $parameters = [], ?int $expiration = null, bool $absolute = true): string
    {
        return app('url')->signedRoute($name, $parameters, $expiration, $absolute);
    }
}

if (!function_exists('temporary_signed_route')) {
    /**
     * Generate a temporary signed URL to a named route.
     *
     * @param string $name Route name
     * @param int $expiration Expiration in seconds from now
     * @param array<string, mixed> $parameters Route parameters
     * @param bool $absolute Generate absolute URL (default: true)
     * @return string Signed URL
     */
    function temporary_signed_route(string $name, int $expiration, array $parameters = [], bool $absolute = true): string
    {
        return app('url')->temporarySignedRoute($name, $expiration, $parameters, $absolute);
    }
}

// ============================================================================
// Pipeline Helpers
// ============================================================================

if (!function_exists('pipeline')) {
    /**
     * Create a new pipeline instance.
     *
     * Usage:
     * ```php
     * $result = pipeline($user)
     *     ->through([
     *         ValidateUser::class,
     *         NormalizeData::class,
     *         fn($user, $next) => $next($user)
     *     ])
     *     ->thenReturn();
     * ```
     *
     * @param mixed|null $passable Initial value to send through pipeline
     * @return \Toporia\Framework\Pipeline\Pipeline
     */
    function pipeline(mixed $passable = null): \Toporia\Framework\Pipeline\Pipeline
    {
        $pipeline = \Toporia\Framework\Pipeline\Pipeline::make(app()->getContainer());

        if ($passable !== null) {
            $pipeline->send($passable);
        }

        return $pipeline;
    }
}

if (!function_exists('logger')) {
    /**
     * Get logger instance or log a message.
     *
     * @param string|null $message Log message (null to get logger instance)
     * @param array $context Context data
     * @param string $level Log level (info, error, warning, etc.)
     * @return \Toporia\Framework\Log\Contracts\LoggerInterface|void
     */
    function logger(?string $message = null, array $context = [], string $level = 'info')
    {
        $logger = app('logger');

        if ($message === null) {
            return $logger;
        }

        $logger->log($level, $message, $context);
    }
}

if (!function_exists('log_info')) {
    /**
     * Log an info message.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    function log_info(string $message, array $context = []): void
    {
        app('logger')->info($message, $context);
    }
}

if (!function_exists('log_error')) {
    /**
     * Log an error message.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    function log_error(string $message, array $context = []): void
    {
        app('logger')->error($message, $context);
    }
}

if (!function_exists('log_warning')) {
    /**
     * Log a warning message.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    function log_warning(string $message, array $context = []): void
    {
        app('logger')->warning($message, $context);
    }
}

if (!function_exists('log_debug')) {
    /**
     * Log a debug message.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    function log_debug(string $message, array $context = []): void
    {
        app('logger')->debug($message, $context);
    }
}
