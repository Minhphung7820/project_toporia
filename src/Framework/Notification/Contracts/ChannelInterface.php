<?php

declare(strict_types=1);

namespace Toporia\Framework\Notification\Contracts;

/**
 * Notification Channel Interface
 *
 * Defines contract for notification delivery channels.
 * Each channel handles delivery to a specific medium (email, SMS, database, etc.)
 *
 * Built-in Channels:
 * - MailChannel: Email delivery via MailManager
 * - DatabaseChannel: Store in database for in-app notifications
 * - SmsChannel: SMS delivery via third-party APIs
 * - SlackChannel: Slack messages via webhooks
 *
 * Performance:
 * - Async delivery via queue support
 * - Bulk sending optimization
 * - Connection pooling for external APIs
 * - Minimal memory footprint
 *
 * SOLID Principles:
 * - Single Responsibility: Each channel handles one delivery method
 * - Open/Closed: Extensible via custom channels
 * - Liskov Substitution: All channels are interchangeable
 * - Interface Segregation: Minimal interface
 * - Dependency Inversion: Depends on abstractions
 *
 * @package Toporia\Framework\Notification\Contracts
 */
interface ChannelInterface
{
    /**
     * Send notification via this channel.
     *
     * Channel receives notification and notifiable, extracts routing info,
     * builds message, and delivers it.
     *
     * Flow:
     * 1. Get routing info: $notifiable->routeNotificationFor($channelName)
     * 2. Build message: $notification->toChannel($notifiable, $channelName)
     * 3. Deliver message via channel-specific mechanism
     *
     * Performance:
     * - O(1) for single notification
     * - O(N) for bulk notifications (with batching optimization)
     * - Async delivery via queue (optional)
     *
     * @param NotifiableInterface $notifiable Entity receiving notification
     * @param NotificationInterface $notification Notification to send
     * @return void
     * @throws \Throwable If delivery fails
     */
    public function send(NotifiableInterface $notifiable, NotificationInterface $notification): void;
}
