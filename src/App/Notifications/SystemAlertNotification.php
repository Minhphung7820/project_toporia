<?php

declare(strict_types=1);

namespace App\Notifications;

use Toporia\Framework\Notification\Notification;
use Toporia\Framework\Notification\Contracts\NotifiableInterface;
use Toporia\Framework\Notification\Messages\SlackMessage;

/**
 * System Alert Notification
 *
 * Sent to admins for critical system events.
 * Channel: Slack only (for real-time alerts)
 *
 * Usage:
 * ```php
 * // Send to admin
 * $admin->notify(new SystemAlertNotification('High CPU usage detected', [
 *     'CPU' => '95%',
 *     'Memory' => '80%',
 *     'Server' => 'web-01'
 * ]));
 * ```
 *
 * @package App\Notifications
 */
final class SystemAlertNotification extends Notification
{
    public function __construct(
        private readonly string $message,
        private readonly array $details = [],
        private readonly string $severity = 'warning' // info, warning, danger
    ) {
        parent::__construct();
    }

    /**
     * Get notification channels.
     *
     * @param NotifiableInterface $notifiable
     * @return array
     */
    public function via(NotifiableInterface $notifiable): array
    {
        return ['slack'];
    }

    /**
     * Build Slack notification.
     *
     * @param NotifiableInterface $notifiable
     * @return SlackMessage
     */
    public function toSlack(NotifiableInterface $notifiable): SlackMessage
    {
        $color = match($this->severity) {
            'info' => 'good',
            'warning' => 'warning',
            'danger' => 'danger',
            default => 'warning'
        };

        $icon = match($this->severity) {
            'info' => ':information_source:',
            'warning' => ':warning:',
            'danger' => ':rotating_light:',
            default => ':bell:'
        };

        $message = (new SlackMessage)
            ->content("System Alert: {$this->message}")
            ->icon($icon)
            ->from('System Monitor')
            ->channel('#alerts');

        if (!empty($this->details)) {
            $message->attachment(function ($attachment) use ($color) {
                $attachment
                    ->title('Alert Details')
                    ->fields($this->details)
                    ->color($color);
            });
        }

        return $message;
    }
}
