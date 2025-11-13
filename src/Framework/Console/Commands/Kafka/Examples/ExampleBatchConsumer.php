<?php

declare(strict_types=1);

namespace Toporia\Framework\Console\Commands\Kafka\Examples;

use Toporia\Framework\Console\Commands\Kafka\Base\AbstractBatchKafkaConsumer;
use Toporia\Framework\Console\Commands\Kafka\Contracts\BatchingMessagesHandlerInterface;
use Toporia\Framework\Realtime\Contracts\MessageInterface;
use Toporia\Framework\Support\Collection;

/**
 * Example Batch Kafka Consumer
 *
 * Example implementation of a batch Kafka consumer.
 * This demonstrates how to create a consumer that processes messages in batches.
 *
 * Usage:
 *   php console kafka:consume:batch-example
 *
 * @package Toporia\Framework\Console\Commands\Kafka\Examples
 */
final class ExampleBatchConsumer extends AbstractBatchKafkaConsumer implements BatchingMessagesHandlerInterface
{
    protected string $signature = 'kafka:consume:batch-example';
    protected string $description = 'Example batch Kafka consumer';

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
    protected function getBatchSizeLimit(): int
    {
        return 100; // Process 100 messages per batch
    }

    /**
     * {@inheritdoc}
     */
    protected function getBatchReleaseInterval(): int
    {
        return 1500; // Release batch after 1.5 seconds even if not full
    }

    /**
     * {@inheritdoc}
     */
    public function handleMessages(Collection $messages): void
    {
        $this->line("Processing batch of " . $messages->count() . " messages");

        foreach ($messages as $item) {
            /** @var MessageInterface $message */
            $message = $item['message'] ?? null;
            $metadata = $item['metadata'] ?? [];

            if (!$message instanceof MessageInterface) {
                continue;
            }

            // Process each message in the batch
            // Your business logic here
            // Example: Bulk insert to database, batch API calls, etc.
        }

        $this->line("Batch processed successfully");
    }
}
