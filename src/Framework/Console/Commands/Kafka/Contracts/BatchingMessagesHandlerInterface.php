<?php

declare(strict_types=1);

namespace Toporia\Framework\Console\Commands\Kafka\Contracts;

use Toporia\Framework\Support\Collection;

/**
 * Batching Messages Handler Interface
 *
 * Interface for handling batches of Kafka messages.
 * Used by consumers that process messages in batches for better performance.
 *
 * Performance Benefits:
 * - Reduced network round-trips
 * - Better throughput (1000+ messages/sec)
 * - Atomic batch processing
 * - Lower overhead per message
 *
 * SOLID Principles:
 * - Single Responsibility: Only defines contract for batch message handling
 * - Interface Segregation: Separate from single message handling
 * - Dependency Inversion: Consumers depend on this abstraction
 *
 * @package Toporia\Framework\Console\Commands\Kafka\Contracts
 */
interface BatchingMessagesHandlerInterface
{
    /**
     * Handle a batch of Kafka messages.
     *
     * Each item in the collection is an array with:
     * - 'message': MessageInterface instance
     * - 'metadata': array with partition, offset, topic, timestamp, etc.
     *
     * @param Collection $messages Collection of message arrays
     * @return void
     * @throws \Throwable If batch processing fails (will trigger DLQ for entire batch)
     */
    public function handleMessages(Collection $messages): void;
}
