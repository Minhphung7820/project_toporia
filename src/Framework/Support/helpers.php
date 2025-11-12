<?php

declare(strict_types=1);

use Toporia\Framework\Support\Collection;
use Toporia\Framework\Support\Str;
use Toporia\Framework\Support\Stringable;

if (!function_exists('collect')) {
    /**
     * Create a collection from given value.
     */
    function collect(mixed $value = []): Collection
    {
        return Collection::make($value);
    }
}

if (!function_exists('str')) {
    /**
     * Create a fluent string instance.
     */
    function str(string $value = ''): Stringable
    {
        return Str::of($value);
    }
}

if (!function_exists('value')) {
    /**
     * Return the value of the given value.
     * If value is a callable, call it and return result.
     */
    function value(mixed $value, mixed ...$args): mixed
    {
        return $value instanceof Closure ? $value(...$args) : $value;
    }
}

if (!function_exists('tap')) {
    /**
     * Tap into a value, execute callback, and return original value.
     */
    function tap(mixed $value, ?callable $callback = null): mixed
    {
        if ($callback === null) {
            return $value;
        }

        $callback($value);

        return $value;
    }
}

if (!function_exists('with')) {
    /**
     * Return the given value, optionally passed through a callback.
     */
    function with(mixed $value, ?callable $callback = null): mixed
    {
        return $callback === null ? $value : $callback($value);
    }
}

if (!function_exists('blank')) {
    /**
     * Determine if the given value is "blank".
     */
    function blank(mixed $value): bool
    {
        if (is_null($value)) {
            return true;
        }

        if (is_string($value)) {
            return trim($value) === '';
        }

        if (is_numeric($value) || is_bool($value)) {
            return false;
        }

        if ($value instanceof Countable) {
            return count($value) === 0;
        }

        return empty($value);
    }
}

if (!function_exists('filled')) {
    /**
     * Determine if a value is "filled".
     */
    function filled(mixed $value): bool
    {
        return !blank($value);
    }
}

if (!function_exists('data_get')) {
    /**
     * Get an item from an array or object using "dot" notation.
     */
    function data_get(mixed $target, string|array|null $key, mixed $default = null): mixed
    {
        if ($key === null) {
            return $target;
        }

        $key = is_array($key) ? $key : explode('.', $key);

        foreach ($key as $segment) {
            if (is_array($target) && array_key_exists($segment, $target)) {
                $target = $target[$segment];
            } elseif (is_object($target) && isset($target->{$segment})) {
                $target = $target->{$segment};
            } else {
                return value($default);
            }
        }

        return $target;
    }
}

if (!function_exists('data_set')) {
    /**
     * Set an item on an array or object using dot notation.
     */
    function data_set(mixed &$target, string|array $key, mixed $value, bool $overwrite = true): mixed
    {
        $segments = is_array($key) ? $key : explode('.', $key);
        $segment = array_shift($segments);

        if ($segment === '*') {
            if (!is_array($target)) {
                $target = [];
            }

            if ($segments) {
                foreach ($target as &$inner) {
                    data_set($inner, $segments, $value, $overwrite);
                }
            } elseif ($overwrite) {
                foreach ($target as &$inner) {
                    $inner = $value;
                }
            }
        } elseif (is_array($target)) {
            if ($segments) {
                if (!isset($target[$segment])) {
                    $target[$segment] = [];
                }

                data_set($target[$segment], $segments, $value, $overwrite);
            } elseif ($overwrite || !isset($target[$segment])) {
                $target[$segment] = $value;
            }
        } elseif (is_object($target)) {
            if ($segments) {
                if (!isset($target->{$segment})) {
                    $target->{$segment} = [];
                }

                data_set($target->{$segment}, $segments, $value, $overwrite);
            } elseif ($overwrite || !isset($target->{$segment})) {
                $target->{$segment} = $value;
            }
        } else {
            $target = [];

            if ($segments) {
                data_set($target[$segment], $segments, $value, $overwrite);
            } elseif ($overwrite) {
                $target[$segment] = $value;
            }
        }

        return $target;
    }
}

if (!function_exists('data_fill')) {
    /**
     * Fill in data where keys are non-existent.
     */
    function data_fill(mixed &$target, string|array $key, mixed $value): mixed
    {
        return data_set($target, $key, $value, false);
    }
}

if (!function_exists('retry')) {
    /**
     * Retry an operation a given number of times.
     */
    function retry(int $times, callable $callback, int $sleepMilliseconds = 0): mixed
    {
        $lastException = null;

        for ($attempts = 1; $attempts <= $times; $attempts++) {
            try {
                return $callback($attempts);
            } catch (Throwable $e) {
                $lastException = $e;

                if ($attempts < $times && $sleepMilliseconds > 0) {
                    usleep($sleepMilliseconds * 1000);
                }
            }
        }

        // If we've exhausted all attempts, throw the last exception
        throw $lastException;
    }
}

if (!function_exists('transform')) {
    /**
     * Transform a value if it is not blank.
     */
    function transform(mixed $value, callable $callback, mixed $default = null): mixed
    {
        if (filled($value)) {
            return $callback($value);
        }

        return value($default);
    }
}

