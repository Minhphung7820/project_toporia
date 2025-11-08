<?php

declare(strict_types=1);

namespace Toporia\Framework\Mail;

/**
 * Message Interface
 *
 * Contract for email message representation.
 * Immutable message object following Value Object pattern.
 */
interface MessageInterface
{
    /**
     * Get sender email address.
     *
     * @return string
     */
    public function getFrom(): string;

    /**
     * Get sender name.
     *
     * @return string|null
     */
    public function getFromName(): ?string;

    /**
     * Get recipient email addresses.
     *
     * @return array<string>
     */
    public function getTo(): array;

    /**
     * Get CC recipients.
     *
     * @return array<string>
     */
    public function getCc(): array;

    /**
     * Get BCC recipients.
     *
     * @return array<string>
     */
    public function getBcc(): array;

    /**
     * Get reply-to address.
     *
     * @return string|null
     */
    public function getReplyTo(): ?string;

    /**
     * Get email subject.
     *
     * @return string
     */
    public function getSubject(): string;

    /**
     * Get email body (HTML).
     *
     * @return string
     */
    public function getBody(): string;

    /**
     * Get plain text body.
     *
     * @return string|null
     */
    public function getTextBody(): ?string;

    /**
     * Get attachments.
     *
     * @return array<array{path: string, name?: string, mime?: string}>
     */
    public function getAttachments(): array;

    /**
     * Get custom headers.
     *
     * @return array<string, string>
     */
    public function getHeaders(): array;
}
