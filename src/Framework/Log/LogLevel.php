<?php

declare(strict_types=1);

namespace Toporia\Framework\Log;

/**
 * Log Levels (PSR-3 compliant)
 *
 * Standard log severity levels.
 */
final class LogLevel
{
    public const EMERGENCY = 'emergency';
    public const ALERT = 'alert';
    public const CRITICAL = 'critical';
    public const ERROR = 'error';
    public const WARNING = 'warning';
    public const NOTICE = 'notice';
    public const INFO = 'info';
    public const DEBUG = 'debug';

    /**
     * Get all log levels.
     *
     * @return array<string>
     */
    public static function all(): array
    {
        return [
            self::EMERGENCY,
            self::ALERT,
            self::CRITICAL,
            self::ERROR,
            self::WARNING,
            self::NOTICE,
            self::INFO,
            self::DEBUG,
        ];
    }

    /**
     * Check if level is valid.
     *
     * @param string $level
     * @return bool
     */
    public static function isValid(string $level): bool
    {
        return in_array($level, self::all(), true);
    }
}
