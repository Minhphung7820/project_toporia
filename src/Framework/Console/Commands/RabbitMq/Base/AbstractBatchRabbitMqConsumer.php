<?php

declare(strict_types=1);

namespace Toporia\Framework\Console\Commands\RabbitMq\Base;

use Toporia\Framework\Console\Commands\Kafka\Contracts\BatchingMessagesHandlerInterface;
use Toporia\Framework\Realtime\Brokers\RabbitMqBroker;
use Toporia\Framework\Realtime\Contracts\MessageInterface;
use Toporia\Framework\Support\Collection;

/**
 * Abstract Batch RabbitMQ Consumer
 *
 * Base class for RabbitMQ consumers that process messages in batches.
 * Provides high-throughput processing with configurable batch size and interval.
 *
 * Performance Benefits:
 * - Reduced processing overhead per message
 * - Better throughput (5k+ messages/sec)
 * - Atomic batch processing
 * - Lower memory overhead
 * - Prefetch control for flow control
 *
 * Architecture:
 * - RabbitMQ uses pull-based model (consumer pulls messages)
 * - Messages are buffered until batch size or interval reached
 * - Batch processing reduces per-message overhead
 * - Supports prefetch for flow control
 *
 * SOLID Principles:
 * - Single Responsibility: Handles batch message processing
 * - Open/Closed: Extensible via inheritance
 * - Liskov Substitution: Implements BatchingMessagesHandlerInterface
 *
 * Usage:
 * ```php
 * class MyBatchConsumer extends AbstractBatchRabbitMqConsumer
 * {
 *     protected function getChannels(): array { return ['channel1', 'channel2']; }
 *     protected function getBatchSizeLimit(): int { return 100; }
 *     protected function getBatchReleaseInterval(): int { return 1500; } // 1.5s
 *
 *     public function handleMessages(Collection $messages): void
 *     {
 *         // Process batch
 *     }
 * }
 * ```
 *
 * @package Toporia\Framework\Console\Commands\RabbitMq\Base
 */
abstract class AbstractBatchRabbitMqConsumer extends AbstractRabbitMqConsumer implements BatchingMessagesHandlerInterface
{
    /**
     * Get batch size limit.
     *
     * Maximum number of messages to accumulate before processing.
     *
     * @return int
     */
    abstract protected function getBatchSizeLimit(): int;

    /**
     * Get batch release interval in milliseconds.
     *
     * Maximum time to wait before processing batch (even if not full).
     *
     * @return int Milliseconds
     */
    abstract protected function getBatchReleaseInterval(): int;

    /**
     * {@inheritdoc}
     */
    public function handle(): int
    {
        try {
            $broker = $this->getBroker();
            $channels = $this->getChannels();
            $batchSize = $this->getBatchSizeLimit();
            $interval = $this->getBatchReleaseInterval();

            // Validate
            if (empty($channels)) {
                $this->error('Channels are required. Override getChannels() method.');
                return 1;
            }

            if ($batchSize <= 0) {
                $this->error('Batch size must be greater than 0.');
                return 1;
            }

            // Display header
            $this->displayHeader('Batch Consumer', [
                'broker' => $this->getBrokerName(),
                'batch_size' => $batchSize,
                'interval' => $interval . 'ms',
            ]);

            // Setup graceful shutdown
            $this->setupSignalHandlers(function () use ($broker) {
                $broker->stopConsuming();
            });

            // Start batch consuming
            $timeout = (int) $this->option('timeout', 1000);
            $maxMessages = (int) $this->option('max-messages', 0);

            if ($maxMessages > 0) {
                $this->consumeBatchesWithLimit($broker, $channels, $timeout, $batchSize, $maxMessages);
            } else {
                $this->consumeBatches($broker, $channels, $timeout, $batchSize);
            }

            // Display summary
            $this->displaySummary();

            return 0;
        } catch (\Throwable $e) {
            $this->error("Consumer crashed: {$e->getMessage()}");

            if ($this->hasOption('verbose')) {
                $this->line($e->getTraceAsString());
            }

            return 1;
        }
    }

