<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Toporia\Framework\Queue\Job;
use Toporia\Framework\Queue\QueueManager;

/**
 * Test Job for Redis Queue
 */
class TestRedisJob extends Job
{
    public function __construct(
        private string $message,
        private int $number
    ) {
        parent::__construct();
    }

    public function handle(): void
    {
        echo "🚀 Processing job #{$this->number}: {$this->message}\n";
        sleep(1);
        echo "✅ Job #{$this->number} completed!\n";
    }
}

// Create Queue Manager
$manager = new QueueManager([
    'default' => 'redis',
    'connections' => [
        'redis' => [
            'driver' => 'redis',
            'host' => '127.0.0.1',
            'port' => 6379,
            'database' => 0,
            'prefix' => 'queues'
        ]
    ]
]);

echo "📦 Dispatching jobs to Redis queue...\n\n";

// Dispatch 5 test jobs
for ($i = 1; $i <= 5; $i++) {
    $job = new TestRedisJob("Test message {$i}", $i);
    $job->onQueue('default');

    $jobId = $manager->push($job, 'default');
    echo "✅ Job #{$i} dispatched: {$jobId}\n";
}

echo "\n📊 Queue size: " . $manager->size('default') . "\n";
echo "\n💡 Run worker with: php console queue:work --queue=default\n";
