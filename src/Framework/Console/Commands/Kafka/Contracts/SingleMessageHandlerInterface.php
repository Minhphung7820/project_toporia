<?php

declare(strict_types=1);

namespace Toporia\Framework\Console\Commands\Kafka\Contracts;

use Toporia\Framework\Realtime\Contracts\MessageInterface;

/**
 * Single Message Handler Interface
 *
 * Interface for handling single Kafka messages.
 * Used by consumers that process messages one at a time.
 *
 * SOLID Principles:
 * - Single Responsibility: Only defines contract for single message handling
 * - Interface Segregation: Small, focused interface
 * - Dependency Inversion: Consumers depend on this abstraction
 *
 * @package Toporia\Framework\Console\Commands\Kafka\Contracts
 */
interface SingleMessageHandlerInterface
{
    /**
     * Handle a single Kafka message.
     *
     * @param MessageInterface $message Consumed message
     * @param array<string, mixed> $metadata Message metadata (partition, offset, topic, etc.)
     * @return void
     * @throws \Throwable If message processing fails (will trigger DLQ)
     */
    public function handleMessage(MessageInterface $message, array $metadata = []): void;
}