if (!function_exists('once')) {
    /**
     * Ensure a callable is only executed once.
     */
    function once(callable $callback): callable
    {
        return function (...$args) use ($callback) {
            static $called = false;
            static $result = null;

            if (!$called) {
                $result = $callback(...$args);
                $called = true;
            }

            return $result;
        };
    }
    if (!function_exists('iter')) {
        /**
         * Chuẩn hoá về Traversable để có thể foreach an toàn mà không copy mảng.
         */
        function iter(mixed $value): Traversable
        {
            if ($value instanceof Traversable) return $value;
            if (is_array($value)) {
                foreach ($value as $k => $v) yield $k => $v;
                return;
            }
            if (is_null($value)) return (function () {
                if (false) yield null;
            })();
            yield $value;
        }
    }

    if (!function_exists('comparer')) {
        /**
         * Sinh comparator (dùng cho usort/uasort) từ key hoặc callback.
         * comparer('price', 'desc', SORT_NUMERIC)
         */
        function comparer(callable|string $key, string $direction = 'asc', int $type = SORT_REGULAR): callable
        {
            $extract = is_string($key)
                ? fn($v) => is_array($v) ? ($v[$key] ?? null) : (is_object($v) ? ($v->{$key} ?? null) : null)
                : $key;

            $mult = strtolower($direction) === 'desc' ? -1 : 1;

            return function ($a, $b) use ($extract, $type, $mult) {
                $va = $extract($a);
                $vb = $extract($b);
                return $mult * (
                    $type === SORT_NUMERIC ? (($va <=> $vb)) : ($type === SORT_STRING ? strcmp((string)$va, (string)$vb) : ($va <=> $vb))
                );
            };
        }
    }
}

// =============================================================================
// Security Helper Functions
// =============================================================================

if (!function_exists('csrf_token')) {
    /**
     * Get the current CSRF token.
     */
    function csrf_token(): string
    {
        if (function_exists('app') && app()->has('csrf')) {
            return app('csrf')->getToken();
        }

        // Fallback for standalone usage
        if (isset($_SESSION['_csrf_token'])) {
            return $_SESSION['_csrf_token'];
        }

        throw new RuntimeException('CSRF token manager not available');
    }
}

