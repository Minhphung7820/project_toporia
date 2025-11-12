<?php

declare(strict_types=1);

namespace Toporia\Framework\Process;

use Toporia\Framework\Process\Contracts\ProcessInterface;
use Toporia\Framework\Process\Contracts\ProcessManagerInterface;

/**
 * Process Manager
 *
 * Manages a pool of forked processes with resource limits.
 * Implements efficient parallel execution with automatic cleanup.
 *
 * Features:
 * - Process pool management
 * - Concurrent execution limits
 * - Automatic cleanup of finished processes
 * - Memory efficient (no zombie processes)
 * - Signal handling for graceful shutdown
 * - Non-blocking wait for optimal performance
 *
 * Architecture:
 * - Uses fork() for true multiprocessing
 * - Each process runs in isolated memory
 * - Master process manages worker lifecycle
 * - Workers report results via serialization
 *
 * Performance:
 * - O(1) process creation
 * - O(N) where N = number of concurrent processes
 * - Minimal memory overhead in master process
 * - Linear scaling with CPU cores
 *
 * Example:
 * ```php
 * $manager = new ProcessManager();
 *
 * // Add tasks
 * for ($i = 0; $i < 100; $i++) {
 *     $manager->add(fn($n) => $n * 2, [$i]);
 * }
 *
 * // Run with max 4 concurrent processes
 * $results = $manager->run(maxConcurrent: 4);
 * ```
 */
final class ProcessManager implements ProcessManagerInterface
{
    /** @var array<ProcessInterface> */
    private array $processes = [];

    /** @var array<array{callable, array}> */
    private array $pending = [];

    /** @var array<mixed> */
    private array $results = [];

    private bool $shutdownRequested = false;
    private int $parentPid; // Track parent PID to prevent child cleanup

    public function __construct()
    {
        $this->parentPid = getmypid(); // Store parent PID
        $this->registerSignalHandlers();
    }

    /**
     * Add a task to the pool.
     *
     * @param callable $callback
     * @param array $args
     * @return ProcessInterface
     */
    public function add(callable $callback, array $args = []): ProcessInterface
    {
        $process = new ForkProcess($callback, $args);
        $this->pending[] = ['process' => $process, 'callback' => $callback, 'args' => $args];

        return $process;
    }

    /**
     * Run all processes with concurrency limit.
     *
     * @param int $maxConcurrent
     * @return array
     */
    public function run(int $maxConcurrent = 4): array
    {
        if (!ForkProcess::isSupported()) {
            // Fallback to synchronous execution
            return $this->runSynchronous();
        }

        $this->results = [];
        $pendingCount = count($this->pending);
        $processed = 0;

        // Start all processes up to concurrency limit
        while ($processed < $pendingCount) {
            // Check for shutdown signal
            if ($this->shutdownRequested) {
                $this->killAll(SIGTERM);
                break;
            }

            // Start new processes up to limit
            while (count($this->processes) < $maxConcurrent && $processed < $pendingCount) {
                $task = $this->pending[$processed];
                $process = $task['process'];

                // CRITICAL: start() MUST be called before getPid()!
                // PID is NULL until process is forked
                if (!$process->start()) {
                    $processed++;
                    continue;
                }

                // Now getPid() returns the actual PID
                $pid = $process->getPid();
                if ($pid !== null) {
                    $this->processes[$pid] = $process;
                }
                $processed++;
            }

            // If all tasks started, break immediately (no need to wait in loop)
            if ($processed >= $pendingCount) {
                break;
            }

            // Check for finished processes and start new ones
            // Non-blocking check to avoid unnecessary delays
            $this->collectFinishedProcesses();
        }

        // Wait for remaining processes
        $this->wait();

        // Clear pending tasks
        $this->pending = [];

        return $this->results;
    }

    /**
     * Wait for all processes to complete.
     * Performance: O(N) where N = number of processes
     * Optimized: Non-blocking check first, then blocking wait only when needed
     *
     * @return array
     */
    public function wait(): array
    {
        // Wait for all remaining processes
        while (count($this->processes) > 0) {
            foreach ($this->processes as $pid => $process) {
                // Non-blocking check first (fast path)
                if (!$process->isRunning()) {
                    // Process already finished - collect output immediately
                    $exitCode = $process->wait();
                    $output = $process->getOutput();
                    $this->results[] = $output;
                    unset($this->processes[$pid]);
                } else {
                    // Process still running - do blocking wait for this one
                    $exitCode = $process->wait();
                    $output = $process->getOutput();
                    $this->results[] = $output;
                    unset($this->processes[$pid]);
                    break; // Break to restart loop and check others
                }
            }
        }

        return $this->results;
    }

    /**
     * Collect finished processes without blocking.
     * Performance: O(N) where N = number of processes
     * Used in run() loop to start new processes as soon as slots become available
     *
     * @return void
     */
    private function collectFinishedProcesses(): void
    {
        foreach ($this->processes as $pid => $process) {
            // Non-blocking check
            if (!$process->isRunning()) {
                // Process finished - collect output
                $exitCode = $process->wait();
                $output = $process->getOutput();
                $this->results[] = $output;
                unset($this->processes[$pid]);
            }
        }
    }

    /**
     * Get number of running processes.
     *
     * @return int
     */
    public function getRunningCount(): int
    {
        $count = 0;

        foreach ($this->processes as $process) {
            if ($process->isRunning()) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Kill all processes.
     *
     * @param int $signal
     * @return void
     */
    public function killAll(int $signal = SIGTERM): void
    {
        foreach ($this->processes as $process) {
            if ($process->isRunning()) {
                $process->kill($signal);
            }
        }

        // Wait for all to die
        foreach ($this->processes as $process) {
            $process->wait();
        }

        $this->processes = [];
    }

    /**
     * Check if any process is running.
     *
     * @return bool
     */
    public function hasRunning(): bool
    {
        foreach ($this->processes as $process) {
            if ($process->isRunning()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Fallback to synchronous execution when PCNTL not available.
     *
     * @return array
     */
    private function runSynchronous(): array
    {
        $results = [];

        foreach ($this->pending as $task) {
            try {
                $result = ($task['callback'])(...$task['args']);
                $results[] = $result;
            } catch (\Throwable $e) {
                error_log("Task failed: " . $e->getMessage());
                $results[] = null;
            }
        }

        $this->pending = [];

        return $results;
    }

    /**
     * Register signal handlers for graceful shutdown.
     *
     * @return void
     */
    private function registerSignalHandlers(): void
    {
        if (!function_exists('pcntl_signal')) {
            return;
        }

        // Handle SIGTERM (graceful shutdown)
        pcntl_signal(SIGTERM, function () {
            $this->shutdownRequested = true;
        });

        // Handle SIGINT (Ctrl+C)
        pcntl_signal(SIGINT, function () {
            $this->shutdownRequested = true;
        });

        // Enable signal dispatching
        pcntl_async_signals(true);
    }

    /**
     * Cleanup on destruction.
     * Only runs in PARENT process to prevent child processes from killing siblings.
     */
    public function __destruct()
    {
        // CRITICAL: Only parent process should cleanup
        // Child processes have a copy of this object but should NOT kill processes
        if (getmypid() !== $this->parentPid) {
            return;
        }

        // Kill any remaining processes
        $this->killAll(SIGKILL);
    }
}
