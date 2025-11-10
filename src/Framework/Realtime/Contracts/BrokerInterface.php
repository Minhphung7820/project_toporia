<?php

declare(strict_types=1);

namespace Toporia\Framework\Realtime\Contracts;

/**
 * Broker Interface
 *
 * Defines contract for message broker systems.
 * Brokers enable fan-out, multi-server scaling, and persistence.
 *
 * Available Brokers:
 * - Redis Pub/Sub: Fast, simple, ephemeral messages
 * - RabbitMQ (AMQP): Durable, routing, message persistence
 * - NATS: Ultra-fast, wildcard subjects, clustering
 * - Kafka: High-throughput, replay, message history
 * - PostgreSQL LISTEN/NOTIFY: DB-based, simple setup
 *
 * Performance Characteristics:
 * - Redis: ~0.1ms latency, 100k+ msg/sec
 * - RabbitMQ: ~1ms latency, 50k+ msg/sec, durable
 * - NATS: ~0.05ms latency, 1M+ msg/sec, clustering
 * - Kafka: ~5ms latency, 1M+ msg/sec, persistence
 * - PostgreSQL: ~10ms latency, 10k+ msg/sec
 *
 * Use Cases:
 * - Single server: No broker needed (in-memory)
 * - Multi-server: Redis Pub/Sub (simple, fast)
 * - Enterprise: RabbitMQ (durable, routing)
 * - High-throughput: NATS or Kafka
 *
 * @package Toporia\Framework\Realtime\Contracts
 */
interface BrokerInterface
{
    /**
     * Publish message to a channel.
     *
     * Sends message to all subscribers of the channel across all servers.
     *
     * Performance: O(1) for Redis/NATS, O(log N) for Kafka
     *
     * @param string $channel Channel name
     * @param MessageInterface $message Message to publish
     * @return void
     */
    public function publish(string $channel, MessageInterface $message): void;

    /**
     * Subscribe to a channel.
     *
     * Receives messages published to the channel.
     * Callback is invoked for each message.
     *
     * Performance: O(1) subscription setup
     *
     * @param string $channel Channel name (supports wildcards for NATS)
     * @param callable $callback Invoked with (MessageInterface $message)
     * @return void
     */
    public function subscribe(string $channel, callable $callback): void;

    /**
     * Unsubscribe from a channel.
     *
     * @param string $channel Channel name
     * @return void
     */
    public function unsubscribe(string $channel): void;

    /**
     * Get number of subscribers for a channel.
     *
     * @param string $channel Channel name
     * @return int Subscriber count
     */
    public function getSubscriberCount(string $channel): int;

    /**
     * Check if broker is connected.
     *
     * @return bool
     */
    public function isConnected(): bool;

    /**
     * Disconnect from broker.
     *
     * @return void
     */
    public function disconnect(): void;

    /**
     * Get broker name.
     *
     * @return string (redis, rabbitmq, nats, kafka, postgres)
     */
    public function getName(): string;
}
