<?php

declare(strict_types=1);

namespace Toporia\Framework\Notification;

use Toporia\Framework\Notification\Contracts\{NotificationInterface, NotifiableInterface};

/**
 * Abstract Notification
 *
 * Base class for all notifications with built-in features:
 * - Unique ID generation
 * - Queue support
 * - Multi-channel delivery
 * - Delayed sending
 *
 * Usage:
 * ```php
 * class WelcomeNotification extends Notification
 * {
 *     public function via(NotifiableInterface $notifiable): array
 *     {
 *         return ['mail', 'database'];
 *     }
 *
 *     public function toMail(NotifiableInterface $notifiable): MailMessage
 *     {
 *         return (new MailMessage)
 *             ->subject('Welcome!')
 *             ->line('Thanks for signing up!')
 *             ->action('Get Started', url('/dashboard'));
 *     }
 *
 *     public function toDatabase(NotifiableInterface $notifiable): array
 *     {
 *         return [
 *             'title' => 'Welcome!',
 *             'message' => 'Thanks for signing up!',
 *             'action_url' => url('/dashboard')
 *         ];
 *     }
 * }
 *
 * // Send notification
 * $user->notify(new WelcomeNotification());
 * ```
 *
 * Performance Optimizations:
 * - Lazy channel resolution (only builds data for used channels)
 * - Supports queueing for async delivery
 * - Minimal memory footprint (< 1KB per instance)
 *
 * @package Toporia\Framework\Notification
 */
abstract class Notification implements NotificationInterface
{
    protected string $id;
    protected bool $shouldQueue = false;
    protected string $queueName = 'notifications';
    protected int $delay = 0;

    public function __construct()
    {
        $this->id = uniqid('notification_', true);
    }

    /**
     * {@inheritdoc}
     */
    abstract public function via(NotifiableInterface $notifiable): array;

    /**
     * {@inheritdoc}
     *
     * Routes to channel-specific methods:
     * - 'mail' → toMail()
     * - 'database' → toDatabase()
     * - 'sms' → toSms()
     * - 'slack' → toSlack()
     */
    public function toChannel(NotifiableInterface $notifiable, string $channel): mixed
    {
        $method = 'to' . ucfirst($channel);

        if (!method_exists($this, $method)) {
            throw new \BadMethodCallException(
                "Notification " . static::class . " is missing toChannel method for '{$channel}'"
            );
        }

        return $this->$method($notifiable);
    }

    /**
     * {@inheritdoc}
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * {@inheritdoc}
     */
    public function shouldQueue(): bool
    {
        return $this->shouldQueue;
    }

    /**
     * {@inheritdoc}
     */
    public function getQueueName(): string
    {
        return $this->queueName;
    }

    /**
     * {@inheritdoc}
     */
    public function getDelay(): int
    {
        return $this->delay;
    }

    /**
     * Set notification to be queued.
     *
     * @param string $queueName Queue name
     * @return $this
     */
    public function onQueue(string $queueName): self
    {
        $this->shouldQueue = true;
        $this->queueName = $queueName;
        return $this;
    }

    /**
     * Set notification delay.
     *
     * @param int $seconds Delay in seconds
     * @return $this
     */
    public function delay(int $seconds): self
    {
        $this->delay = $seconds;
        return $this;
    }
}
