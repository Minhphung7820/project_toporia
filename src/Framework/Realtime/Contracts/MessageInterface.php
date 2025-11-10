<?php

declare(strict_types=1);

namespace Toporia\Framework\Realtime\Contracts;

/**
 * Message Interface
 *
 * Represents a realtime message sent/received over transports.
 *
 * Message Format:
 * {
 *   "type": "event",           // event, subscribe, unsubscribe, error, ping, pong
 *   "channel": "chat.room.1",  // Target channel
 *   "event": "message.sent",   // Event name
 *   "data": {...},             // Payload data
 *   "timestamp": 1234567890,   // Unix timestamp
 *   "id": "msg_xxxxx"          // Message ID
 * }
 *
 * Performance:
 * - JSON encoding: ~0.01ms for typical message
 * - Binary encoding (MessagePack): ~0.005ms, 30% smaller
 * - Compression (gzip): ~0.1ms, 50-70% smaller
 *
 * @package Toporia\Framework\Realtime\Contracts
 */
interface MessageInterface
{
    /**
     * Get message unique identifier.
     *
     * @return string
     */
    public function getId(): string;

    /**
     * Get message type.
     *
     * Types: event, subscribe, unsubscribe, error, ping, pong, presence
     *
     * @return string
     */
    public function getType(): string;

    /**
     * Get target channel.
     *
     * @return string|null
     */
    public function getChannel(): ?string;

    /**
     * Get event name.
     *
     * Example: message.sent, user.joined, typing.started
     *
     * @return string|null
     */
    public function getEvent(): ?string;

    /**
     * Get message data/payload.
     *
     * @return mixed
     */
    public function getData(): mixed;

    /**
     * Get message timestamp.
     *
     * @return int Unix timestamp
     */
    public function getTimestamp(): int;

    /**
     * Convert message to JSON.
     *
     * @return string JSON string
     */
    public function toJson(): string;

    /**
     * Convert message to array.
     *
     * @return array
     */
    public function toArray(): array;

    /**
     * Create message from JSON.
     *
     * @param string $json JSON string
     * @return static
     */
    public static function fromJson(string $json): static;

    /**
     * Create message from array.
     *
     * @param array $data Message data
     * @return static
     */
    public static function fromArray(array $data): static;
}
