<?php

declare(strict_types=1);

namespace Toporia\Framework\Realtime\Transports;

use Toporia\Framework\Realtime\Contracts\{ConnectionInterface, MessageInterface, TransportInterface};
use Toporia\Framework\Realtime\RealtimeManager;

/**
 * Memory Transport
 *
 * In-memory transport for testing and single-server deployments.
 * Does NOT support actual client connections - used for programmatic testing.
 *
 * Use Cases:
 * - Unit testing
 * - Integration testing
 * - Single-server apps without real WebSocket
 * - Background job → Realtime event broadcasting
 *
 * Performance:
 * - O(1) send
 * - O(N) broadcast where N = connections
 * - No network overhead
 * - Memory only: ~1KB per connection
 *
 * Limitations:
 * - No actual client connections (HTTP/WebSocket)
 * - Single PHP process only
 * - Data lost on restart
 *
 * @package Toporia\Framework\Realtime\Transports
 */
final class MemoryTransport implements TransportInterface
{
    /**
     * @var array<callable> Message handlers for testing
     */
    private array $handlers = [];

    public function __construct(
        private readonly RealtimeManager $manager
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function send(ConnectionInterface $connection, MessageInterface $message): void
    {
        // Trigger handlers (for testing)
        foreach ($this->handlers as $handler) {
            $handler($connection, $message);
        }

        // Update connection activity
        $connection->updateLastActivity();
    }

    /**
     * {@inheritdoc}
     */
    public function broadcast(MessageInterface $message): void
    {
        foreach ($this->manager->getConnections() as $connection) {
            $this->send($connection, $message);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function broadcastToChannel(string $channel, MessageInterface $message): void
    {
        $channelInstance = $this->manager->channel($channel);
        $channelInstance->broadcast($message);
    }

    /**
     * {@inheritdoc}
     */
    public function getConnectionCount(): int
    {
        return $this->manager->getConnectionCount();
    }

    /**
     * {@inheritdoc}
     */
    public function hasConnection(string $connectionId): bool
    {
        return !empty($this->manager->getUserConnections($connectionId));
    }

    /**
     * {@inheritdoc}
     */
    public function close(ConnectionInterface $connection, int $code = 1000, string $reason = ''): void
    {
        // Memory transport doesn't have actual connections to close
        // Just trigger handlers
        foreach ($this->handlers as $handler) {
            $handler($connection, null); // null = close
        }
    }

    /**
     * {@inheritdoc}
     */
    public function start(string $host, int $port): void
    {
        // Memory transport doesn't start a server
        // This is a no-op
    }

    /**
     * {@inheritdoc}
     */
    public function stop(): void
    {
        // No-op
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'memory';
    }

    /**
     * Add message handler (for testing).
     *
     * Handler signature: function(ConnectionInterface $connection, ?MessageInterface $message)
     * - $message = null means connection closed
     *
     * @param callable $handler
     * @return void
     */
    public function onMessage(callable $handler): void
    {
        $this->handlers[] = $handler;
    }
}
