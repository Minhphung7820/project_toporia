<?php

declare(strict_types=1);

namespace Toporia\Framework\Schedule;

use Toporia\Framework\Container\ContainerInterface;

/**
 * Task Scheduler
 *
 * Manages scheduled tasks (cron-like functionality).
 * Provides fluent interface for defining task schedules.
 */
final class Scheduler
{
    /**
     * @var ScheduledTask[]
     */
    private array $tasks = [];

    /**
     * @var ContainerInterface|null
     */
    private ?ContainerInterface $container = null;

    /**
     * @var MutexInterface|null
     */
    private ?MutexInterface $mutex = null;

    /**
     * Set container for dependency injection
     *
     * @param ContainerInterface $container
     * @return void
     */
    public function setContainer(ContainerInterface $container): void
    {
        $this->container = $container;
    }

    /**
     * Set mutex for overlap prevention
     *
     * @param MutexInterface $mutex
     * @return void
     */
    public function setMutex(MutexInterface $mutex): void
    {
        $this->mutex = $mutex;
    }

    /**
     * Schedule a callback to run
     *
     * @param callable $callback
     * @param string|null $description
     * @return ScheduledTask
     */
    public function call(callable $callback, ?string $description = null): ScheduledTask
    {
        $task = new ScheduledTask($callback, $description);
        $this->tasks[] = $task;
        return $task;
    }

    /**
     * Schedule a command to run
     *
     * @param string $command Shell command
     * @param string|null $description
     * @return ScheduledTask
     */
    public function exec(string $command, ?string $description = null): ScheduledTask
    {
        return $this->call(function () use ($command) {
            exec($command);
        }, $description ?? "Execute: {$command}");
    }

    /**
     * Schedule a job to be queued
     *
     * @param string $jobClass Job class name
     * @param string|null $description
     * @return ScheduledTask
     */
    public function job(string $jobClass, ?string $description = null): ScheduledTask
    {
        return $this->call(function () use ($jobClass) {
            $job = new $jobClass();
            app('queue')->push($job);
        }, $description ?? "Queue job: {$jobClass}");
    }

    /**
     * Schedule a console command to run
     *
     * @param string $command Command signature (e.g., 'cache:clear', 'migrate')
     * @param array $options Command options (e.g., ['--store' => 'redis'])
     * @param string|null $description
     * @return ScheduledTask
     */
    public function command(string $command, array $options = [], ?string $description = null): ScheduledTask
    {
        return $this->call(function () use ($command, $options) {
            if (!$this->container) {
                throw new \RuntimeException('Container must be set to run console commands');
            }

            // Get console application
            $console = $this->container->get(\Toporia\Framework\Console\Application::class);

            // Build arguments array
            $arguments = [$command];

            // Add options
            foreach ($options as $key => $value) {
                if (is_int($key)) {
                    // Flag without value (e.g., '--force')
                    $arguments[] = $value;
                } else {
                    // Option with value (e.g., '--store=redis')
                    if (str_starts_with($key, '--')) {
                        $arguments[] = "{$key}={$value}";
                    } else {
                        $arguments[] = "--{$key}={$value}";
                    }
                }
            }

            // Run command
            $console->run($arguments);
        }, $description ?? "Run command: {$command}");
    }

    /**
     * Get all scheduled tasks
     *
     * @return ScheduledTask[]
     */
    public function getTasks(): array
    {
        return $this->tasks;
    }

    /**
     * Get tasks that are due to run
     *
     * @param \DateTime|null $currentTime
     * @return ScheduledTask[]
     */
    public function getDueTasks(?\DateTime $currentTime = null): array
    {
        $currentTime = $currentTime ?? new \DateTime();
        $dueTasks = [];

        foreach ($this->tasks as $task) {
            if ($task->isDue($currentTime)) {
                $dueTasks[] = $task;
            }
        }

        return $dueTasks;
    }

    /**
     * Run all tasks that are due
     *
     * @param \DateTime|null $currentTime
     * @return int Number of tasks executed
     */
    public function runDueTasks(?\DateTime $currentTime = null): int
    {
        $dueTasks = $this->getDueTasks($currentTime);
        $count = 0;

        foreach ($dueTasks as $task) {
            // Check for overlap prevention
            if ($task->hasOverlapPrevention() && $this->mutex) {
                $mutexName = $task->getMutexName();

                // Skip if task is already running
                if ($this->mutex->exists($mutexName)) {
                    echo "Skipping task (already running): {$task->getDescription()}\n";
                    continue;
                }

                // Acquire mutex lock
                if (!$this->mutex->create($mutexName, $task->getExpiresAfter())) {
                    echo "Failed to acquire lock for task: {$task->getDescription()}\n";
                    continue;
                }

                // Execute task
                try {
                    echo "Running task: {$task->getDescription()}\n";

                    if ($task->shouldRunInBackground()) {
                        $this->runTaskInBackground($task, $mutexName);
                    } else {
                        $task->execute();
                        $this->mutex->forget($mutexName);
                    }

                    echo "Task completed: {$task->getDescription()}\n";
                    $count++;
                } catch (\Throwable $e) {
                    $this->mutex->forget($mutexName);
                    echo "Task failed: {$task->getDescription()} - {$e->getMessage()}\n";
                }
            } else {
                // No overlap prevention - just run the task
                try {
                    echo "Running task: {$task->getDescription()}\n";

                    if ($task->shouldRunInBackground()) {
                        $this->runTaskInBackground($task);
                    } else {
                        $task->execute();
                    }

                    echo "Task completed: {$task->getDescription()}\n";
                    $count++;
                } catch (\Throwable $e) {
                    echo "Task failed: {$task->getDescription()} - {$e->getMessage()}\n";
                }
            }
        }

        return $count;
    }

    /**
     * Run task in background
     *
     * @param ScheduledTask $task
     * @param string|null $mutexName
     * @return void
     */
    private function runTaskInBackground(ScheduledTask $task, ?string $mutexName = null): void
    {
        // Fork process to run in background (Unix-like systems only)
        if (function_exists('pcntl_fork')) {
            $pid = pcntl_fork();

            if ($pid === -1) {
                throw new \RuntimeException('Failed to fork process');
            }

            if ($pid === 0) {
                // Child process
                try {
                    $task->execute();
                } catch (\Throwable $e) {
                    error_log("Background task failed: {$e->getMessage()}");
                } finally {
                    if ($mutexName && $this->mutex) {
                        $this->mutex->forget($mutexName);
                    }
                }
                exit(0);
            }

            // Parent process continues
            echo "Task started in background (PID: {$pid})\n";
        } else {
            // Fallback: Use shell background execution
            $phpBinary = PHP_BINARY;
            $script = $_SERVER['SCRIPT_FILENAME'] ?? 'console';

            $command = sprintf(
                '%s %s schedule:run-task %s > /dev/null 2>&1 &',
                escapeshellarg($phpBinary),
                escapeshellarg($script),
                escapeshellarg($task->getDescription())
            );

            exec($command);
            echo "Task started in background (shell)\n";
        }
    }

    /**
     * List all scheduled tasks
     *
     * @return array
     */
    public function listTasks(): array
    {
        $list = [];

        foreach ($this->tasks as $task) {
            $list[] = [
                'description' => $task->getDescription(),
                'expression' => $task->getExpression(),
            ];
        }

        return $list;
    }

    /**
     * Clear all scheduled tasks
     *
     * @return void
     */
    public function clear(): void
    {
        $this->tasks = [];
    }
}
