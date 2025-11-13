<?php

declare(strict_types=1);

namespace Toporia\Framework\Realtime;

use Toporia\Framework\Container\ContainerInterface;
use Toporia\Framework\Realtime\Contracts\{
    BrokerInterface,
    ChannelInterface,
    ConnectionInterface,
    MessageInterface,
    RealtimeManagerInterface,
    TransportInterface
};

/**
 * Realtime Manager
 *
 * Central coordinator for realtime communication system.
 * Manages transports, brokers, channels, and connections.
 *
 * Performance:
 * - O(1) channel lookup via hash table
 * - O(1) connection lookup via hash table
 * - O(N) broadcast where N = subscribers
 * - Lazy channel creation
 *
 * Architecture:
 * - Factory pattern for transports/brokers
 * - Repository pattern for channels/connections
 * - Singleton pattern for manager instance
 *
 * @package Toporia\Framework\Realtime
 */
final class RealtimeManager implements RealtimeManagerInterface
{
    /**
     * @var array<string, ChannelInterface> Channel instances
     */
    private array $channels = [];

    /**
     * @var array<string, ConnectionInterface> Active connections
     */
    private array $connections = [];

    /**
     * @var array<string, TransportInterface> Transport instances
     */
    private array $transports = [];

    /**
     * @var array<string, BrokerInterface> Broker instances
     */
    private array $brokers = [];

    private string $defaultTransport;
    private ?string $defaultBroker;

