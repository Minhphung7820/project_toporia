<?php

declare(strict_types=1);

namespace Toporia\Framework\Schedule;

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
            try {
                echo "Running task: {$task->getDescription()}\n";
                $task->execute();
                echo "Task completed: {$task->getDescription()}\n";
                $count++;
            } catch (\Throwable $e) {
                echo "Task failed: {$task->getDescription()} - {$e->getMessage()}\n";
            }
        }

        return $count;
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
