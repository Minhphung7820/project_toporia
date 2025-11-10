<?php

declare(strict_types=1);

namespace Toporia\Framework\Notification\Contracts;

/**
 * Notification Interface
 *
 * Defines contract for notification messages.
 * Notifications are sent through multiple channels (mail, database, SMS, Slack, etc.)
 *
 * Performance:
 * - Lazy channel resolution (only loads channels that are used)
 * - Supports queueing for async delivery
 * - Minimal object creation overhead
 *
 * Clean Architecture:
 * - Interface Segregation: Focused on notification behavior
 * - Dependency Inversion: Channels depend on abstractions
 * - Open/Closed: Extensible via custom channels
 *
 * @package Toporia\Framework\Notification\Contracts
 */
interface NotificationInterface
{
    /**
     * Get notification channels for a notifiable entity.
     *
     * Returns array of channel names: ['mail', 'database', 'sms', 'slack']
     * Channels are resolved and executed in order.
     *
     * Performance: O(1) - Simple array return
     *
     * @param NotifiableInterface $notifiable The entity receiving notification
     * @return array<string> Channel names
     */
    public function via(NotifiableInterface $notifiable): array;

    /**
     * Build notification data for a specific channel.
     *
     * Each channel calls this method to get channel-specific data.
     * Return type varies by channel:
     * - MailChannel: MailMessage object
     * - DatabaseChannel: array of data
     * - SmsChannel: SmsMessage object
     * - SlackChannel: SlackMessage object
     *
     * Performance: O(1) - Data construction only when needed
     *
     * @param NotifiableInterface $notifiable The entity receiving notification
     * @param string $channel Channel name (mail, database, sms, slack)
     * @return mixed Channel-specific message object or data
     */
    public function toChannel(NotifiableInterface $notifiable, string $channel): mixed;

    /**
     * Get unique notification identifier.
     *
     * Used for tracking, deduplication, and database storage.
     *
     * @return string Unique ID
     */
    public function getId(): string;

    /**
     * Determine if notification should be queued.
     *
     * Default: false (send immediately)
     * Override to return true for async delivery.
     *
     * @return bool
     */
    public function shouldQueue(): bool;

    /**
     * Get queue name for async delivery.
     *
     * Only used if shouldQueue() returns true.
     *
     * @return string Queue name (e.g., 'notifications', 'emails')
     */
    public function getQueueName(): string;

    /**
     * Get notification delay in seconds.
     *
     * Allows scheduled delivery (e.g., send in 5 minutes).
     *
     * @return int Delay in seconds (0 = immediate)
     */
    public function getDelay(): int;
}