    /**
     * @param array $config Realtime configuration
     * @param ContainerInterface|null $container DI container
     */
    public function __construct(
        private array $config = [],
        private readonly ?ContainerInterface $container = null
    ) {
        $this->defaultTransport = $config['default_transport'] ?? 'memory';
        $this->defaultBroker = $config['default_broker'] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function broadcast(string $channel, string $event, mixed $data): void
    {
        $message = Message::event($channel, $event, $data);

        // Broadcast via broker if available (multi-server)
        if ($broker = $this->broker()) {
            $broker->publish($channel, $message);
            return;
        }

        // Local broadcast only (single server)
        $channelInstance = $this->channel($channel);
        $channelInstance->broadcast($message);
    }

    /**
     * {@inheritdoc}
     */
    public function send(string $connectionId, string $event, mixed $data): void
    {
        $connection = $this->connections[$connectionId] ?? null;

        if (!$connection) {
            throw new \RuntimeException("Connection {$connectionId} not found");
        }

        $message = Message::event(null, $event, $data);
        $transport = $this->transport();
        $transport->send($connection, $message);
    }

    /**
     * {@inheritdoc}
     */
    public function sendToUser(string|int $userId, string $event, mixed $data): void
    {
        $userConnections = $this->getUserConnections($userId);

        if (empty($userConnections)) {
            return; // User not connected
        }

        $message = Message::event(null, $event, $data);
        $transport = $this->transport();

        foreach ($userConnections as $connection) {
            try {
                $transport->send($connection, $message);
            } catch (\Throwable $e) {
                error_log("Failed to send to user {$userId} connection {$connection->getId()}: {$e->getMessage()}");
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function channel(string $name): ChannelInterface
    {
        // Return cached channel if exists
        if (isset($this->channels[$name])) {
            return $this->channels[$name];
        }

        // Create new channel
        $transport = $this->transport();
        $authorizer = $this->getChannelAuthorizer($name);

        $this->channels[$name] = new Channel($name, $transport, $authorizer);

        return $this->channels[$name];
    }

    /**
     * {@inheritdoc}
     */
    public function transport(?string $name = null): TransportInterface
    {
        $name = $name ?? $this->defaultTransport;

        // Return cached instance
        if (isset($this->transports[$name])) {
            return $this->transports[$name];
        }

        // Create new transport
        $this->transports[$name] = $this->createTransport($name);

        return $this->transports[$name];
    }

    /**
     * {@inheritdoc}
     */
    public function broker(?string $name = null): ?BrokerInterface
    {
        if (!$this->defaultBroker && !$name) {
            return null; // No broker configured
        }

        $name = $name ?? $this->defaultBroker;

        // Return cached instance
        if (isset($this->brokers[$name])) {
            return $this->brokers[$name];
        }

        // Create new broker
        $this->brokers[$name] = $this->createBroker($name);

        return $this->brokers[$name];
    }

    /**
     * {@inheritdoc}
     */
    public function getUserConnections(string|int $userId): array
    {
        return array_filter(
            $this->connections,
            fn($conn) => $conn->getUserId() === $userId
        );
    }

    /**
     * {@inheritdoc}
     */
    public function disconnect(string $connectionId): void
    {
        $connection = $this->connections[$connectionId] ?? null;

        if (!$connection) {
            return;
        }

        // Unsubscribe from all channels
        foreach ($connection->getChannels() as $channelName) {
            $channel = $this->channels[$channelName] ?? null;
            if ($channel) {
                $channel->unsubscribe($connection);
            }
        }

        // Close connection
        $transport = $this->transport();
        $transport->close($connection);

        // Remove from registry
        unset($this->connections[$connectionId]);
    }

    /**
     * Register a connection.
     *
     * @param ConnectionInterface $connection
     * @return void
     */
    public function registerConnection(ConnectionInterface $connection): void
    {
        $this->connections[$connection->getId()] = $connection;
    }

    /**
     * Create transport instance.
     *
     * @param string $name Transport name
     * @return TransportInterface
     */
    private function createTransport(string $name): TransportInterface
    {
        $config = $this->config['transports'][$name] ?? [];
        $driver = $config['driver'] ?? $name;

        return match ($driver) {
            'memory' => new Transports\MemoryTransport($this),
            'websocket' => new Transports\WebSocketTransport($config, $this),
            'sse' => new Transports\SseTransport($config, $this),
            'longpolling' => new Transports\LongPollingTransport($config, $this),
            'socketio' => new Transports\SocketIOGateway($config, $this),
            default => throw new \InvalidArgumentException(
                "Unsupported transport driver: {$driver}. " .
                    "Supported drivers: memory, websocket, sse, longpolling, socketio"
            )
        };
    }

    /**
     * Create broker instance.
     *
     * @param string $name Broker name
     * @return BrokerInterface
     */
    private function createBroker(string $name): BrokerInterface
    {
        $config = $this->config['brokers'][$name] ?? [];
        $driver = $config['driver'] ?? $name;

        return match ($driver) {
            'redis' => new Brokers\RedisBroker($config, $this),
            'kafka' => new Brokers\KafkaBroker($config, $this),
            // TODO: Implement these brokers
            // 'rabbitmq' => new Brokers\RabbitMqBroker($config, $this),
            // 'nats' => new Brokers\NatsBroker($config, $this),
            // 'postgres' => new Brokers\PostgresBroker($config, $this),
            default => throw new \InvalidArgumentException(
                "Unsupported broker driver: {$driver}. " .
                    "Supported drivers: redis, kafka. " .
                    "RabbitMQ, NATS, and PostgreSQL are planned for future releases."
            )
        };
    }

    /**
     * Get channel authorizer callback.
     *
     * @param string $channelName
     * @return callable|null
     */
    private function getChannelAuthorizer(string $channelName): ?callable
    {
        // Check for pattern-based authorizers
        $authorizers = $this->config['authorizers'] ?? [];

        foreach ($authorizers as $pattern => $callback) {
            if ($this->matchesPattern($channelName, $pattern)) {
                return $callback;
            }
        }

        return null;
    }

    /**
     * Check if channel name matches pattern.
     *
     * @param string $channelName
     * @param string $pattern
     * @return bool
     */
    private function matchesPattern(string $channelName, string $pattern): bool
    {
        // Convert wildcard pattern to regex
        $regex = str_replace(['*', '.'], ['.*', '\\.'], $pattern);
        return (bool) preg_match("/^{$regex}$/", $channelName);
    }

    /**
     * Get all active channels.
     *
     * @return array<ChannelInterface>
     */
    public function getChannels(): array
    {
        return array_values($this->channels);
    }

    /**
     * Add connection to manager.
     *
     * @param ConnectionInterface $connection
     * @return void
     */
    public function addConnection(ConnectionInterface $connection): void
    {
        $this->connections[$connection->getId()] = $connection;
    }

    /**
     * Remove connection from manager.
     *
     * @param ConnectionInterface $connection
     * @return void
     */
    public function removeConnection(ConnectionInterface $connection): void
    {
        $connId = $connection->getId();

        // Unsubscribe from all channels
        foreach ($connection->getChannels() as $channelName) {
            if (isset($this->channels[$channelName])) {
                $this->channels[$channelName]->unsubscribe($connection);
            }
        }

        unset($this->connections[$connId]);
    }

    /**
     * Get all active connections.
     *
     * @return array<ConnectionInterface>
     */
    public function getAllConnections(): array
    {
        return array_values($this->connections);
    }

    /**
     * Get connection by ID.
     *
     * @param string $connectionId
     * @return ConnectionInterface|null
     */
    public function getConnection(string $connectionId): ?ConnectionInterface
    {
        return $this->connections[$connectionId] ?? null;
    }

    /**
     * Get total number of active connections.
     *
     * @return int
     */
    public function getConnectionCount(): int
    {
        return count($this->connections);
    }

    /**
     * Get total number of active channels.
     *
     * @return int
     */
    public function getChannelCount(): int
    {
        return count($this->channels);
    }

    /**
     * Get all active connections.
     *
     * @return array<ConnectionInterface>
     */
    public function getConnections(): array
    {
        return array_values($this->connections);
    }
}
