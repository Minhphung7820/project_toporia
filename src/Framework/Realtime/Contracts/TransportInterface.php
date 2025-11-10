<?php

declare(strict_types=1);

namespace Toporia\Framework\Realtime\Contracts;

/**
 * Transport Interface
 *
 * Defines contract for realtime transport layers.
 * Transports handle client-server communication (WebSocket, SSE, Long-polling).
 *
 * Available Transports:
 * - WebSocket (WS/WSS): Full-duplex, low latency, best performance
 * - Server-Sent Events (SSE): One-way (server→client), HTTP/2, simple
 * - Long-polling: Fallback, works everywhere, higher latency
 *
 * Performance Characteristics:
 * - WebSocket: ~1-5ms latency, 1000+ concurrent connections
 * - SSE: ~10-50ms latency, 100+ concurrent connections
 * - Long-polling: ~100-500ms latency, 50+ concurrent connections
 *
 * SOLID Principles:
 * - Single Responsibility: Each transport handles one protocol
 * - Open/Closed: Extensible via custom transports
 * - Liskov Substitution: All transports are interchangeable
 * - Interface Segregation: Minimal interface
 * - Dependency Inversion: Depends on abstractions
 *
 * @package Toporia\Framework\Realtime\Contracts
 */
interface TransportInterface
{
    /**
     * Send message to a connection.
     *
     * Delivers message to single client connection.
     *
     * Performance: O(1) - Direct connection write
     *
     * @param ConnectionInterface $connection Target connection
     * @param MessageInterface $message Message to send
     * @return void
     * @throws \RuntimeException If send fails
     */
    public function send(ConnectionInterface $connection, MessageInterface $message): void;

    /**
     * Broadcast message to all connections.
     *
     * Sends message to all active connections.
     *
     * Performance: O(N) where N = number of connections
     * Optimization: Can be parallelized for WebSocket
     *
     * @param MessageInterface $message Message to broadcast
     * @return void
     */
    public function broadcast(MessageInterface $message): void;

    /**
     * Broadcast message to connections in a channel.
     *
     * Sends message only to clients subscribed to channel.
     *
     * Performance: O(M) where M = subscribers in channel
     *
     * @param string $channel Channel name
     * @param MessageInterface $message Message to send
     * @return void
     */
    public function broadcastToChannel(string $channel, MessageInterface $message): void;

    /**
     * Get active connections count.
     *
     * @return int Number of active connections
     */
    public function getConnectionCount(): int;

    /**
     * Check if connection is active.
     *
     * @param string $connectionId Connection identifier
     * @return bool
     */
    public function hasConnection(string $connectionId): bool;

    /**
     * Close a connection.
     *
     * @param ConnectionInterface $connection Connection to close
     * @param int $code Close code (1000 = normal closure)
     * @param string $reason Close reason
     * @return void
     */
    public function close(ConnectionInterface $connection, int $code = 1000, string $reason = ''): void;

    /**
     * Start transport server.
     *
     * Blocks until server is stopped.
     *
     * @param string $host Server host
     * @param int $port Server port
     * @return void
     */
    public function start(string $host, int $port): void;

    /**
     * Stop transport server.
     *
     * @return void
     */
    public function stop(): void;

    /**
     * Get transport name.
     *
     * @return string (websocket, sse, longpolling)
     */
    public function getName(): string;
}
