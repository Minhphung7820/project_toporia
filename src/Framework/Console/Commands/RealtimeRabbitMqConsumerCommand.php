<?php

declare(strict_types=1);

namespace Toporia\Framework\Console\Commands;

use Toporia\Framework\Console\Commands\Kafka\Contracts\BatchingMessagesHandlerInterface;
use Toporia\Framework\Console\Commands\RabbitMq\Base\AbstractBatchRabbitMqConsumer;
use Toporia\Framework\Realtime\Brokers\RabbitMqBroker;
use Toporia\Framework\Realtime\Contracts\MessageInterface;
use Toporia\Framework\Realtime\Contracts\RealtimeManagerInterface;
use Toporia\Framework\Support\Collection;

/**
 * Realtime RabbitMQ Consumer Command
 *
 * Consume messages from RabbitMQ for realtime communication.
 * Runs as a long-lived process, consuming messages and broadcasting to local connections.
 *
 * Performance Optimizations:
 * - Batch processing for high throughput (configurable batch size)
 * - Prefetch control for flow control
 * - Graceful shutdown with signal handling
 * - Automatic reconnection on connection loss
 * - Memory-efficient message processing
 * - Durable message queues (guaranteed delivery)
 *
 * Usage:
 *   php console realtime:rabbitmq:consume
 *   php console realtime:rabbitmq:consume --broker=rabbitmq
 *   php console realtime:rabbitmq:consume --channels=user.1,user.2,public.news
 *   php console realtime:rabbitmq:consume --batch-size=100 --timeout=1000
 *   php console realtime:rabbitmq:consume --max-messages=10000
 *
 * Options:
 *   --broker=name          RabbitMQ broker name from config (default: rabbitmq)
 *   --channels=ch1,ch2     Comma-separated list of channels to subscribe
 *   --batch-size=N         Messages per batch (default: 100)
 *   --timeout=N            Poll timeout in milliseconds (default: 1000)
 *   --max-messages=N       Maximum messages to process before exit (0 = unlimited)
 *   --stop-when-empty      Stop when no messages available (testing)
 *
 * Architecture:
 * - Subscribes to RabbitMQ queues (one per channel via routing keys)
 * - Consumes messages in batches for performance
 * - Broadcasts messages to local RealtimeManager
 * - Supports graceful shutdown (SIGTERM, SIGINT)
 * - Durable queues ensure message delivery
 *
 * SOLID Principles:
 * - Single Responsibility: Only consumes RabbitMQ messages
 * - Open/Closed: Extensible via broker configuration
 * - Dependency Inversion: Depends on BrokerInterface
 *
 * @package Toporia\Framework\Console\Commands
 */
final class RealtimeRabbitMqConsumerCommand extends AbstractBatchRabbitMqConsumer implements BatchingMessagesHandlerInterface
{
    protected string $signature = 'realtime:rabbitmq:consume {--broker=rabbitmq} {--channels=*} {--batch-size=100} {--timeout=1000} {--max-messages=0} {--stop-when-empty}';

    protected string $description = 'Consume messages from RabbitMQ for realtime communication';

    /**
     * @var array<string> Channels to subscribe to
     */
    private array $channels = [];

    /**
     * @param RealtimeManagerInterface $realtime Realtime manager instance
     */
    public function __construct(
        RealtimeManagerInterface $realtime
    ) {
        parent::__construct($realtime);
    }

    /**
     * {@inheritdoc}
     */
    protected function getChannels(): array
    {
        if (!empty($this->channels)) {
            return $this->channels;
        }

        // Parse from options
        $channelsOption = $this->option('channels', []);
        $this->channels = $this->parseChannels($channelsOption);

        return $this->channels;
    }

    /**
     * {@inheritdoc}
     */
    protected function getBatchSizeLimit(): int
    {
        return (int) $this->option('batch-size', 100);
    }

    /**
     * {@inheritdoc}
     */
    protected function getBatchReleaseInterval(): int
    {
        return (int) config('rabbitmq.batch_release_interval', 1500); // 1.5 seconds
    }

