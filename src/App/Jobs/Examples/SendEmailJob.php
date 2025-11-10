<?php

declare(strict_types=1);

namespace App\Jobs\Examples;

use Toporia\Framework\Queue\Job;
use Toporia\Framework\Queue\Middleware\RateLimited;

/**
 * Example Job: Send Email
 *
 * Simple job with constant retry delay.
 * Demonstrates property-based configuration (Laravel-style).
 *
 * @package App\Jobs\Examples
 */
final class SendEmailJob extends Job
{
    /**
     * Number of times to retry on failure.
     */
    protected int $maxAttempts = 3;

    /**
     * Number of seconds to wait before retrying.
     * Simple constant delay (30 seconds between retries).
     */
    protected int $retryAfter = 30;

    /**
     * @param string $to Recipient email
     * @param string $subject Email subject
     * @param string $body Email body
     */
    public function __construct(
        private string $to,
        private string $subject,
        private string $body
    ) {
        parent::__construct();
    }

    /**
     * Rate limit email sending: max 100 emails per minute.
     *
     * @return array
     */
    public function middleware(): array
    {
        return [
            new RateLimited(
                limiter: app('limiter'),
                maxAttempts: 100,
                decayMinutes: 1
            ),
        ];
    }

    /**
     * Send the email.
     *
     * @return void
     */
    public function handle(): void
    {
        // Use injected mailer (dependency injection)
        // $mailer = app('mailer');
        // $mailer->send($this->to, $this->subject, $this->body);

        // Simulate email sending
        if (rand(1, 10) === 1) {
            throw new \RuntimeException('SMTP connection failed');
        }

        echo "Email sent to {$this->to}\n";
    }

    /**
     * Log email failure.
     *
     * @param \Throwable $exception
     * @return void
     */
    public function failed(\Throwable $exception): void
    {
        error_log("Failed to send email to {$this->to}: {$exception->getMessage()}");
    }
}
