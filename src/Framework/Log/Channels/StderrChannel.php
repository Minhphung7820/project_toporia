<?php

declare(strict_types=1);

namespace Toporia\Framework\Log\Channels;

use Toporia\Framework\Log\Contracts\ChannelInterface;

/**
 * Stderr Channel - Standard error output
 *
 * Writes logs to STDERR stream.
 * Useful for Docker containers, CLI applications, and error tracking.
 *
 * Performance: O(1) write operation
 */
final class StderrChannel implements ChannelInterface
{
    private string $dateFormat;

    public function __construct(string $dateFormat = 'Y-m-d H:i:s')
    {
        $this->dateFormat = $dateFormat;
    }

    public function write(string $level, string $message, array $context = []): void
    {
        $timestamp = date($this->dateFormat);
        $levelUpper = strtoupper($level);

        // Format: [2025-01-11 13:45:23] ERROR: Something went wrong {"user_id":123}
        $logEntry = sprintf(
            "[%s] %s: %s",
            $timestamp,
            $levelUpper,
            $message
        );

        // Add context if present
        if (!empty($context)) {
            $logEntry .= ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        $logEntry .= PHP_EOL;

        // Write to STDERR
        fwrite(STDERR, $logEntry);
    }
}
