<?php

declare(strict_types=1);

namespace App\Providers;

use Toporia\Framework\Container\ContainerInterface;
use Toporia\Framework\Foundation\ServiceProvider;
use Toporia\Framework\Schedule\Scheduler;

/**
 * Schedule Service Provider
 *
 * Define all scheduled tasks in one place.
 * This provider is loaded automatically and tasks are registered during boot phase.
 */
final class ScheduleServiceProvider extends ServiceProvider
{
    public function register(ContainerInterface $container): void
    {
        // Nothing to register
    }

    public function boot(ContainerInterface $container): void
    {
        $scheduler = $container->get(Scheduler::class);

        $this->defineSchedule($scheduler, $container);
    }

    /**
     * Define the application's scheduled tasks
     *
     * Add your scheduled tasks here.
     *
     * @param Scheduler $scheduler
     * @param ContainerInterface $container
     * @return void
     */
    private function defineSchedule(Scheduler $scheduler, ContainerInterface $container): void
    {
        // Example 1: Run a command every minute
        $scheduler->call(function () {
            echo "This runs every minute\n";
        })->everyMinute()->description('Example task - runs every minute');

        // Example 2: Clear old cache files daily at 2 AM
        $scheduler->call(function () {
            $directory = __DIR__ . '/../../../storage/temp';
            if (!is_dir($directory)) {
                return;
            }

            $files = glob($directory . '/*');
            $deletedCount = 0;

            foreach ($files as $file) {
                if (is_file($file) && filemtime($file) < strtotime('-7 days')) {
                    unlink($file);
                    $deletedCount++;
                }
            }

            echo "Deleted {$deletedCount} old temp files\n";
        })->dailyAt('02:00')->description('Cleanup old temp files');

        // Example 3: Clear cache every hour
        $scheduler->call(function () {
            // Use static accessor to get cache
            \Toporia\Framework\Support\Accessors\Cache::clear();
            echo "Cache cleared successfully\n";
        })->hourly()->description('Clear application cache');

        // Example 4: Database cleanup every day at midnight
        $scheduler->call(function () {
            // Delete old sessions, logs, etc.
            echo "Database cleanup completed\n";
        })->daily()->description('Database cleanup');

        // Example 5: Send weekly newsletter every Monday at 8 AM
        $scheduler->call(function () {
            // Queue newsletter jobs
            echo "Newsletter jobs queued\n";
        })->mondays()->dailyAt('08:00')->description('Queue weekly newsletter');

        // Example 6: Health check every 5 minutes (only during business hours)
        $scheduler->call(function () {
            $healthy = true;
            // Check application health
            if (!$healthy) {
                echo "ALERT: Health check failed!\n";
            }
        })->everyMinutes(5)
            ->when(fn() => date('H') >= 9 && date('H') < 18)
            ->description('Application health check (business hours)');

        // Example 7: Backup database every Sunday at midnight
        $scheduler->exec('echo "Database backup would run here"')
            ->sundays()
            ->description('Weekly database backup');

        // Example 8: Generate daily report (weekdays only)
        $scheduler->call(function () {
            echo "Daily report generated\n";
        })->weekdays()
            ->dailyAt('17:00')
            ->timezone('Asia/Ho_Chi_Minh')
            ->description('Generate daily report');

        // Add your custom scheduled tasks here...
    }
}
