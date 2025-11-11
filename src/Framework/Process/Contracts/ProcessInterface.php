<?php

declare(strict_types=1);

namespace Toporia\Framework\Process\Contracts;

/**
 * Process Interface
 *
 * Contract for managing individual processes.
 * Supports fork-based multiprocessing with PCNTL.
 */
interface ProcessInterface
{
    /**
     * Start the process.
     *
     * @return bool True if started successfully
     */
    public function start(): bool;

    /**
     * Check if process is running.
     *
     * @return bool
     */
    public function isRunning(): bool;

    /**
     * Wait for process to finish.
     *
     * @return int Exit code
     */
    public function wait(): int;

    /**
     * Get process ID (PID).
     *
     * @return int|null
     */
    public function getPid(): ?int;

    /**
     * Get exit code.
     *
     * @return int|null
     */
    public function getExitCode(): ?int;

    /**
     * Kill the process.
     *
     * @param int $signal Signal to send (default: SIGTERM)
     * @return bool
     */
    public function kill(int $signal = 15): bool;

    /**
     * Get process output.
     *
     * @return mixed
     */
    public function getOutput(): mixed;
}
