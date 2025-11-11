<?php

declare(strict_types=1);

namespace Toporia\Framework\Log\Channels;

use Toporia\Framework\Log\Contracts\ChannelInterface;

/**
 * Daily File Channel - Rotating daily log files
 *
 * Creates a new log file each day with format: YYYY-MM-DD.log
 * Automatically rotates logs by date for easy management.
 *
 * Example:
 * - 2025-01-11.log
 * - 2025-01-12.log
 * - 2025-01-13.log
 *
 * Features:
 * - Automatic file rotation by date
 * - Optional log retention (auto-delete old logs)
 * - Thread-safe with file locking
 *
 * Performance: O(1) write operation
 */
final class DailyFileChannel implements ChannelInterface
{
    private string $logPath;
    private string $dateFormat;
    private ?int $daysToKeep;

    /**
     * @param string $logPath Directory path for log files
     * @param string $dateFormat Timestamp format for log entries
     * @param int|null $daysToKeep Number of days to retain logs (null = keep all)
     */
    public function __construct(
        string $logPath,
        string $dateFormat = 'Y-m-d H:i:s',
        ?int $daysToKeep = null
    ) {
        $this->logPath = rtrim($logPath, '/');
        $this->dateFormat = $dateFormat;
        $this->daysToKeep = $daysToKeep;

        // Ensure directory exists
        if (!is_dir($this->logPath)) {
            mkdir($this->logPath, 0755, true);
        }
    }

    public function write(string $level, string $message, array $context = []): void
    {
        $logFile = $this->getLogFile();
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

        // Atomic write to prevent race conditions
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);

        // Cleanup old logs if retention policy is set
        if ($this->daysToKeep !== null) {
            $this->cleanupOldLogs();
        }
    }

    /**
     * Get current log file path.
     *
     * Format: /path/to/logs/2025-01-11.log
     *
     * @return string
     */
    private function getLogFile(): string
    {
        $date = date('Y-m-d');
        return $this->logPath . '/' . $date . '.log';
    }

    /**
     * Cleanup logs older than retention period.
     *
     * Called automatically after each write operation.
     * Uses glob() for O(N) file discovery.
     *
     * @return void
     */
    private function cleanupOldLogs(): void
    {
        $cutoffTime = strtotime("-{$this->daysToKeep} days");
        $pattern = $this->logPath . '/*.log';

        foreach (glob($pattern) as $file) {
            // Extract date from filename (YYYY-MM-DD.log)
            $filename = basename($file);
            if (preg_match('/^(\d{4}-\d{2}-\d{2})\.log$/', $filename, $matches)) {
                $fileDate = $matches[1];
                $fileTime = strtotime($fileDate);

                if ($fileTime !== false && $fileTime < $cutoffTime) {
                    @unlink($file);
                }
            }
        }
    }
}
