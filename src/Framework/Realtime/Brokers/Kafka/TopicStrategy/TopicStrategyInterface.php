<?php

declare(strict_types=1);

namespace Toporia\Framework\Realtime\Brokers\Kafka\TopicStrategy;

/**
 * Topic Strategy Interface
 *
 * Strategy for mapping channels to Kafka topics and partitions.
 * Allows different strategies: one-topic-per-channel, grouped-topics, etc.
 *
 * SOLID Principles:
 * - Single Responsibility: Only maps channels to topics/partitions
 * - Open/Closed: Extensible via different strategy implementations
 * - Dependency Inversion: Depends on abstraction
 *
 * @package Toporia\Framework\Realtime\Brokers\Kafka\TopicStrategy
 */
interface TopicStrategyInterface
{
    /**
     * Get topic name for a channel.
     *
     * @param string $channel Channel name
     * @return string Topic name
     */
    public function getTopicName(string $channel): string;

    /**
     * Get partition number for a channel.
     *
     * @param string $channel Channel name
     * @param int $totalPartitions Total partitions in topic
     * @return int Partition number (0-based)
     */
    public function getPartition(string $channel, int $totalPartitions): int;

    /**
     * Get message key for a channel.
     *
     * Used for partitioning and message ordering.
     *
     * @param string $channel Channel name
     * @return string|null Message key (null = no key, Kafka will round-robin)
     */
    public function getMessageKey(string $channel): ?string;

    /**
     * Get all topics that match a channel pattern.
     *
     * Used for subscribing to multiple channels.
     *
     * @param array<string> $channels Channel names
     * @return array<string> Unique topic names
     */
    public function getTopicsForChannels(array $channels): array;
}

