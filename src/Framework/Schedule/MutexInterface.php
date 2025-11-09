<?php

declare(strict_types=1);

namespace Toporia\Framework\Schedule;

/**
 * Mutex Interface
 *
 * Provides mutual exclusion for scheduled tasks to prevent overlapping execution.
 */
interface MutexInterface
{
    /**
     * Attempt to acquire a lock
     *
     * @param string $name Lock name
     * @param int $expiresAfter Lock expires after X minutes
     * @return bool True if lock was acquired, false if already locked
     */
    public function create(string $name, int $expiresAfter = 1440): bool;

    /**
     * Check if a lock exists
     *
     * @param string $name Lock name
     * @return bool
     */
    public function exists(string $name): bool;

    /**
     * Release a lock
     *
     * @param string $name Lock name
     * @return bool
     */
    public function forget(string $name): bool;
}
