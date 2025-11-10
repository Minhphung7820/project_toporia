<?php

declare(strict_types=1);

namespace Toporia\Framework\Notification\Channels;

use Toporia\Framework\Mail\{MailManagerInterface, Message};
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
 * Clean Architecture:
 * - Depends on MailManagerInterface abstraction (DIP)
 * - Config injected via constructor (Testable)
 * - No global state dependencies
 *
 * @package Toporia\Framework\Notification\Channels
 */
final class MailChannel implements ChannelInterface
{
    public function __construct(
        private readonly MailManagerInterface $mailer,
        private readonly array $config = []
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function send(NotifiableInterface $notifiable, NotificationInterface $notification): void
    {
        // Get recipient email address
        // Check if notification has custom routing first (for override scenarios)
        $to = method_exists($notification, 'routeNotificationFor')
            ? $notification->routeNotificationFor($notifiable, 'mail')
            : $notifiable->routeNotificationFor('mail');

        if (!$to) {
            return; // No email address configured
        }

        // Build notification message (MailMessage from notification)
        $notificationMessage = $notification->toChannel($notifiable, 'mail');

        if (!$notificationMessage instanceof MailMessage) {
            throw new \InvalidArgumentException(
                'Mail notification must return MailMessage instance from toMail() method'
            );
        }

        // Get from address from injected config (DIP compliant)
        $from = $this->config['from']['address'] ?? 'noreply@example.com';
        $fromName = $this->config['from']['name'] ?? 'Toporia Framework';

        // Convert to Mail\Message and send
        $mailMessage = (new Message())
            ->from($from, $fromName)
            ->to($to)
            ->subject($notificationMessage->subject)
            ->html($notificationMessage->render());

        // Send email via mailer
        $this->mailer->send($mailMessage);
    }
}
