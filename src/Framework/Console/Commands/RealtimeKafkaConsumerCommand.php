<?php

declare(strict_types=1);

namespace Toporia\Framework\Console\Commands;

use Toporia\Framework\Console\Commands\Kafka\Base\AbstractBatchKafkaConsumer;
use Toporia\Framework\Console\Commands\Kafka\Contracts\BatchingMessagesHandlerInterface;
use Toporia\Framework\Console\Commands\Kafka\DeadLetterQueue\DeadLetterQueueHandler;
use Toporia\Framework\Realtime\Contracts\MessageInterface;
use Toporia\Framework\Realtime\Contracts\RealtimeManagerInterface;
use Toporia\Framework\Support\Collection;

/**
 * Realtime Kafka Consumer Command
 *
 * Consume messages from Kafka topics for realtime communication.
 * Runs as a long-lived process, consuming messages and broadcasting to local connections.
 *
 * Performance Optimizations:
 * - Batch processing for high throughput (configurable batch size)
 * - Non-blocking poll with timeout (prevents CPU spinning)
 * - Graceful shutdown with signal handling
 * - Automatic reconnection on connection loss
 * - Memory-efficient message processing
 *
 * Usage:
 *   php console realtime:kafka:consume
 *   php console realtime:kafka:consume --broker=kafka
 *   php console realtime:kafka:consume --channels=user.1,user.2,public.news
 *   php console realtime:kafka:consume --batch-size=100 --timeout=1000
 *   php console realtime:kafka:consume --max-messages=10000
 *
 * Options:
 *   --broker=name          Kafka broker name from config (default: kafka)
 *   --channels=ch1,ch2     Comma-separated list of channels to subscribe
 *   --batch-size=N         Messages per batch (default: 100)
 *   --timeout=N            Poll timeout in milliseconds (default: 1000)
 *   --max-messages=N       Maximum messages to process before exit (0 = unlimited)
 *   --stop-when-empty      Stop when no messages available (testing)
 *
 * Architecture:
 * - Subscribes to Kafka topics (one per channel)
 * - Consumes messages in batches for performance
 * - Broadcasts messages to local RealtimeManager
 * - Supports graceful shutdown (SIGTERM, SIGINT)
 *
 * SOLID Principles:
 * - Single Responsibility: Only consumes Kafka messages
 * - Open/Closed: Extensible via broker configuration
 * - Dependency Inversion: Depends on BrokerInterface
 *
 * @package Toporia\Framework\Console\Commands
 */
final class RealtimeKafkaConsumerCommand extends AbstractBatchKafkaConsumer implements BatchingMessagesHandlerInterface
{
    protected string $signature = 'realtime:kafka:consume {--broker=kafka} {--channels=*} {--batch-size=100} {--timeout=1000} {--max-messages=0} {--stop-when-empty} {--dlq-enabled}';

    protected string $description = 'Consume messages from Kafka for realtime communication';

    /**
     * @var array<string> Channels to subscribe to
     */
    private array $channels = [];

    /**
     * @var DeadLetterQueueHandler|null DLQ handler
     */
    private ?DeadLetterQueueHandler $dlqHandler = null;

    /**
     * @param RealtimeManagerInterface $realtime Realtime manager instance
     */
    public function __construct(
        RealtimeManagerInterface $realtime
    ) {
        parent::__construct($realtime);
    }

    /**
     * Get topic name.
     *
     * For realtime consumer, we use the first channel as topic.
     * In practice, each channel maps to a topic.
     *
     * @return string
     */
    protected function getTopic(): string
    {
        // For realtime, we subscribe to multiple channels
        // This is a special case - we'll override consumeBatches to handle multiple channels
        return $this->channels[0] ?? 'realtime';
    }

    /**
     * Get consumer group ID.
     *
     * @return string
     */
    protected function getGroupId(): string
    {
        $broker = $this->getBroker();
        $config = config('realtime.brokers.kafka', []);
        return $config['consumer_group'] ?? 'realtime-servers';
    }

    /**
     * Get offset reset strategy.
     *
     * @return string
     */
    protected function getOffset(): string
    {
        return config('kafka.offset_reset', 'earliest');
    }

    /**
     * Get batch size limit.
     *
     * @return int
     */
    protected function getBatchSizeLimit(): int
    {
        return (int) $this->option('batch-size', 100);
    }

    /**
     * Get batch release interval in milliseconds.
     *
     * @return int
     */
    protected function getBatchReleaseInterval(): int
    {
        return (int) config('kafka.batch_release_interval', 1500); // 1.5 seconds
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

            // Initialize DLQ if enabled
            if ($this->hasOption('dlq-enabled')) {
                $this->dlqHandler = new DeadLetterQueueHandler(
                    dlqTopicPrefix: config('kafka.dlq_topic_prefix', 'dlq'),
                    maxRetries: config('kafka.dlq_max_retries', 3)
                );
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
        $batchSize = $this->getBatchSizeLimit();
        $timeout = (int) $this->option('timeout', 1000);

        // Display header
        $this->displayHeader('Realtime Batch Consumer', [
            'broker' => $this->getBrokerName(),
            'channels' => implode(', ', $this->channels),
            'batch_size' => $batchSize,
            'dlq' => $this->dlqHandler ? 'enabled' : 'disabled',
        ]);

        // Setup graceful shutdown
        $this->setupSignalHandlers(function () use ($broker) {
            $broker->stopConsuming();
        });

        // Subscribe to all channels
        foreach ($this->channels as $channel) {
            $broker->subscribe($channel, function ($message) use ($channel) {
                // Messages will be collected in batches
                // This callback is called by KafkaBroker's consume loop
            });
            $this->line("Subscribed to channel: <info>{$channel}</info>");
        }

        // Start consuming
        $maxMessages = (int) $this->option('max-messages', 0);
        if ($maxMessages > 0) {
            $this->consumeBatchesWithLimit($broker, $timeout, $batchSize, $maxMessages);
        } else {
            $this->consumeBatches($broker, $timeout, $batchSize);
        }

        // Display summary
        $this->displaySummary();

        return 0;
    }

    /**
     * {@inheritdoc}
     *
     * Handle a batch of messages from Kafka.
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
                // Handle error with DLQ if enabled
                if ($this->dlqHandler && isset($message)) {
                    $shouldRetry = $this->dlqHandler->handleFailedMessage(
                        $message,
                        $e,
                        $metadata,
                        function (string $dlqTopic, string $payload) {
                            // Publish to DLQ topic
                            $broker = $this->getBroker();
                            // Note: This would need a publish method on KafkaBroker
                            // For now, log the error
                            error_log("DLQ: Would publish to {$dlqTopic}: {$payload}");
                        }
                    );

                    if ($shouldRetry) {
                        // Retry logic would be handled by DLQ handler
                        // For now, just log
                        error_log("Message will be retried: {$e->getMessage()}");
                    }
                } else {
                    $this->logError($e, [
                        'message_id' => $message->getId() ?? 'unknown',
                        'channel' => $channel ?? 'unknown',
                    ]);
                }
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
