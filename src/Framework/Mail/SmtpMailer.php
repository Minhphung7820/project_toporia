<?php

declare(strict_types=1);

namespace Toporia\Framework\Mail;

use Toporia\Framework\Queue\QueueInterface;

/**
 * SMTP Mailer
 *
 * Sends emails via SMTP protocol.
 * Supports queue integration for async sending.
 */
final class SmtpMailer implements MailerInterface
{
    /**
     * @param array $config SMTP configuration.
     * @param QueueInterface|null $queue Queue for async sending.
     */
    public function __construct(
        private array $config,
        private ?QueueInterface $queue = null
    ) {}

    /**
     * {@inheritdoc}
     */
    public function send(MessageInterface $message): bool
    {
        try {
            // Build email headers
            $headers = $this->buildHeaders($message);

            // Send via PHP mail() with SMTP headers
            // In production, use PHPMailer or Symfony Mailer
            $success = mail(
                implode(', ', $message->getTo()),
                $message->getSubject(),
                $message->getBody(),
                $headers
            );

            return $success;
        } catch (\Throwable $e) {
            error_log("Mail send failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function sendMailable(Mailable $mailable): bool
    {
        $message = $mailable->build();
        return $this->send($message);
    }

    /**
     * {@inheritdoc}
     */
    public function queue(MessageInterface $message, int $delay = 0): bool
    {
        if (!$this->queue) {
            throw new \RuntimeException('Queue not configured for mailer');
        }

        $job = new \App\Application\Jobs\SendMailJob($message);

        if ($delay > 0) {
            $this->queue->later($job, $delay);
        } else {
            $this->queue->push($job, 'emails');
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function queueMailable(Mailable $mailable, int $delay = 0): bool
    {
        $message = $mailable->build();
        return $this->queue($message, $delay);
    }

    /**
     * Build email headers.
     *
     * @param MessageInterface $message
     * @return string
     */
    private function buildHeaders(MessageInterface $message): string
    {
        $headers = [];

        // From header
        if ($message->getFromName()) {
            $headers[] = 'From: ' . $message->getFromName() . ' <' . $message->getFrom() . '>';
        } else {
            $headers[] = 'From: ' . $message->getFrom();
        }

        // Reply-To
        if ($message->getReplyTo()) {
            $headers[] = 'Reply-To: ' . $message->getReplyTo();
        }

        // CC
        if (!empty($message->getCc())) {
            $headers[] = 'Cc: ' . implode(', ', $message->getCc());
        }

        // BCC
        if (!empty($message->getBcc())) {
            $headers[] = 'Bcc: ' . implode(', ', $message->getBcc());
        }

        // Content type
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: text/html; charset=UTF-8';

        // Custom headers
        foreach ($message->getHeaders() as $name => $value) {
            $headers[] = "$name: $value";
        }

        return implode("\r\n", $headers);
    }
}
