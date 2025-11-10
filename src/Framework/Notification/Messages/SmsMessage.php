<?php

declare(strict_types=1);

namespace Toporia\Framework\Notification\Messages;

/**
 * SMS Message
 *
 * Simple SMS message builder with character count tracking.
 *
 * Usage:
 * ```php
 * return (new SmsMessage)
 *     ->content('Your verification code is: 123456')
 *     ->from('YourApp');
 * ```
 *
 * @package Toporia\Framework\Notification\Messages
 */
final class SmsMessage
{
    public string $content = '';
    public ?string $from = null;

    /**
     * Set message content.
     *
     * @param string $content
     * @return $this
     */
    public function content(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    /**
     * Set sender ID/name.
     *
     * @param string $from
     * @return $this
     */
    public function from(string $from): self
    {
        $this->from = $from;
        return $this;
    }

    /**
     * Get message character count.
     *
     * @return int
     */
    public function getLength(): int
    {
        return mb_strlen($this->content);
    }

    /**
     * Check if message exceeds SMS limit (160 characters).
     *
     * @return bool
     */
    public function exceedsLimit(): bool
    {
        return $this->getLength() > 160;
    }
}
