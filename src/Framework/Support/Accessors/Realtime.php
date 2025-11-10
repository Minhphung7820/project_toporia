<?php

declare(strict_types=1);

namespace Toporia\Framework\Support\Accessors;

use Toporia\Framework\Foundation\ServiceAccessor;
use Toporia\Framework\Realtime\RealtimeManager;
use Toporia\Framework\Realtime\Contracts\{ChannelInterface, TransportInterface, BrokerInterface};

/**
 * Realtime Service Accessor
 *
 * Provides static-like access to the realtime manager.
 *
 * @method static void broadcast(string $channel, string $event, mixed $data) Broadcast to channel
 * @method static void send(string $connectionId, string $event, mixed $data) Send to connection
 * @method static void sendToUser(string|int $userId, string $event, mixed $data) Send to user
 * @method static ChannelInterface channel(string $name) Get channel
 * @method static TransportInterface transport(?string $name = null) Get transport
 * @method static BrokerInterface|null broker(?string $name = null) Get broker
 * @method static int getConnectionCount() Get active connections count
 * @method static void disconnect(string $connectionId) Disconnect connection
 *
 * @see RealtimeManager
 *
 * @example
 * // Broadcast to channel
 * Realtime::broadcast('chat.room.1', 'message.sent', [
 *     'user' => 'John',
 *     'text' => 'Hello!'
 * ]);
 *
 * // Send to specific user
 * Realtime::sendToUser($userId, 'notification.new', [
 *     'title' => 'New Message',
 *     'body' => 'You have a new message'
 * ]);
 *
 * // Get channel
 * $channel = Realtime::channel('chat.room.1');
 * $subscribers = $channel->getSubscriberCount();
 *
 * @package Toporia\Framework\Support\Accessors
 */
final class Realtime extends ServiceAccessor
{
    /**
     * {@inheritdoc}
     */
    protected static function getServiceName(): string
    {
        return 'realtime';
    }
}
