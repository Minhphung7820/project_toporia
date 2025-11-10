<?php

declare(strict_types=1);

namespace Toporia\Framework\Notification\Channels;

use Toporia\Framework\Mail\MailManagerInterface;
use Toporia\Framework\Notification\Contracts\{ChannelInterface, NotifiableInterface, NotificationInterface};
use Toporia\Framework\Notification\Messages\MailMessage;

/**
 * Mail Notification Channel
 *
 * Sends notifications via email using MailManager.
 * Supports rich HTML emails with action buttons.
 *
 * Performance:
 * - O(1) per notification
 * - Async delivery via mail queue
 * - SMTP connection pooling
 *
 * @package Toporia\Framework\Notification\Channels
 */
final class MailChannel implements ChannelInterface
{
    public function __construct(
        private readonly MailManagerInterface $mailer
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function send(NotifiableInterface $notifiable, NotificationInterface $notification): void
    {
        // Get recipient email address
        $to = $notifiable->routeNotificationFor('mail');

        if (!$to) {
            return; // No email address configured
        }

        // Build mail message
        $message = $notification->toChannel($notifiable, 'mail');

        if (!$message instanceof MailMessage) {
            throw new \InvalidArgumentException(
                'Mail notification must return MailMessage instance from toMail() method'
            );
        }

        // Send email
        $this->mailer->to($to)
            ->subject($message->subject)
            ->html($message->render())
            ->send();
    }
}
