<?php

declare(strict_types=1);

/**
 * Schedule Runner
 *
 * This file should be run by cron every minute:
 * * * * * * cd /path/to/project && php schedule.php >> /dev/null 2>&1
 *
 * Or with logging:
 * * * * * * cd /path/to/project && php schedule.php >> storage/logs/schedule.log 2>&1
 */

require __DIR__ . '/vendor/autoload.php';

// Bootstrap application
$app = require __DIR__ . '/bootstrap/app.php';

use Toporia\Framework\Support\Accessors\{Schedule, Queue, Cache, DB};

// ============================================================================
// Define Scheduled Tasks
// ============================================================================

// Example 1: Cleanup old files daily at 2 AM
Schedule::call(function() {
    $directory = __DIR__ . '/storage/temp';
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

// Example 2: Clear cache every hour
Schedule::call(function() {
    // Get cache stats before clearing
    $keys = ['users', 'posts', 'settings']; // Example keys

    Cache::clear();
    echo "Cache cleared successfully\n";
})->hourly()->description('Clear application cache');

// Example 3: Database cleanup every day at midnight
Schedule::call(function() {
    // Delete old sessions (example)
    // DB::table('sessions')->where('last_activity', '<', time() - 86400)->delete();

    echo "Database cleanup completed\n";
})->daily()->description('Database cleanup');

// Example 4: Send weekly newsletter every Monday at 8 AM
Schedule::call(function() {
    // Queue newsletter jobs for all subscribed users
    // $users = User::where('subscribed', true)->get();
    // foreach ($users as $user) {
    //     Queue::push(new SendNewsletterJob($user));
    // }

    echo "Newsletter jobs queued\n";
})->mondays()->dailyAt('08:00')->description('Queue weekly newsletter');

// Example 5: Backup database every Sunday at midnight
Schedule::exec('echo "Database backup would run here"')
    ->sundays()
    ->description('Weekly database backup');

// Example 6: Health check every 5 minutes (only during business hours)
Schedule::call(function() {
    // Check if application is healthy
    $healthy = true;

    // Check database
    // try {
    //     DB::getPdo()->query('SELECT 1');
    // } catch (\Exception $e) {
    //     $healthy = false;
    // }

    if (!$healthy) {
        echo "ALERT: Health check failed!\n";
        // Send alert email/SMS
    }
})->everyMinutes(5)
  ->when(fn() => date('H') >= 9 && date('H') < 18) // Business hours only
  ->description('Application health check');

// Example 7: Process pending tasks every minute
Schedule::call(function() {
    // Process any pending background tasks
    echo "Processing pending tasks\n";
})->everyMinute()->description('Process pending tasks');

// Example 8: Generate daily report (weekdays only)
Schedule::call(function() {
    // Generate report
    echo "Daily report generated\n";
})->weekdays()
  ->dailyAt('17:00') // 5 PM
  ->timezone('Asia/Ho_Chi_Minh')
  ->description('Generate daily report');

// ============================================================================
// Run Due Tasks
// ============================================================================

echo str_repeat('=', 80) . "\n";
echo "Schedule Runner - " . date('Y-m-d H:i:s') . "\n";
echo str_repeat('=', 80) . "\n";

try {
    $count = Schedule::runDueTasks();
    echo "\nExecuted {$count} task(s)\n";
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

echo str_repeat('=', 80) . "\n";
