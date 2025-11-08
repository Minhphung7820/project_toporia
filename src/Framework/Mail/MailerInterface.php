<?php

declare(strict_types=1);

namespace Toporia\Framework\Mail;

/**
 * Mailer Interface
 *
 * Contract for email sending implementations.
 * Follows Interface Segregation Principle - focused interface for sending emails.
 */
interface MailerInterface
{
    /**
     * Send an email message.
     *
     * @param MessageInterface $message The email message to send.
     * @return bool True if sent successfully, false otherwise.
     */
    public function send(MessageInterface $message): bool;

    /**
     * Send a Mailable instance.
     *
     * @param Mailable $mailable The mailable instance.
     * @return bool True if sent successfully, false otherwise.
     */
    public function sendMailable(Mailable $mailable): bool;

    /**
     * Queue an email for later sending.
     *
     * @param MessageInterface $message The email message to queue.
     * @param int $delay Delay in seconds (default: 0).
     * @return bool True if queued successfully.
     */
    public function queue(MessageInterface $message, int $delay = 0): bool;

    /**
     * Queue a Mailable for later sending.
     *
     * @param Mailable $mailable The mailable instance.
     * @param int $delay Delay in seconds (default: 0).
     * @return bool True if queued successfully.
     */
    public function queueMailable(Mailable $mailable, int $delay = 0): bool;
}
