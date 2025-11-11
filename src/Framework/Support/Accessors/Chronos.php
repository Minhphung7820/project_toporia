<?php

declare(strict_types=1);

namespace Toporia\Framework\Support\Accessors;

use Toporia\Framework\Foundation\ServiceAccessor;
use Toporia\Framework\DateTime\Chronos as ChronosImpl;

/**
 * Chronos Facade
 *
 * Static accessor for Chronos date/time library.
 *
 * Example:
 * ```php
 * Chronos::now()
 * Chronos::parse('2025-01-01')
 * Chronos::create(2025, 1, 1)
 * ```
 *
 * @method static ChronosImpl now(\DateTimeZone|string|null $timezone = null)
 * @method static ChronosImpl parse(string $time, \DateTimeZone|string|null $timezone = null)
 * @method static ChronosImpl create(?int $year = null, ?int $month = null, ?int $day = null, ?int $hour = null, ?int $minute = null, ?int $second = null, \DateTimeZone|string|null $timezone = null)
 * @method static ChronosImpl createFromTimestamp(int $timestamp, \DateTimeZone|string|null $timezone = null)
 * @method static ChronosImpl createFromFormat(string $format, string $time, \DateTimeZone|string|null $timezone = null)
 */
final class Chronos extends ServiceAccessor
{
    protected static function getServiceName(): string
    {
        return ChronosImpl::class;
    }

    /**
     * Forward static calls to Chronos instance.
     *
     * @param string $method
     * @param array $args
     * @return mixed
     */
    public static function __callStatic(string $method, array $args): mixed
    {
        // For static factory methods, call directly on the class
        if (in_array($method, ['now', 'parse', 'create', 'createFromTimestamp', 'createFromFormat'])) {
            return ChronosImpl::$method(...$args);
        }

        // Otherwise, get instance from container and call method
        return static::getInstance()->$method(...$args);
    }
}
