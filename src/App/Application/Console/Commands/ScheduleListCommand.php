<?php

declare(strict_types=1);

namespace App\Application\Console\Commands;

use Toporia\Framework\Console\Command;
use Toporia\Framework\Schedule\Scheduler;

/**
 * Schedule List Command
 *
 * Display all scheduled tasks with their cron expressions.
 *
 * Usage:
 *   php console schedule:list
 */
final class ScheduleListCommand extends Command
{
    protected string $signature = 'schedule:list';
    protected string $description = 'List all scheduled tasks';

    public function __construct(
        private readonly Scheduler $scheduler
    ) {}

    public function handle(): int
    {
        $tasks = $this->scheduler->listTasks();

        if (empty($tasks)) {
            $this->info('No scheduled tasks defined.');
            return 0;
        }

        $this->info('Scheduled Tasks:');
        $this->newLine();

        // Prepare table data
        $headers = ['Expression', 'Description', 'Next Run'];
        $rows = [];

        foreach ($tasks as $task) {
            $rows[] = [
                $task['expression'],
                $task['description'] ?: '(no description)',
                $this->getNextRunTime($task['expression'])
            ];
        }

        $this->table($headers, $rows);

        $this->newLine();
        $this->info("Total: " . count($tasks) . " task(s)");
        $this->info("Current time: " . date('Y-m-d H:i:s'));

        return 0;
    }

    /**
     * Get next run time for cron expression (simplified)
     *
     * @param string $expression
     * @return string
     */
    private function getNextRunTime(string $expression): string
    {
        // Simple approximation - for exact calculation, use cron-expression library
        if ($expression === '* * * * *') {
            return 'Every minute';
        }

        if (preg_match('/^\*\/(\d+) \* \* \* \*$/', $expression, $matches)) {
            return "Every {$matches[1]} minutes";
        }

        if (preg_match('/^0 \* \* \* \*$/', $expression)) {
            return 'Every hour';
        }

        if (preg_match('/^0 0 \* \* \*$/', $expression)) {
            return 'Daily at midnight';
        }

        if (preg_match('/^0 (\d+) \* \* \*$/', $expression, $matches)) {
            return "Daily at {$matches[1]}:00";
        }

        return '(see expression)';
    }
}
