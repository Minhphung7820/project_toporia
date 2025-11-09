<?php

declare(strict_types=1);

namespace App\Jobs;

use Toporia\Framework\Queue\Job;
use Toporia\Framework\Mail\MailerInterface;
use Toporia\Framework\Mail\Message;

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
final class SendEmailJob extends Job
{
    public function __construct(
        private readonly string $to,
        private readonly string $subject,
        private readonly string $message,
        private readonly ?string $from = null,
        private readonly ?string $fromName = null
    ) {
        parent::__construct();
    }

    /**
     * Execute the job.
     *
     * Dependencies are auto-injected by the Worker via container.
     *
     * @param MailerInterface $mailer Injected mailer service
     * @return void
     * @throws \RuntimeException If sending fails
     */
    public function handle(MailerInterface $mailer): void
    {
        // Build message using fluent builder
        $msg = (new Message())
            ->from(
                $this->from ?? env('MAIL_FROM_ADDRESS', 'noreply@example.com'),
                $this->fromName ?? env('MAIL_FROM_NAME', 'Application')
            )
            ->to($this->to)
            ->subject($this->subject)
            ->html($this->message);

        // Send via injected mailer (SmtpMailer with PHPMailer)
        $mailer->send($msg);

        error_log("Email sent successfully to {$this->to}: {$this->subject}");
    }

    /**
     * Handle job failure.
     *
     * @param \Throwable $exception
     * @return void
     */
    public function failed(\Throwable $exception): void
    {
        error_log("Failed to send email to {$this->to}: " . $exception->getMessage());
        parent::failed($exception);
    }
}
