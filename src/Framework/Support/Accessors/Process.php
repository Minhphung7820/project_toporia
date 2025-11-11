<?php

declare(strict_types=1);

namespace Toporia\Framework\Support\Accessors;

use Toporia\Framework\Foundation\ServiceAccessor;
use Toporia\Framework\Process\ProcessManager;
use Toporia\Framework\Process\ProcessPool;
use Toporia\Framework\Process\ForkProcess;
use Toporia\Framework\Process\Contracts\ProcessInterface;

/**
 * Process Facade
 *
 * Static accessor for multi-process execution system.
 *
 * Example:
 * ```php
 * // Run tasks in parallel
 * $results = Process::run([
 *     fn() => heavyTask1(),
 *     fn() => heavyTask2(),
 * ], maxConcurrent: 4);
 *
 * // Process pool operations
 * $results = Process::map([1, 2, 3], fn($n) => $n * 2);
 * $evens = Process::filter([1, 2, 3, 4], fn($n) => $n % 2 === 0);
 * $sum = Process::reduce([1, 2, 3, 4], fn($acc, $n) => $acc + $n, 0);
 * ```
 *
 * @method static ProcessManager manager()
 * @method static ProcessPool pool(int $workerCount = null)
 */
final class Process extends ServiceAccessor
{
    protected static function getServiceName(): string
    {
        return 'process.manager';
    }

    /**
     * Get ProcessManager instance.
     *
     * @return ProcessManager
     */
    public static function manager(): ProcessManager
    {
        if (function_exists('app')) {
            try {
                return app('process.manager');
            } catch (\Throwable $e) {
                // Container not available, create new instance
            }
        }

        return new ProcessManager();
    }

    /**
     * Get ProcessPool instance.
     *
     * @param int|null $workerCount Number of workers (null = auto-detect CPU cores)
     * @return ProcessPool
     */
    public static function pool(?int $workerCount = null): ProcessPool
    {
        if ($workerCount === null && function_exists('app')) {
            try {
                return app('process.pool');
            } catch (\Throwable $e) {
                // Container not available, auto-detect cores
                $workerCount = static::getCpuCores();
            }
        }

        if ($workerCount === null) {
            $workerCount = static::getCpuCores();
        }

        return new ProcessPool(workerCount: $workerCount);
    }

    /**
     * Run tasks in parallel with concurrency limit.
     *
     * @param array<callable> $tasks Array of callables to execute
     * @param int $maxConcurrent Maximum concurrent processes
     * @return array Results in order of tasks
     */
    public static function run(array $tasks, int $maxConcurrent = 4): array
    {
        $manager = new ProcessManager();

        foreach ($tasks as $task) {
            $manager->add($task);
        }

        return $manager->run($maxConcurrent);
    }

    /**
     * Map function over array in parallel.
     *
     * @param array $items Items to process
     * @param callable $callback Function to apply to each item
     * @param int|null $workerCount Number of workers (null = auto-detect)
     * @return array Mapped results
     */
    public static function map(array $items, callable $callback, ?int $workerCount = null): array
    {
        return static::pool($workerCount)->map($items, $callback);
    }

    /**
     * Filter array in parallel.
     *
     * @param array $items Items to filter
     * @param callable $callback Predicate function
     * @param int|null $workerCount Number of workers (null = auto-detect)
     * @return array Filtered items
     */
    public static function filter(array $items, callable $callback, ?int $workerCount = null): array
    {
        return static::pool($workerCount)->filter($items, $callback);
    }

    /**
     * Reduce array in parallel.
     *
     * @param array $items Items to reduce
     * @param callable $callback Reducer function
     * @param mixed $initial Initial value
     * @param int|null $workerCount Number of workers (null = auto-detect)
     * @return mixed Reduced value
     */
    public static function reduce(array $items, callable $callback, mixed $initial = null, ?int $workerCount = null): mixed
    {
        return static::pool($workerCount)->reduce($items, $callback, $initial);
    }

    /**
     * Create a single fork process.
     *
     * @param callable $callback Function to execute in child process
     * @param array $args Arguments to pass to callback
     * @return ProcessInterface
     */
    public static function fork(callable $callback, array $args = []): ProcessInterface
    {
        return new ForkProcess($callback, $args);
    }

    /**
     * Check if PCNTL fork is supported.
     *
     * @return bool
     */
    public static function isSupported(): bool
    {
        return ForkProcess::isSupported();
    }

    /**
     * Get number of CPU cores.
     *
     * @return int
     */
    public static function getCpuCores(): int
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return (int) ($_ENV['NUMBER_OF_PROCESSORS'] ?? 4);
        }

        $output = shell_exec('nproc 2>/dev/null || sysctl -n hw.ncpu 2>/dev/null || echo 4');
        return max(1, (int) trim((string) $output));
    }
}
