<?php

declare(strict_types=1);

namespace Toporia\Framework\Support\Accessors;

use Toporia\Framework\Foundation\ServiceAccessor;
use Toporia\Framework\Log\LogManager;

/**
 * Log Facade
 *
 * Static accessor for LogManager service.
 *
 * Example:
 * ```php
 * Log::channel('daily')->error('Error occurred');
 * Log::info('User logged in', ['user_id' => 123]);
 * Log::error('Payment failed', ['order_id' => 456]);
 * ```
 *
 * @method static \Toporia\Framework\Log\LoggerInterface channel(?string $name = null)
 * @method static void emergency(string $message, array $context = [])
 * @method static void alert(string $message, array $context = [])
 * @method static void critical(string $message, array $context = [])
 * @method static void error(string $message, array $context = [])
 * @method static void warning(string $message, array $context = [])
 * @method static void notice(string $message, array $context = [])
 * @method static void info(string $message, array $context = [])
 * @method static void debug(string $message, array $context = [])
 * @method static void log(string $level, string $message, array $context = [])
 *
 * @see \Toporia\Framework\Log\LogManager
 */
final class Log extends ServiceAccessor
{
    protected static function getServiceName(): string
    {
        return 'log';
    }
}
