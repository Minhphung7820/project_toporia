<?php

declare(strict_types=1);

namespace Toporia\Framework\Notification;

use Toporia\Framework\Notification\Contracts\NotificationInterface;

/**
 * Notifiable Trait
 *
 * Adds notification capabilities to any model (User, Admin, Team, etc.)
 * Provides convenient notify() method for sending notifications.
 *
 * Usage:
 * ```php
 * class User implements NotifiableInterface
 * {
 *     use Notifiable;
 *
 *     public function routeNotificationFor(string $channel): mixed
 *     {
 *         return match($channel) {
 *             'mail' => $this->email,
 *             'sms' => $this->phone,
 *             'slack' => $this->slackWebhookUrl,
 *             'database' => $this->id,
 *             default => null
 *         };
 *     }
 * }
 *
 * // Send notification
 * $user->notify(new WelcomeNotification());
 * ```
 *
 * Performance:
 * - O(1) method call overhead
 * - Lazy service resolution
 * - No memory overhead when not used
 *
 * @package Toporia\Framework\Notification
 */
trait Notifiable
{
    /**
     * Send a notification to this entity.
     *
     * Convenience method that delegates to NotificationManager.
     *
     * @param NotificationInterface $notification
     * @return void
     */
    public function notify(NotificationInterface $notification): void
    {
        app('notification')->send($this, $notification);
    }

    /**
     * Send a notification asynchronously via queue.
     *
     * @param NotificationInterface $notification
     * @param string $queueName
     * @return void
     */
    public function notifyLater(NotificationInterface $notification, string $queueName = 'notifications'): void
    {
        $notification->onQueue($queueName);
        $this->notify($notification);
    }
}