if (!function_exists('csrf_field')) {
    /**
     * Generate a hidden CSRF token field for forms.
     */
    function csrf_field(): string
    {
        $token = csrf_token();
        return '<input type="hidden" name="_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }
}

if (!function_exists('replay_nonce')) {
    /**
     * Generate a replay attack protection nonce.
     *
     * Performance: O(1) - Direct nonce generation
     *
     * @param int $ttl Time-to-live in seconds (default: 300 = 5 minutes)
     * @return string Nonce token
     */
    function replay_nonce(int $ttl = 300): string
    {
        if (function_exists('app') && app()->has('replay')) {
            return app('replay')->generateNonce($ttl);
        }

        throw new RuntimeException('Replay attack protection not available');
    }
}

if (!function_exists('replay_nonce_field')) {
    /**
     * Generate a hidden nonce field for forms.
     *
     * Performance: O(1) - Direct nonce generation and HTML output
     *
     * @param int $ttl Time-to-live in seconds (default: 300 = 5 minutes)
     * @return string HTML input field
     */
    function replay_nonce_field(int $ttl = 300): string
    {
        $nonce = replay_nonce($ttl);
        return '<input type="hidden" name="_nonce" value="' . htmlspecialchars($nonce, ENT_QUOTES, 'UTF-8') . '">';
    }
}

if (!function_exists('security_fields')) {
    /**
     * Generate both CSRF token and replay nonce fields for forms.
     *
     * Convenience function to add both security fields at once.
     *
     * Performance: O(1) - Direct generation and HTML output
     *
     * @param int $nonceTtl Time-to-live for nonce in seconds (default: 300 = 5 minutes)
     * @return string Combined HTML input fields
     */
    function security_fields(int $nonceTtl = 300): string
    {
        return csrf_field() . "\n" . replay_nonce_field($nonceTtl);
    }
}

if (!function_exists('e')) {
    /**
     * Escape HTML special characters (alias for Xss::escape).
     *
     * Uses Xss accessor if available, falls back to direct htmlspecialchars for performance.
     *
     * Performance: O(1) - Direct function call, no overhead
     * Clean Architecture: Uses Xss service when available, fallback for early bootstrap
     *
     * @param string|null $value Value to escape
     * @param bool $doubleEncode Whether to encode existing HTML entities
     * @return string Escaped value
     */
    function e(?string $value, bool $doubleEncode = true): string
    {
        // Use Xss accessor if container is available (after bootstrap)
        if (function_exists('app') && app()->has('xss')) {
            return \Toporia\Framework\Support\Accessors\Xss::escape($value, $doubleEncode);
        }

        // Fallback for early bootstrap or when service not available
        if ($value === null) {
            return '';
        }

        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8', $doubleEncode);
    }
}

if (!function_exists('clean')) {
    /**
     * Remove all HTML tags from a string (alias for Xss::clean).
     *
     * Uses Xss accessor if available, falls back to direct strip_tags for performance.
     *
     * Performance: O(1) - Direct function call, no overhead
     * Clean Architecture: Uses Xss service when available, fallback for early bootstrap
     *
     * @param string|null $value Value to clean
     * @return string Cleaned value
     */
    function clean(?string $value): string
    {
        // Use Xss accessor if container is available (after bootstrap)
        if (function_exists('app') && app()->has('xss')) {
            return \Toporia\Framework\Support\Accessors\Xss::clean($value);
        }

        // Fallback for early bootstrap or when service not available
        if ($value === null) {
            return '';
        }

        return strip_tags($value);
    }
}

if (!function_exists('cache')) {
    /**
     * Get cache instance or retrieve/store cached value.
     *
     * @param string|null $key Cache key
     * @param mixed $default Default value or closure
     * @return mixed Cache instance or cached value
     */
    function cache(?string $key = null, mixed $default = null): mixed
    {
        if (function_exists('app') && app()->has('cache')) {
            $cache = app('cache');

            if ($key === null) {
                return $cache;
            }

            if ($default instanceof Closure) {
                return $cache->remember($key, $default);
            }

            return $cache->get($key, $default);
        }

        throw new RuntimeException('Cache manager not available');
    }
}

// =============================================================================
// HTTP Request/Response Helper Functions
// =============================================================================

if (!function_exists('request')) {
    /**
     * Get the current HTTP request instance from the container.
     *
     * @return \Toporia\Framework\Http\Request
     * @throws RuntimeException if Request is not available in container
     */
    function request(): \Toporia\Framework\Http\Request
    {
        if (function_exists('app') && app()->has(\Toporia\Framework\Http\Request::class)) {
            return app(\Toporia\Framework\Http\Request::class);
        }

        throw new RuntimeException('Request instance not available in container');
    }
}

if (!function_exists('response')) {
    /**
     * Get the current HTTP response instance from the container.
     *
     * @return \Toporia\Framework\Http\Response
     * @throws RuntimeException if Response is not available in container
     */
    function response(): \Toporia\Framework\Http\Response
    {
        if (function_exists('app') && app()->has(\Toporia\Framework\Http\Response::class)) {
            return app(\Toporia\Framework\Http\Response::class);
        }

        throw new RuntimeException('Response instance not available in container');
    }
}

if (!function_exists('view')) {
    /**
     * Render a view template.
     *
     * @param string $path View path relative to Views directory (without .php extension)
     * @param array $data Data to extract into view scope
     * @return string Rendered HTML content
     */
    function view(string $path, array $data = []): string
    {
        extract($data);
        ob_start();

        $viewPath = __DIR__ . '/../../App/Presentation/Views/' . $path . '.php';

        if (!file_exists($viewPath)) {
            throw new RuntimeException("View not found: {$path}");
        }

        include $viewPath;
        return ob_get_clean();
    }
}

// =============================================================================
// Queue & Job Dispatching Helper Functions
// =============================================================================

if (!function_exists('dispatch')) {
    /**
     * Dispatch a job to the queue (Laravel-style).
     *
     * **Auto-dispatch** when PendingDispatch is destroyed:
     * ```php
     * // Simple dispatch - NO ->dispatch() needed!
     * dispatch(new SendEmailJob($to, $subject, $body));
     * ```
     *
     * **Fluent API** for advanced config:
     * ```php
     * dispatch(new SendEmailJob(...))
     *     ->onQueue('emails')
     *     ->delay(60);  // Auto-dispatches with config when destroyed
     * ```
     *
     * **Explicit dispatch** (optional, for clarity):
     * ```php
     * dispatch(new SendEmailJob(...))->dispatch(); // Works but redundant
     * ```
     *
     * Performance: O(1) - Lightweight PendingDispatch with __destruct() auto-dispatch
     *
     * @param object $job Job instance
     * @return \Toporia\Framework\Queue\PendingDispatch
     * @throws RuntimeException if dispatcher not available
     */
    function dispatch(object $job): \Toporia\Framework\Queue\PendingDispatch
    {
        if (!function_exists('app') || !app()->has('dispatcher')) {
            throw new RuntimeException('Job dispatcher not available in container. Register JobDispatcher in QueueServiceProvider.');
        }

        $dispatcher = app('dispatcher');
        return new \Toporia\Framework\Queue\PendingDispatch($job, $dispatcher);
    }
}

if (!function_exists('dispatch_sync')) {
    /**
     * Dispatch a job synchronously (execute immediately).
     *
     * Executes job immediately with dependency injection support.
     * Useful for testing or when you need immediate results.
     *
     * Performance: O(1) + job execution time
     *
     * @param object $job Job instance
     * @return mixed Job return value
     * @throws RuntimeException if dispatcher not available
     */
    function dispatch_sync(object $job): mixed
    {
        if (!function_exists('app') || !app()->has('dispatcher')) {
            throw new RuntimeException('Job dispatcher not available in container');
        }

        return app('dispatcher')->dispatchSync($job);
    }
}
