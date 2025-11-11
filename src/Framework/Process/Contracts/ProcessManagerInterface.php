<?php

declare(strict_types=1);

namespace Toporia\Framework\Process\Contracts;

/**
 * Process Manager Interface
 *
 * Contract for managing multiple processes in a pool.
 * Provides efficient parallel execution with resource management.
 */
interface ProcessManagerInterface
{
    /**
     * Add a process to the pool.
     *
     * @param callable $callback
     * @param array $args
     * @return ProcessInterface
     */
    public function add(callable $callback, array $args = []): ProcessInterface;

    /**
     * Run all processes in parallel.
     *
     * @param int $maxConcurrent Maximum concurrent processes
     * @return array Results from all processes
     */
    public function run(int $maxConcurrent = 4): array;

    /**
     * Wait for all processes to complete.
     *
     * @return array Results
     */
    public function wait(): array;

    /**
     * Get number of running processes.
     *
     * @return int
     */
    public function getRunningCount(): int;

    /**
     * Kill all processes.
     *
     * @param int $signal
     * @return void
     */
    public function killAll(int $signal = 15): void;

    /**
     * Check if any process is running.
     *
     * @return bool
     */
    public function hasRunning(): bool;
}