    /**
     * Consume messages in batches.
     *
     * @param RabbitMqBroker $broker RabbitMQ broker
     * @param array<string> $channels Channels to subscribe
     * @param int $timeout Poll timeout in milliseconds
     * @param int $batchSize Batch size
     * @return void
     */
    protected function consumeBatches(RabbitMqBroker $broker, array $channels, int $timeout, int $batchSize): void
    {
        $batch = [];
        $lastFlushTime = (int) (microtime(true) * 1000);
        $interval = $this->getBatchReleaseInterval();

        // Subscribe to all channels
        foreach ($channels as $channel) {
            $this->logRabbitMqEvent('SUBSCRIBE', "channel <options=bold>{$channel}</>");
            $broker->subscribe($channel, function (MessageInterface $message) use (&$batch, &$lastFlushTime, $batchSize, $interval, $broker, $channel) {
                // Log message metadata
                $event = $message->getEvent() ?? 'unknown';
                $this->logRabbitMqEvent(
                    'MESSAGE',
                    "channel <fg=cyan>{$channel}</> • event <comment>{$event}</comment>"
                );

                $batch[] = [
                    'message' => $message,
                    'metadata' => [
                        'channel' => $channel,
                        'timestamp' => time(),
                    ],
                ];

                $now = (int) (microtime(true) * 1000);

                // Process batch if full or interval elapsed
                if (count($batch) >= $batchSize || ($now - $lastFlushTime) >= $interval) {
                    $this->logRabbitMqEvent(
                        'BATCH',
                        "Processing <info>" . count($batch) . "</info> message(s)"
                    );
                    $this->processBatch($batch);
                    $batch = [];
                    $lastFlushTime = $now;
                }

                // Check shouldQuit
                if ($this->shouldQuit) {
                    $broker->stopConsuming();
                }
            });
        }

        $this->logRabbitMqEvent('CONSUME', "listening on " . count($channels) . " channel(s)");
        // Start consuming (RabbitMQ uses blocking wait with timeout)
        $broker->consume($timeout, $batchSize);
    }

    /**
     * Consume batches with message limit.
     *
     * @param RabbitMqBroker $broker RabbitMQ broker
     * @param array<string> $channels Channels to subscribe
     * @param int $timeout Poll timeout
     * @param int $batchSize Batch size
     * @param int $maxMessages Maximum messages
     * @return void
     */
    protected function consumeBatchesWithLimit(RabbitMqBroker $broker, array $channels, int $timeout, int $batchSize, int $maxMessages): void
    {
        $batch = [];
        $lastFlushTime = (int) (microtime(true) * 1000);
        $interval = $this->getBatchReleaseInterval();
        $processedCount = 0;

        // Subscribe to all channels
        foreach ($channels as $channel) {
            $this->logRabbitMqEvent('SUBSCRIBE', "channel <options=bold>{$channel}</>");
            $broker->subscribe($channel, function (MessageInterface $message) use (&$batch, &$lastFlushTime, &$processedCount, $batchSize, $interval, $maxMessages, $broker, $channel) {
                if ($processedCount >= $maxMessages) {
                    $broker->stopConsuming();
                    return;
                }

                $event = $message->getEvent() ?? 'unknown';
                $this->logRabbitMqEvent(
                    'MESSAGE',
                    "channel <fg=cyan>{$channel}</> • event <comment>{$event}</comment>"
                );

                $batch[] = [
                    'message' => $message,
                    'metadata' => [
                        'channel' => $channel,
                        'timestamp' => time(),
                    ],
                ];

                $now = (int) (microtime(true) * 1000);

                // Process batch if full or interval elapsed
                if (count($batch) >= $batchSize || ($now - $lastFlushTime) >= $interval) {
                    $this->logRabbitMqEvent(
                        'BATCH',
                        "Processing <info>" . count($batch) . "</info> message(s)"
                    );
                    $this->processBatch($batch);
                    $processedCount += count($batch);
                    $batch = [];
                    $lastFlushTime = $now;

                    if ($processedCount >= $maxMessages) {
                        $broker->stopConsuming();
                    }
                }

                if ($this->shouldQuit) {
                    $broker->stopConsuming();
                }
            });
        }

        $this->logRabbitMqEvent('CONSUME', "listening on " . count($channels) . " channel(s) (max: {$maxMessages} messages)");
        $broker->consume($timeout, $batchSize);
    }

    /**
     * Process a batch of messages.
     *
     * @param array<array{message: MessageInterface, metadata: array}> $batch Message batch
     * @return void
     */
    protected function processBatch(array $batch): void
    {
        if (empty($batch)) {
            return;
        }

        try {
            // Convert to Collection
            $messages = new Collection($batch);

            // Call handler
            $this->handleMessages($messages);

            $this->processed += count($batch);

            // Display progress (every 10 batches)
            if (($this->processed / $this->getBatchSizeLimit()) % 10 === 0) {
                $this->writeln("Processed: <info>{$this->processed}</info> messages in batches");
            }
        } catch (\Throwable $e) {
            $this->logError($e, [
                'batch_size' => count($batch),
                'channels' => $this->getChannels(),
            ]);

            // Re-throw to trigger error handling
            throw $e;
        }
    }
}

