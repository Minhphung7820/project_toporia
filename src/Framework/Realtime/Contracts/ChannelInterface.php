<?php

declare(strict_types=1);

namespace Toporia\Framework\Realtime\Contracts;

/**
 * Channel Interface
 *
 * Represents a realtime communication channel.
 * Channels organize connections and messages by topic/room.
 *
 * Channel Types:
 * - Public: Anyone can subscribe (e.g., "news", "announcements")
 * - Private: Requires authentication (e.g., "user.123", "chat.room.456")
 * - Presence: Tracks who's online (e.g., "presence.room.1")
 *
 * Channel Naming Convention:
 * - Public: "public.{name}" or just "{name}"
 * - Private: "private.{name}" or "user.{id}"
 * - Presence: "presence.{name}"
 *
 * Performance:
 * - O(1) subscription/unsubscription
 * - O(N) broadcast where N = subscribers
 * - Memory: ~100 bytes per subscriber
 *
 * @package Toporia\Framework\Realtime\Contracts
 */
interface ChannelInterface
{
    /**
     * Get channel name.
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Check if channel is public.
     *
     * @return bool
     */
    public function isPublic(): bool;

    /**
     * Check if channel is private.
     *
     * @return bool
     */
    public function isPrivate(): bool;

    /**
     * Check if channel is presence-enabled.
     *
     * @return bool
     */
    public function isPresence(): bool;

    /**
     * Get subscriber count.
     *
     * @return int
     */
    public function getSubscriberCount(): int;

    /**
     * Get all subscribers.
     *
     * @return array<ConnectionInterface>
     */
    public function getSubscribers(): array;

    /**
     * Add subscriber to channel.
     *
     * @param ConnectionInterface $connection
     * @return void
     */
    public function subscribe(ConnectionInterface $connection): void;

    /**
     * Remove subscriber from channel.
     *
     * @param ConnectionInterface $connection
     * @return void
     */
    public function unsubscribe(ConnectionInterface $connection): void;

    /**
     * Check if connection is subscribed.
     *
     * @param ConnectionInterface $connection
     * @return bool
     */
    public function hasSubscriber(ConnectionInterface $connection): bool;

    /**
     * Broadcast message to all subscribers.
     *
     * @param MessageInterface $message
     * @param ConnectionInterface|null $except Exclude this connection
     * @return void
     */
    public function broadcast(MessageInterface $message, ?ConnectionInterface $except = null): void;

    /**
     * Authorize connection to join channel.
     *
     * For private/presence channels, validates user permissions.
     *
     * @param ConnectionInterface $connection
     * @return bool
     */
    public function authorize(ConnectionInterface $connection): bool;
}
