<?php

declare(strict_types=1);

namespace Toporia\Framework\Console\Commands;

use Toporia\Framework\Console\Command;
use Toporia\Framework\Container\ContainerInterface;
use Toporia\Framework\Queue\QueueManagerInterface;
use Toporia\Framework\Queue\Worker;

/**
 * Queue Work Command
 *
 * Process jobs from the queue with graceful shutdown support.
 *
 * Usage:
 *   php console queue:work
 *   php console queue:work --queue=emails
 *   php console queue:work --max-jobs=100 --sleep=5
 *   php console queue:work --stop-when-empty
 */
final class QueueWorkCommand extends Command
{
    protected string $signature = 'queue:work';
    protected string $description = 'Process jobs from the queue';

    private bool $shouldQuit = false;

    public function __construct(
        private readonly QueueManagerInterface $queueManager,
        private readonly ContainerInterface $container
    ) {}

    public function handle(): int
    {
        // Parse options
        $queueName = $this->option('queue', 'default');
        $maxJobs = (int) $this->option('max-jobs', 0); // 0 = unlimited
        $sleep = (int) $this->option('sleep', 3);
        $stopWhenEmpty = $this->hasOption('stop-when-empty');

        // Get queue instance
        try {
            $queue = $this->queueManager->driver();
        } catch (\Exception $e) {
            $this->error("Failed to initialize queue: {$e->getMessage()}");
            return 1;
        }

        // Create worker with container for dependency injection
        $worker = new Worker($queue, $this->container, $maxJobs, $sleep);

        // Setup graceful shutdown
        $this->setupSignalHandlers($worker);

        // Display configuration
        $this->displayHeader($queueName, $maxJobs, $sleep, $stopWhenEmpty);

        // Start processing
        try {
            if ($stopWhenEmpty) {
                $this->processUntilEmpty($worker, $queueName);
            } else {
                $worker->work($queueName);
            }
        } catch (\Throwable $e) {
            $this->newLine();
            $this->error("Worker crashed: {$e->getMessage()}");
            $this->error($e->getTraceAsString());
            return 1;
        }

        // Display summary
        $this->displaySummary($worker);

        return 0;
    }

    /**
     * Setup signal handlers for graceful shutdown
     *
     * @param Worker $worker
     * @return void
     */
    private function setupSignalHandlers(Worker $worker): void
    {
        if (!function_exists('pcntl_async_signals')) {
            return;
        }

        pcntl_async_signals(true);

        $shutdown = function() use ($worker) {
            $this->newLine(2);
            $this->warn("Received shutdown signal...");
            $this->info("Waiting for current job to finish...");
            $worker->stop();
            $this->shouldQuit = true;
        };

        pcntl_signal(SIGTERM, $shutdown); // Kill signal
        pcntl_signal(SIGINT, $shutdown);  // Ctrl+C
    }

    /**
     * Process jobs until queue is empty
     *
     * @param Worker $worker
     * @param string $queueName
     * @return void
     */
    private function processUntilEmpty(Worker $worker, string $queueName): void
    {
        $processed = 0;

        while (!$this->shouldQuit) {
            $job = $worker->getQueue()->pop($queueName);

            if ($job === null) {
                $this->info("Queue is empty. Stopping.");
                break;
            }

            try {
                // Use container to call handle() with dependency injection
                $this->container->call([$job, 'handle']);
                $this->success("Job processed successfully: " . get_class($job));
                $processed++;
            } catch (\Throwable $e) {
                $this->error("Job failed: {$e->getMessage()}");
                $job->failed($e);
            }
        }

        $this->info("Processed {$processed} job(s)");
    }

    /**
     * Display header with configuration
     *
     * @param string $queueName
     * @param int $maxJobs
     * @param int $sleep
     * @param bool $stopWhenEmpty
     * @return void
     */
    private function displayHeader(
        string $queueName,
        int $maxJobs,
        int $sleep,
        bool $stopWhenEmpty
    ): void {
        $this->line('=', 80);
        $this->writeln('Queue Worker Started');
        $this->line('=', 80);
        $this->writeln("Queue:     {$queueName}");
        $this->writeln("Max Jobs:  " . ($maxJobs > 0 ? $maxJobs : 'unlimited'));
        $this->writeln("Sleep:     {$sleep} second(s)");
        $this->writeln("Stop when empty: " . ($stopWhenEmpty ? 'yes' : 'no'));
        $this->writeln("Time:      " . date('Y-m-d H:i:s'));
        $this->writeln("PID:       " . getmypid());
        $this->line('=', 80);
        $this->newLine();
    }

    /**
     * Display summary after worker stops
     *
     * @param Worker $worker
     * @return void
     */
    private function displaySummary(Worker $worker): void
    {
        $this->newLine();
        $this->line('=', 80);
        $this->writeln('Worker Stopped');
        $this->writeln("Processed: {$worker->getProcessedCount()} job(s)");
        $this->line('=', 80);
    }
}
