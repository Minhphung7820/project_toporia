<?php

declare(strict_types=1);

namespace Toporia\Framework\Support\Accessors;

use Toporia\Framework\Foundation\ServiceAccessor;
use Toporia\Framework\Notification\NotificationManager;
use Toporia\Framework\Notification\Contracts\{NotifiableInterface, NotificationInterface};

/**
 * Notification Service Accessor
 *
 * Provides static-like access to the notification manager.
 *
 * @method static void send(NotifiableInterface $notifiable, NotificationInterface $notification) Send notification
 * @method static void sendToMany(iterable $notifiables, NotificationInterface $notification) Send to multiple
 * @method static \Toporia\Framework\Notification\Contracts\ChannelInterface channel(string $name) Get channel
 *
 * @see NotificationManager
 *
 * @example
 * // Send notification
 * Notification::send($user, new WelcomeNotification());
 *
 * // Send to multiple users
 * Notification::sendToMany($users, new AnnouncementNotification());
 *
 * // Access specific channel
 * $mailChannel = Notification::channel('mail');
 *
 * @package Toporia\Framework\Support\Accessors
 */
final class Notification extends ServiceAccessor
{
    /**
     * {@inheritdoc}
     */
    protected static function getServiceName(): string
    {
        return 'notification';
    }
}
