<?php

declare(strict_types=1);

namespace App\Jobs;

use Toporia\Framework\Queue\Job;
use Toporia\Framework\Mail\MailerInterface;
use Toporia\Framework\Mail\Message;
use Toporia\Framework\Support\Accessors\Log;
use Toporia\Framework\Support\Accessors\Process;

/**
 * Send Email Job
 *
 * Queued job for sending emails asynchronously.
 *
 * Clean Architecture:
 * - Depends on MailerInterface (Dependency Inversion Principle)
 * - Single Responsibility: Only handles email job execution
 * - Open/Closed: Works with any MailerInterface implementation
 * - High Reusability: Decoupled from specific mailer
 *
 * @package App\Jobs
 */
final class TestProcess extends Job
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the job.
     *
     * Dependencies are auto-injected by the Worker via container.
     *
     * Note: Closures cannot be serialized, so we create them in handle() method
     * after the job is deserialized. This ensures Process::run() works correctly
     * with RabbitMQ, Redis, and Database queues.
     *
     * @return void
     * @throws \RuntimeException If sending fails
     */
    public function handle(): void
    {
        // Create closures here (after deserialization) instead of in constructor
        // This ensures they work correctly with all queue drivers (RabbitMQ, Redis, Database)
        Process::run([
            fn() => $this->logTest(),
            fn() => $this->logTest(),
            fn() => $this->logTest(),
            fn() => $this->logTest(),
        ]);
    }

    private function logTest()
    {
        Log::info("Testlog");
    }
}
