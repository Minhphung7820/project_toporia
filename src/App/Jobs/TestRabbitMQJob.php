<?php

declare(strict_types=1);

namespace App\Jobs;

use Toporia\Framework\Queue\Job;
use Toporia\Framework\Support\Accessors\Log;

/**
 * Test RabbitMQ Job
 *
 * Simple job to test RabbitMQ queue functionality.
 */
final class TestRabbitMQJob extends Job
{
    public function __construct(
        private readonly string $message = 'Hello from RabbitMQ!'
    ) {
        parent::__construct();
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        Log::info("RabbitMQ Job executed: {$this->message}");
        Log::info("Job ID: {$this->getId()}");
        Log::info("Queue: {$this->getQueue()}");
        Log::info("Attempts: {$this->attempts()}");

        // Simulate some work (reduced from 1 second to 0.1 second for faster testing)
        usleep(100000); // 0.1 second

        Log::info("RabbitMQ Job completed successfully!");
    }
}

