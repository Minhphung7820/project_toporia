<?php

declare(strict_types=1);

/**
 * Queue Worker
 *
 * Process jobs from the queue.
 *
 * Usage:
 *   php worker.php [queue] [maxJobs] [sleep]
 *
 * Examples:
 *   php worker.php                    # Default queue, unlimited jobs, 3s sleep
 *   php worker.php emails             # Process 'emails' queue
 *   php worker.php default 100        # Process max 100 jobs then exit
 *   php worker.php default 0 5        # Unlimited jobs, 5s sleep
 *
 * Production (background):
 *   nohup php worker.php default >> storage/logs/worker.log 2>&1 &
 *
 * With Supervisor:
 *   See docs/SCHEDULE_AND_QUEUE.md for supervisor configuration
 */

require __DIR__ . '/vendor/autoload.php';

// Bootstrap application
$app = require __DIR__ . '/bootstrap/app.php';

use Toporia\Framework\Queue\Worker;
use Toporia\Framework\Support\Accessors\Queue;

// ============================================================================
// Configuration from CLI arguments
// ============================================================================

$queueName = $argv[1] ?? 'default';
$maxJobs = isset($argv[2]) ? (int)$argv[2] : 0; // 0 = unlimited
$sleep = isset($argv[3]) ? (int)$argv[3] : 3;   // seconds

// ============================================================================
// Get Queue Instance
// ============================================================================

try {
    // Get queue manager and select driver
    $queueManager = Queue::getInstance();
    $queue = $queueManager; // Uses default driver from config

    // Or use specific driver:
    // $queue = $queueManager->driver('database');
    // $queue = $queueManager->driver('redis');
} catch (\Exception $e) {
    echo "ERROR: Failed to initialize queue: {$e->getMessage()}\n";
    exit(1);
}

// ============================================================================
// Create Worker
// ============================================================================

$worker = new Worker($queue, $maxJobs, $sleep);

// ============================================================================
// Handle Graceful Shutdown
// ============================================================================

if (function_exists('pcntl_async_signals')) {
    pcntl_async_signals(true);

    $shutdown = function() use ($worker) {
        echo "\n\nReceived shutdown signal...\n";
        $worker->stop();
    };

    pcntl_signal(SIGTERM, $shutdown); // Kill signal
    pcntl_signal(SIGINT, $shutdown);  // Ctrl+C
}

// ============================================================================
// Display Configuration
// ============================================================================

echo str_repeat('=', 80) . "\n";
echo "Queue Worker Started\n";
echo str_repeat('=', 80) . "\n";
echo "Queue:     {$queueName}\n";
echo "Max Jobs:  " . ($maxJobs > 0 ? $maxJobs : 'unlimited') . "\n";
echo "Sleep:     {$sleep} second(s)\n";
echo "Time:      " . date('Y-m-d H:i:s') . "\n";
echo "PID:       " . getmypid() . "\n";
echo str_repeat('=', 80) . "\n\n";

// ============================================================================
// Start Processing
// ============================================================================

try {
    $worker->work($queueName);
} catch (\Throwable $e) {
    echo "\n\nERROR: Worker crashed: {$e->getMessage()}\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

echo "\n" . str_repeat('=', 80) . "\n";
echo "Worker Stopped\n";
echo "Processed: {$worker->getProcessedCount()} job(s)\n";
echo str_repeat('=', 80) . "\n";