    /**
     * {@inheritdoc}
     */
    public function handle(): int
    {
        try {
            // Parse channels
            $channelsOption = $this->option('channels', []);
            $this->channels = $this->parseChannels($channelsOption);

            if (empty($this->channels)) {
                $this->warn('No channels specified. Use --channels=channel1,channel2');
                $this->warn('Example: --channels=user.1,public.news,presence-chat');
                return 1;
            }

            // Override parent handle to customize for multiple channels
            return $this->handleMultipleChannels();
        } catch (\Throwable $e) {
            $this->error("Consumer crashed: {$e->getMessage()}");

            if ($this->hasOption('verbose')) {
                $this->line($e->getTraceAsString());
            }

            return 1;
        }
    }

    /**
     * Handle multiple channels consumption.
     *
     * @return int
     */
    private function handleMultipleChannels(): int
    {
        $broker = $this->getBroker();

        if (!$broker instanceof RabbitMqBroker) {
            $this->error("Broker is not a RabbitMQ broker. Got: " . get_class($broker));
            return 1;
        }

        $batchSize = $this->getBatchSizeLimit();
        $timeout = (int) $this->option('timeout', 1000);

        // Display header
        $this->displayHeader('Realtime Batch Consumer', [
            'broker' => $this->getBrokerName(),
            'channels' => implode(', ', $this->channels),
            'batch_size' => $batchSize,
        ]);

        // Setup graceful shutdown
        $this->setupSignalHandlers(function () use ($broker) {
            $broker->stopConsuming();
        });

        // Subscribe to all channels
        foreach ($this->channels as $channel) {
            $broker->subscribe($channel, function (MessageInterface $message) use ($channel) {
                // Messages will be collected in batches
                // This callback is called by RabbitMqBroker's consume loop
            });
            $this->line("Subscribed to channel: <info>{$channel}</info>");
        }

        // Start consuming
        $maxMessages = (int) $this->option('max-messages', 0);
        if ($maxMessages > 0) {
            $this->consumeBatchesWithLimit($broker, $this->channels, $timeout, $batchSize, $maxMessages);
        } else {
            $this->consumeBatches($broker, $this->channels, $timeout, $batchSize);
        }

        // Display summary
        $this->displaySummary();

        return 0;
    }

    /**
     * {@inheritdoc}
     *
     * Handle a batch of messages from RabbitMQ.
     * Broadcasts each message to local RealtimeManager.
     */
    public function handleMessages(Collection $messages): void
    {
        foreach ($messages as $item) {
            try {
                /** @var MessageInterface $message */
                $message = $item['message'] ?? null;
                $metadata = $item['metadata'] ?? [];

                if (!$message instanceof MessageInterface) {
                    continue;
                }

                // Extract channel from metadata or message
                $channel = $metadata['channel'] ?? $message->getChannel() ?? $this->channels[0] ?? 'default';

                // Broadcast locally only (do NOT publish to broker again to prevent loop)
                // This message came from broker, so we only broadcast to local clients
                if ($message->getEvent() && $message->getData() !== null) {
                    $this->realtime->broadcastLocal(
                        $channel,
                        $message->getEvent(),
                        $message->getData()
                    );
                }
            } catch (\Throwable $e) {
                $this->logError($e, [
                    'message_id' => $message->getId() ?? 'unknown',
                    'channel' => $channel ?? 'unknown',
                ]);
            }
        }
    }

    /**
     * Parse channels from options.
     *
     * Supports:
     * - Single value: --channels=ch1,ch2,ch3
     * - Multiple values: --channels=ch1 --channels=ch2
     *
     * @param array|string $channelsOption Channel option value
     * @return array<string> Channel names
     */
    private function parseChannels(array|string $channelsOption): array
    {
        if (is_string($channelsOption)) {
            $channelsOption = [$channelsOption];
        }

        $channels = [];

        foreach ($channelsOption as $value) {
            // Split comma-separated values
            $parts = explode(',', $value);
            foreach ($parts as $part) {
                $part = trim($part);
                if ($part !== '') {
                    $channels[] = $part;
                }
            }
        }

        return array_unique($channels);
    }
}

