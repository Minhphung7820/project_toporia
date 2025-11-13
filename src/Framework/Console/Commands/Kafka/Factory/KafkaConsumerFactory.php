<?php

declare(strict_types=1);

namespace Toporia\Framework\Console\Commands\Kafka\Factory;

use Toporia\Framework\Realtime\Contracts\RealtimeManagerInterface;
use Toporia\Framework\Realtime\Brokers\KafkaBroker;

/**
 * Kafka Consumer Factory
 *
 * Factory for creating and configuring Kafka consumers.
 * Provides centralized configuration and broker management.
 *
 * SOLID Principles:
 * - Single Responsibility: Only creates and configures consumers
 * - Open/Closed: Extensible via factory methods
 * - Dependency Inversion: Depends on RealtimeManagerInterface
 *
 * @package Toporia\Framework\Console\Commands\Kafka\Factory
 */
final class KafkaConsumerFactory
{
    /**
     * @param RealtimeManagerInterface $realtime Realtime manager
     */
    public function __construct(
        private readonly RealtimeManagerInterface $realtime
    ) {
    }

    /**
     * Get Kafka broker instance.
     *
     * @param string|null $brokerName Broker name (default: 'kafka')
     * @return KafkaBroker
     * @throws \RuntimeException If broker not found
     */
    public function getBroker(?string $brokerName = null): KafkaBroker
    {
        $brokerName = $brokerName ?? config('realtime.default_broker', 'kafka');
        $broker = $this->realtime->broker($brokerName);

        if (!$broker) {
            throw new \RuntimeException(
                "Kafka broker '{$brokerName}' not found. " .
                "Configure it in config/realtime.php"
            );
        }

        if (!$broker instanceof KafkaBroker) {
            throw new \RuntimeException("Broker '{$brokerName}' is not a Kafka broker");
        }

        return $broker;
    }

    /**
     * Get Kafka configuration.
     *
     * @param string|null $brokerName Broker name
     * @return array<string, mixed>
     */
    public function getConfig(?string $brokerName = null): array
    {
        $brokerName = $brokerName ?? config('realtime.default_broker', 'kafka');
        return config("realtime.brokers.{$brokerName}", []);
    }

    /**
     * Get brokers list.
     *
     * @param string|null $brokerName Broker name
     * @return array<string>
     */
    public function getBrokers(?string $brokerName = null): array
    {
        $config = $this->getConfig($brokerName);
        $brokers = $config['brokers'] ?? ['localhost:9092'];
        return is_array($brokers) ? $brokers : [$brokers];
    }

    /**
     * Get topic prefix.
     *
     * @param string|null $brokerName Broker name
     * @return string
     */
    public function getTopicPrefix(?string $brokerName = null): string
    {
        $config = $this->getConfig($brokerName);
        return $config['topic_prefix'] ?? 'realtime';
    }

    /**
     * Get consumer group.
     *
     * @param string|null $brokerName Broker name
     * @return string
     */
    public function getConsumerGroup(?string $brokerName = null): string
    {
        $config = $this->getConfig($brokerName);
        return $config['consumer_group'] ?? 'realtime-servers';
    }
}

