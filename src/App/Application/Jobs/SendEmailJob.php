<?php

declare(strict_types=1);

namespace App\Application\Jobs;

use Toporia\Framework\Queue\Job;
use Toporia\Framework\Mail\MailerInterface;
use Toporia\Framework\Mail\Message;

/**
 * Send Email Job
 *
 * Example job that sends an email asynchronously using the Mail system.
 */
final class SendEmailJob extends Job
{
    /**
     * @param string $to Recipient email
     * @param string $subject Email subject
     * @param string $body Email body (HTML)
     * @param int $tries Maximum retry attempts (default: 3)
     */
    public function __construct(
        private string $to,
        private string $subject,
        private string $body,
        int $tries = 3
    ) {
        parent::__construct();
        $this->tries($tries);
        $this->onQueue('emails');
    }

    /**
     * Execute the job.
     *
     * @param MailerInterface $mailer Mailer instance (auto-injected from container).
     * @return void
     */
    public function handle(MailerInterface $mailer): void
    {
        // Build message using Mail system
        $message = (new Message())
            ->from(config('mail.from.address'), config('mail.from.name'))
            ->to($this->to)
            ->subject($this->subject)
            ->html($this->body);

        // Send email
        $success = $mailer->send($message);

        if (!$success) {
            throw new \RuntimeException("Failed to send email to {$this->to}");
        }

        // Log success
        error_log("Email sent successfully to {$this->to}");
    }

    /**
     * Handle job failure.
     *
     * @param \Throwable $exception
     * @return void
     */
    public function failed(\Throwable $exception): void
    {
        // Log failure
        error_log("Failed to send email to {$this->to}: " . $exception->getMessage());

        // You could send notification to admin here
        // or store failed job info in database
    }
}
