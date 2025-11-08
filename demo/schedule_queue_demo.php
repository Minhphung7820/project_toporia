<?php

declare(strict_types=1);

/**
 * Schedule & Queue Demo
 *
 * Demonstrates how Schedule and Queue work together.
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';

use Toporia\Framework\Support\Accessors\{Schedule, Queue};
use App\Jobs\ExampleJob;

echo "=== Schedule & Queue Integration Demo ===\n\n";

// ============================================================================
// 1. Queue System Demo
// ============================================================================

echo "1. Queue System:\n";
echo "   " . str_repeat("-", 60) . "\n\n";

echo "   a) Push job to queue:\n";
$job1 = new ExampleJob('Hello from job 1', ['user_id' => 123]);
$jobId1 = Queue::push($job1);
echo "      Pushed job: {$jobId1}\n\n";

echo "   b) Push job to specific queue:\n";
$job2 = new ExampleJob('High priority job', ['priority' => 'high']);
$jobId2 = Queue::push($job2, 'high-priority');
echo "      Pushed job to 'high-priority' queue: {$jobId2}\n\n";

echo "   c) Push delayed job (5 seconds):\n";
$job3 = new ExampleJob('Delayed job', ['delay' => 5]);
$jobId3 = Queue::later($job3, 5);
echo "      Pushed delayed job: {$jobId3}\n\n";

echo "   To process these jobs, run:\n";
echo "     php worker.php default\n";
echo "     php worker.php high-priority\n\n";

// ============================================================================
// 2. Schedule System Demo
// ============================================================================

echo "2. Schedule System:\n";
echo "   " . str_repeat("-", 60) . "\n\n";

echo "   a) Define scheduled tasks:\n\n";

// Task 1: Every minute
Schedule::call(function() {
    echo "      → Running every-minute task\n";
})->everyMinute()->description('Every minute task');
echo "      ✓ Every minute task scheduled\n";

// Task 2: Every 5 minutes
Schedule::call(function() {
    echo "      → Running every-5-minutes task\n";
})->everyMinutes(5)->description('Every 5 minutes task');
echo "      ✓ Every 5 minutes task scheduled\n";

// Task 3: Hourly
Schedule::call(function() {
    echo "      → Running hourly task\n";
})->hourly()->description('Hourly task');
echo "      ✓ Hourly task scheduled\n";

// Task 4: Daily at specific time
Schedule::call(function() {
    echo "      → Running daily task\n";
})->dailyAt('02:00')->description('Daily cleanup at 2 AM');
echo "      ✓ Daily task scheduled (2:00 AM)\n";

// Task 5: Conditional task
Schedule::call(function() {
    echo "      → Running conditional task\n";
})->everyMinute()
  ->when(fn() => date('s') < 30) // Only first 30 seconds of minute
  ->description('Conditional task');
echo "      ✓ Conditional task scheduled\n\n";

echo "   b) List all scheduled tasks:\n";
$tasks = Schedule::getInstance()->listTasks();
foreach ($tasks as $i => $task) {
    echo "      Task " . ($i + 1) . ": {$task['description']}\n";
    echo "         Expression: {$task['expression']}\n";
}
echo "\n";

// ============================================================================
// 3. Integration: Schedule + Queue
// ============================================================================

echo "3. Integration - Schedule Jobs to Queue:\n";
echo "   " . str_repeat("-", 60) . "\n\n";

echo "   a) Schedule that queues jobs:\n";
Schedule::call(function() {
    echo "      → Scheduler is queuing jobs...\n";

    // Queue multiple jobs
    for ($i = 1; $i <= 3; $i++) {
        $job = new ExampleJob("Scheduled job #{$i}", ['scheduled' => true]);
        $jobId = Queue::push($job);
        echo "         Queued job: {$jobId}\n";
    }
})->everyMinute()->description('Queue batch jobs');
echo "      ✓ Job-queuing task scheduled\n\n";

echo "   b) Schedule that uses Queue accessor:\n";
Schedule::call(function() {
    // Queue high-priority job
    Queue::push(
        new ExampleJob('High priority from scheduler'),
        'high-priority'
    );
})->everyMinutes(5)->description('Queue high-priority job');
echo "      ✓ High-priority queuing task scheduled\n\n";

// ============================================================================
// 4. Run Due Tasks (Demo)
// ============================================================================

echo "4. Run Due Tasks Now (Demo):\n";
echo "   " . str_repeat("-", 60) . "\n\n";

echo "   NOTE: Only 'every minute' tasks will run in this demo\n";
echo "   because other tasks have different schedules.\n\n";

echo "   Running due tasks...\n";
$count = Schedule::runDueTasks();
echo "\n   Executed {$count} task(s)\n\n";

// ============================================================================
// 5. Cron Expression Examples
// ============================================================================

echo "5. Cron Expression Examples:\n";
echo "   " . str_repeat("=", 60) . "\n\n";

$examples = [
    ['* * * * *', 'Every minute'],
    ['*/5 * * * *', 'Every 5 minutes'],
    ['0 * * * *', 'Every hour'],
    ['0 0 * * *', 'Every day at midnight'],
    ['0 9 * * 1-5', 'Weekdays at 9 AM'],
    ['0 0 * * 0', 'Every Sunday at midnight'],
    ['0 0 1 * *', 'First day of month at midnight'],
    ['30 14 * * *', 'Every day at 2:30 PM'],
];

