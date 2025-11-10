<?php

declare(strict_types=1);

namespace Toporia\Framework\Notification\Contracts;

/**
 * Notification Manager Interface
 *
 * Multi-channel notification dispatcher with driver management.
 * Coordinates sending notifications across multiple channels.
 *
 * @package Toporia\Framework\Notification\Contracts
 */
interface NotificationManagerInterface
{
    /**
     * Send notification to a notifiable entity.
     *
     * Determines channels via $notification->via($notifiable)
     * and dispatches to each channel.
     *
     * Checks if notification should be queued via shouldQueue().
     *
     * Performance: O(C) where C = number of channels
     *
     * @param NotifiableInterface $notifiable Entity to notify
     * @param NotificationInterface $notification Notification to send
     * @return void
     */
    public function send(NotifiableInterface $notifiable, NotificationInterface $notification): void;

    /**
     * Send notification immediately (synchronous), bypassing queue check.
     *
     * Use this when you want to force immediate delivery regardless of
     * shouldQueue() setting. Primarily used by queue workers.
     *
     * Performance: O(C) where C = number of channels
     *
     * @param NotifiableInterface $notifiable Entity to notify
     * @param NotificationInterface $notification Notification to send
     * @return void
     */
    public function sendNow(NotifiableInterface $notifiable, NotificationInterface $notification): void;

    /**
     * Send notification to multiple notifiables.
     *
     * Optimized bulk sending with batching support.
     *
     * Performance: O(N * C) where N = notifiables, C = channels per notifiable
     * Can be optimized to O(N + C) with channel batching
     *
     * @param iterable<NotifiableInterface> $notifiables Entities to notify
     * @param NotificationInterface $notification Notification to send
     * @return void
     */
    public function sendToMany(iterable $notifiables, NotificationInterface $notification): void;

    /**
     * Get a specific notification channel.
     *
     * Lazy loads channel on first access.
     *
     * @param string $name Channel name (mail, database, sms, slack)
     * @return ChannelInterface
     * @throws \InvalidArgumentException If channel not found
     */
    public function channel(string $name): ChannelInterface;

    /**
     * Get default notification channel name.
     *
     * @return string
     */
    public function getDefaultChannel(): string;
}
