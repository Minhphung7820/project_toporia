<?php

declare(strict_types=1);

namespace Toporia\Framework\Notification;

use Toporia\Framework\Notification\Contracts\{NotifiableInterface, NotificationInterface};

/**
 * Anonymous Notifiable
 *
 * Allows sending notifications to arbitrary channels without a model.
 * Useful for sending notifications to specific emails, phone numbers, etc.
 *
 * Usage:
 * ```php
 * // Send to specific email
 * Notification::route('mail', 'admin@example.com')
 *     ->notify(new OrderPlaced($order));
 *
 * // Multiple channels
 * Notification::route('mail', 'admin@example.com')
 *     ->route('sms', '+1234567890')
 *     ->notify(new Alert());
 * ```
 *
 * @package Toporia\Framework\Notification
 */
final class AnonymousNotifiable implements NotifiableInterface
{
    /**
     * @var array<string, mixed> Channel routes
     */
    private array $routes = [];

    /**
     * Set routing for a notification channel.
     *
     * @param string $channel Channel name
     * @param mixed $route Route value (email, phone, etc.)
     * @return $this
     */
    public function route(string $channel, mixed $route): self
    {
        $this->routes[$channel] = $route;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function routeNotificationFor(string $channel): mixed
    {
        return $this->routes[$channel] ?? null;
    }

    /**
     * Send a notification.
     *
     * @param NotificationInterface $notification
     * @return void
     */
    public function notify(NotificationInterface $notification): void
    {
        app('notification')->send($this, $notification);
    }
}
