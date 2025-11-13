<?php

declare(strict_types=1);

namespace Toporia\Framework\Realtime\Brokers\Kafka\TopicStrategy;

/**
 * One Topic Per Channel Strategy
 *
 * Legacy strategy: Each channel maps to its own topic.
 * Used for backward compatibility.
 *
 * Performance:
 * - Simple but creates many topics
 * - Good for small scale (< 100 channels)
 * - Not recommended for large scale
 *
 * @package Toporia\Framework\Realtime\Brokers\Kafka\TopicStrategy
 */
final class OneTopicPerChannelStrategy implements TopicStrategyInterface
{
    public function __construct(
        private readonly string $topicPrefix = 'realtime'
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function getTopicName(string $channel): string
    {
        // Sanitize channel name for Kafka topic naming
        $sanitized = preg_replace('/[^a-zA-Z0-9._-]/', '_', $channel);
        return "{$this->topicPrefix}_{$sanitized}";
    }

    /**
     * {@inheritdoc}
     */
    public function getPartition(string $channel, int $totalPartitions): int
    {
        // Each topic has 1 partition (legacy behavior)
        return 0;
    }

    /**
     * {@inheritdoc}
     */
    public function getMessageKey(string $channel): ?string
    {
        // No key needed (each channel = 1 topic)
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function getTopicsForChannels(array $channels): array
    {
        $topics = [];
        foreach ($channels as $channel) {
            $topics[] = $this->getTopicName($channel);
        }
        return array_unique($topics);
    }
}

