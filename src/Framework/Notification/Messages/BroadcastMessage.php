<?php

declare(strict_types=1);

namespace Toporia\Framework\Notification\Messages;

/**
 * Broadcast Message
 *
 * Fluent builder for realtime broadcast notifications via WebSocket/SSE.
 * Integrates with Toporia's Realtime system for push notifications.
 *
 * Usage:
 * ```php
 * // User-specific notification (most common)
 * return (new BroadcastMessage)
 *     ->event('order.shipped')
 *     ->data([
 *         'title' => 'Order Shipped!',
 *         'message' => 'Your order #123 has been shipped',
 *         'action_url' => url('/orders/123')
 *     ]);
 *
 * // Custom channel (public/presence)
 * return (new BroadcastMessage)
 *     ->channel('announcements')
 *     ->event('announcement.new')
 *     ->data([
 *         'title' => 'System Maintenance',
 *         'message' => 'Scheduled maintenance tonight',
 *         'type' => 'warning'
 *     ]);
 *
 * // Chat room notification
 * return (new BroadcastMessage)
 *     ->channel("presence-chat.{$roomId}")
 *     ->event('message.sent')
 *     ->data([
 *         'user' => $this->user->name,
 *         'text' => $this->message,
 *         'timestamp' => time()
 *     ]);
 * ```
 *
 * Performance:
 * - O(1) for each fluent call
 * - Lazy evaluation (data built only when needed)
 * - Minimal memory footprint (< 1KB)
 *
 * Channel Routing:
 * - No channel specified: Defaults to `user.{id}` (private channel)
 * - `channel('foo')`: Broadcast to specific channel
 * - User-specific is more efficient (sends only to user's connections)
 *
 * @package Toporia\Framework\Notification\Messages
 */
final class BroadcastMessage
{
    private ?string $channel = null;
    private string $event = 'notification';
    private mixed $data = [];
    private bool $userSpecific = true;

    /**
     * Set broadcast channel.
     *
     * Channel types:
     * - User-specific: `user.{id}` (private, one user)
     * - Presence: `presence-room.{id}` (chat, online tracking)
     * - Public: `announcements` (all subscribers)
     *
     * If not set, defaults to user-specific channel.
     *
     * @param string $channel Channel name
     * @return $this
     */
    public function channel(string $channel): self
    {
        $this->channel = $channel;
        $this->userSpecific = false; // Custom channel = not user-specific
        return $this;
    }

    /**
     * Set event name.
     *
     * Event naming conventions:
     * - Use dot notation: `resource.action` (e.g., `order.shipped`)
     * - Be specific: `user.followed` not just `followed`
     * - Past tense for completed actions
     *
     * @param string $event Event name
     * @return $this
     */
    public function event(string $event): self
    {
        $this->event = $event;
        return $this;
    }

    /**
     * Set notification data.
     *
     * Data will be JSON-encoded and sent to client.
     *
     * Recommended structure:
     * ```php
     * [
     *     'title' => 'Short title',
     *     'message' => 'Detailed message',
     *     'action_url' => url('/path'),
     *     'type' => 'success|info|warning|error',
     *     'icon' => '🎉',
     *     'timestamp' => time(),
     *     'resource_id' => 123,
     *     'resource_type' => 'order'
     * ]
     * ```
     *
     * @param mixed $data Notification data (will be JSON-encoded)
     * @return $this
     */
    public function data(mixed $data): self
    {
        $this->data = $data;
        return $this;
    }

    /**
     * Set as user-specific notification.
     *
     * This optimizes delivery by sending directly to user's connections
     * instead of broadcasting to a channel.
     *
     * @param bool $userSpecific
     * @return $this
     */
    public function toUser(bool $userSpecific = true): self
    {
        $this->userSpecific = $userSpecific;
        return $this;
    }

    /**
     * Get channel name.
     *
     * @return string|null
     */
    public function getChannel(): ?string
    {
        return $this->channel;
    }

    /**
     * Get event name.
     *
     * @return string
     */
    public function getEvent(): string
    {
        return $this->event;
    }

    /**
     * Get notification data.
     *
     * @return mixed
     */
    public function getData(): mixed
    {
        return $this->data;
    }

    /**
     * Check if notification is user-specific.
     *
     * User-specific = send to user's connections only (more efficient)
     * Non-user-specific = broadcast to channel (all subscribers)
     *
     * @return bool
     */
    public function isUserSpecific(): bool
    {
        return $this->userSpecific && $this->channel === null;
    }

    /**
     * Convert to array format.
     *
     * Used for serialization and debugging.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'channel' => $this->channel,
            'event' => $this->event,
            'data' => $this->data,
            'user_specific' => $this->userSpecific
        ];
    }
}
