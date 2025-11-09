<?php

declare(strict_types=1);

namespace Toporia\Framework\Mail;

/**
 * Mailer Interface
 *
 * Contract for email sending implementations.
 *
 * Clean Architecture:
 * - Defines contract for mail sending (Dependency Inversion Principle)
 * - Allows multiple implementations (SMTP, Log, Array, etc.)
 * - Framework-agnostic abstraction
 *
 * @package Toporia\Framework\Mail
 */
interface MailerInterface
{
    /**
     * Send an email using Message object.
     *
     * @param MessageInterface $message Email message to send
     * @return bool True on success, false on failure
     * @throws \RuntimeException If sending fails
     */
    public function send(MessageInterface $message): bool;

    /**
     * Send a Mailable.
     *
     * @param Mailable $mailable Mailable instance
     * @return bool True on success
     * @throws \RuntimeException If sending fails
     */
    public function sendMailable(Mailable $mailable): bool;

    /**
     * Queue an email for async sending.
     *
     * @param MessageInterface $message Email message to queue
     * @param int $delay Delay in seconds
     * @return bool True on success
     * @throws \RuntimeException If queue not available
     */
    public function queue(MessageInterface $message, int $delay = 0): bool;

    /**
     * Queue a Mailable for async sending.
     *
     * @param Mailable $mailable Mailable instance
     * @param int $delay Delay in seconds
     * @return bool True on success
     * @throws \RuntimeException If queue not available
     */
    public function queueMailable(Mailable $mailable, int $delay = 0): bool;
}
