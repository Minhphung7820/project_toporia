<?php

declare(strict_types=1);

namespace App\Application\Console\Commands;

use Toporia\Framework\Console\Command;
use Toporia\Framework\Schedule\Scheduler;

/**
 * Schedule Run Command
 *
 * Run all scheduled tasks that are due.
 * This command should be run every minute by cron:
 *   * * * * * cd /path/to/project && php console schedule:run >> storage/logs/schedule.log 2>&1
 *
 * Usage:
 *   php console schedule:run
 *   php console schedule:run --verbose
 */
final class ScheduleRunCommand extends Command
{
    protected string $signature = 'schedule:run';
    protected string $description = 'Run all scheduled tasks that are due';

    public function __construct(
        private readonly Scheduler $scheduler
    ) {}

    public function handle(): int
    {
        $verbose = $this->hasOption('verbose') || $this->hasOption('v');

        // Display header
        $this->displayHeader($verbose);

        // Run due tasks
        try {
            $tasksRun = $this->scheduler->runDueTasks();

            $this->newLine();
            if ($tasksRun === 0) {
                $this->info("No scheduled tasks are due to run.");
            } else {
                $this->success("Executed {$tasksRun} task(s) successfully.");
            }
        } catch (\Throwable $e) {
            $this->newLine();
            $this->error("Scheduler error: {$e->getMessage()}");

            if ($verbose) {
                $this->error($e->getTraceAsString());
            }

            return 1;
        }

        // Display footer
        if ($verbose) {
            $this->displayFooter();
        }

        return 0;
    }

    /**
     * Display header
     *
     * @param bool $verbose
     * @return void
     */
    private function displayHeader(bool $verbose): void
    {
        if (!$verbose) {
            return;
        }

        $this->line('=', 80);
        $this->writeln('Schedule Runner');
        $this->line('=', 80);
        $this->writeln('Time: ' . date('Y-m-d H:i:s'));
        $this->writeln('Timezone: ' . date_default_timezone_get());
        $this->line('=', 80);
    }

    /**
     * Display footer
     *
     * @return void
     */
    private function displayFooter(): void
    {
        $this->newLine();
        $this->line('=', 80);
        $this->writeln('Completed at: ' . date('Y-m-d H:i:s'));
        $this->line('=', 80);
    }
}
