<?php

declare(strict_types=1);

namespace Toporia\Framework\Log\Contracts;

/**
 * Log Channel Interface
 *
 * Defines contract for log channel implementations.
 * Each channel handles log output differently (file, syslog, stderr, etc.).
 */
interface ChannelInterface
{
    /**
     * Write a log entry.
     *
     * @param string $level Log level
     * @param string $message Log message
     * @param array $context Additional context data
     * @return void
     */
    public function write(string $level, string $message, array $context = []): void;
}