foreach ($examples as $example) {
    [$expression, $description] = $example;
    echo "   " . str_pad($expression, 20) . " → {$description}\n";
}

echo "\n";

// ============================================================================
// 6. Production Usage
// ============================================================================

echo "6. Production Setup:\n";
echo "   " . str_repeat("=", 60) . "\n\n";

echo "   A) Crontab (for Schedule):\n";
echo "      ----------------------------------------------------------\n";
echo "      # Edit crontab:\n";
echo "      crontab -e\n\n";
echo "      # Add this line:\n";
echo "      * * * * * cd " . dirname(__DIR__) . " && php schedule.php >> /dev/null 2>&1\n\n";

echo "   B) Worker (for Queue):\n";
echo "      ----------------------------------------------------------\n";
echo "      # Run worker in foreground (development):\n";
echo "      php worker.php default\n\n";
echo "      # Run worker in background (production):\n";
echo "      nohup php worker.php default >> storage/logs/worker.log 2>&1 &\n\n";

echo "   C) Supervisor (recommended for production):\n";
echo "      ----------------------------------------------------------\n";
echo "      sudo cp deployment/supervisor-queue-worker.conf /etc/supervisor/conf.d/\n";
echo "      sudo supervisorctl reread\n";
echo "      sudo supervisorctl update\n";
echo "      sudo supervisorctl start queue-worker-default:*\n\n";

echo "   D) Systemd Service:\n";
echo "      ----------------------------------------------------------\n";
echo "      sudo cp deployment/systemd-queue-worker@.service /etc/systemd/system/\n";
echo "      sudo systemctl daemon-reload\n";
echo "      sudo systemctl enable queue-worker@default\n";
echo "      sudo systemctl start queue-worker@default\n\n";

// ============================================================================
// 7. Testing
// ============================================================================

echo "7. Testing:\n";
echo "   " . str_repeat("=", 60) . "\n\n";

echo "   A) Test Schedule:\n";
echo "      # Run schedule manually\n";
echo "      php schedule.php\n\n";

echo "   B) Test Queue:\n";
echo "      # Push test job\n";
echo "      php -r \"require 'vendor/autoload.php'; \$app = require 'bootstrap/app.php'; \\\n";
echo "             use Toporia\\Framework\\Support\\Accessors\\Queue; \\\n";
echo "             Queue::push(new App\\Jobs\\ExampleJob('test'));\"\n\n";
echo "      # Process with worker\n";
echo "      php worker.php default 1  # Process 1 job then exit\n\n";

echo "   C) Monitor Queue:\n";
echo "      # Check queue size (if using DatabaseQueue)\n";
echo "      php -r \"require 'vendor/autoload.php'; \$app = require 'bootstrap/app.php'; \\\n";
echo "             use Toporia\\Framework\\Support\\Accessors\\DB; \\\n";
echo "             echo DB::table('jobs')->count() . ' jobs pending';\"\n\n";

// ============================================================================
// Summary
// ============================================================================

echo "8. Summary:\n";
echo "   " . str_repeat("=", 60) . "\n\n";

echo "   ✓ Schedule: Define tasks that run on cron schedule\n";
echo "   ✓ Queue: Push jobs to background queue for async processing\n";
echo "   ✓ Integration: Schedule can queue jobs for heavy work\n";
echo "   ✓ Production: Use crontab + supervisor/systemd\n";
echo "   ✓ Monitoring: Check logs, queue size, worker health\n\n";

echo "   Read more:\n";
echo "     docs/SCHEDULE_AND_QUEUE.md\n";
echo "     demo/schedule_queue_demo.php (this file)\n\n";

echo "=== Demo Complete ===\n";
