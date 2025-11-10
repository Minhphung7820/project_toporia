<?php

declare(strict_types=1);

namespace Toporia\Framework\Notification\Contracts;

/**
 * Notifiable Interface
 *
 * Marks entities that can receive notifications (Users, Admins, Teams, etc.)
 * Provides routing information for each notification channel.
 *
 * Usage:
 * ```php
 * class User implements NotifiableInterface
 * {
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
 * ```
 *
 * Performance:
 * - O(1) routing resolution via match expression
 * - Lazy loading of routing data
 * - No database queries during routing
 *
 * @package Toporia\Framework\Notification\Contracts
 */
interface NotifiableInterface
{
    /**
     * Get routing information for a notification channel.
     *
     * Returns channel-specific delivery address:
     * - 'mail': Email address (string)
     * - 'sms': Phone number (string)
     * - 'slack': Webhook URL (string)
     * - 'database': User ID or identifier (string|int)
     * - Custom channels: Any routing data
     *
     * Performance: O(1) - Direct property access
     *
     * @param string $channel Channel name
     * @return mixed Channel-specific routing data (null if channel not supported)
     */
    public function routeNotificationFor(string $channel): mixed;
}
