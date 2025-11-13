<?php

declare(strict_types=1);

namespace Toporia\Framework\Console\Commands\Kafka\Examples;

use Toporia\Framework\Console\Commands\Kafka\Base\AbstractJsonKafkaConsumer;
use Toporia\Framework\Console\Commands\Kafka\Contracts\SingleMessageHandlerInterface;
use Toporia\Framework\Realtime\Contracts\MessageInterface;

/**
 * Example JSON Kafka Consumer
 *
 * Example implementation of a JSON Kafka consumer.
 * This demonstrates how to create a simple consumer for JSON messages.
 *
 * Usage:
 *   php console kafka:consume:json-example
 *
 * @package Toporia\Framework\Console\Commands\Kafka\Examples
 */
final class ExampleJsonConsumer extends AbstractJsonKafkaConsumer implements SingleMessageHandlerInterface
{
    protected string $signature = 'kafka:consume:json-example';
    protected string $description = 'Example JSON Kafka consumer';

    /**
     * {@inheritdoc}
     */
    protected function getTopic(): string
    {
        return config('kafka.topics.json', 'realtime-json');
    }

    /**
     * {@inheritdoc}
     */
    protected function getGroupId(): string
    {
        return config('kafka.consumer_group_id.json', 'realtime-json-consumers');
    }

    /**
     * {@inheritdoc}
     */
    protected function getOffset(): string
    {
        return config('kafka.offset_reset_by_type.json', 'earliest');
    }

    /**
     * {@inheritdoc}
     */
    public function handleMessage(MessageInterface $message, array $metadata = []): void
    {
        // Process the message
        $this->line("Received message: {$message->getId()}");
        $this->line("Channel: {$message->getChannel()}");
        $this->line("Event: {$message->getEvent()}");
        $this->line("Data: " . json_encode($message->getData(), JSON_PRETTY_PRINT));

        // Your business logic here
        // Example: Save to database, send notification, etc.
    }
}

