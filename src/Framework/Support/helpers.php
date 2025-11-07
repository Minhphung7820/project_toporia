<?php

declare(strict_types=1);

use Framework\Support\Collection;
use Framework\Support\Str;
use Framework\Support\Stringable;

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
        $attempts = 0;

        beginning:
        $attempts++;

        try {
            return $callback($attempts);
        } catch (Throwable $e) {
            if ($attempts >= $times) {
                throw $e;
            }

            if ($sleepMilliseconds > 0) {
                usleep($sleepMilliseconds * 1000);
            }

            goto beginning;
        }
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
