<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Toporia\Framework\Console\Commands\Kafka\Base\AbstractBatchKafkaConsumer;
use Toporia\Framework\Console\Commands\Kafka\Contracts\BatchingMessagesHandlerInterface;
use Toporia\Framework\Console\Commands\Kafka\DeadLetterQueue\DeadLetterQueueHandler;
use Toporia\Framework\Realtime\Contracts\RealtimeManagerInterface;
use Toporia\Framework\Support\Accessors\Log;
use Toporia\Framework\Support\Collection;

/**
 * Order Tracking Consumer Command
 *
 * Consume order events from Kafka and process order tracking logic.
 * This is a BUSINESS LOGIC consumer, separate from realtime system.
 *
 * Architecture:
 * - Consumes from Kafka topic: 'orders.events'
 * - Processes order events (created, updated, shipped, delivered, etc.)
 * - Updates order tracking database
 * - Sends notifications
 * - Generates analytics
 *
 * Usage:
 *   php console order:tracking:consume
 *   php console order:tracking:consume --batch-size=50
 *   php console order:tracking:consume --dlq-enabled
 *
 * Performance:
 * - Batch processing: 50-100 messages per batch
 * - High throughput: 1000+ orders/sec
 * - DLQ support for failed messages
 *
 * SOLID Principles:
 * - Single Responsibility: Only handles order tracking
 * - Open/Closed: Extensible via configuration
 * - Dependency Inversion: Depends on abstractions
 *
 * @package App\Console\Commands
 */
final class OrderTrackingConsumerCommand extends AbstractBatchKafkaConsumer implements BatchingMessagesHandlerInterface
{
    protected string $signature = 'order:tracking:consume {--batch-size=50} {--timeout=1000} {--max-messages=0} {--dlq-enabled}';

    protected string $description = 'Consume order events from Kafka and process order tracking';

    /**
     * @var DeadLetterQueueHandler|null DLQ handler
     */
    private ?DeadLetterQueueHandler $dlqHandler = null;

    /**
     * {@inheritdoc}
     */
    protected function getTopic(): string
    {
        // Business logic topic (not realtime)
        return config('kafka.topics.orders', 'orders.events');
    }

    /**
     * {@inheritdoc}
     */
    protected function getGroupId(): string
    {
        // Separate consumer group for order tracking
        return config('kafka.consumer_groups.order_tracking', 'order-tracking-consumers');
    }

    /**
     * {@inheritdoc}
     */
    protected function getOffset(): string
    {
        // Start from earliest to process all order events
        return config('kafka.offset_reset', 'earliest');
    }

    /**
     * {@inheritdoc}
     */
    protected function getBatchSizeLimit(): int
    {
        return (int) $this->option('batch-size', 50);
    }

    /**
     * {@inheritdoc}
     */
    protected function getBatchReleaseInterval(): int
    {
        // Process batch every 2 seconds (even if not full)
        return (int) config('kafka.batch_release_interval', 2000);
    }

    /**
     * {@inheritdoc}
     */
    public function handle(): int
    {
        try {
            // Initialize DLQ if enabled
            if ($this->hasOption('dlq-enabled')) {
                $this->dlqHandler = new DeadLetterQueueHandler(
                    dlqTopicPrefix: config('kafka.dlq_topic_prefix', 'dlq'),
                    maxRetries: config('kafka.dlq_max_retries', 3)
                );
            }

            // Override parent's displayHeader with custom one
            // Parent class will call displayHeader, but we override it here
            $broker = $this->getBroker();
            $topic = $this->getTopic();
            $batchSize = $this->getBatchSizeLimit();
            $interval = $this->getBatchReleaseInterval();

            // Validate
            if (empty($topic)) {
                $this->error('Topic is required. Override getTopic() method.');
                return 1;
            }

            if ($batchSize <= 0) {
                $this->error('Batch size must be greater than 0.');
                return 1;
            }

            // Display custom header (only once)
            $this->displayHeader('Order Tracking Consumer', [
                'broker' => $this->getBrokerName(),
                'topic' => $topic,
                'group_id' => $this->getGroupId(),
                'batch_size' => $batchSize,
                'interval' => $interval . 'ms',
                'dlq' => $this->dlqHandler ? 'enabled' : 'disabled',
            ]);

            // Setup graceful shutdown
            $this->setupSignalHandlers(function () use ($broker) {
                $broker->stopConsuming();
            });

            // Start batch consuming
            $timeout = (int) $this->option('timeout', 1000);
            $maxMessages = (int) $this->option('max-messages', 0);

            if ($maxMessages > 0) {
                $this->consumeBatchesWithLimit($broker, $timeout, $batchSize, $maxMessages);
            } else {
                $this->consumeBatches($broker, $timeout, $batchSize);
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
     * {@inheritdoc}
     *
     * Process batch of order events.
     * This is where your business logic goes.
     */
    public function handleMessages(Collection $messages): void
    {
        $count = $messages->count();
        $this->writeln("OrderTrackingConsumer: Processing batch of <info>{$count}</info> order events");
        error_log("OrderTrackingConsumer: Processing batch of {$count} messages");

        foreach ($messages as $item) {
            try {
                $message = $item['message'] ?? null;
                $metadata = $item['metadata'] ?? [];

                if (!$message) {
                    $this->warn("  ⚠ Skipping message: message is null");
                    error_log("OrderTrackingConsumer: Skipping message - message is null");
                    continue;
                }

                // Extract order data from message
                $orderData = $this->extractOrderData($message);

                // Log extracted data
                $this->writeln("  Processing order: <info>" . ($orderData['order_id'] ?? 'unknown') . "</info>");
                error_log("OrderTrackingConsumer: Extracted order data: " . json_encode($orderData));

                // Process order event based on event type
                $this->processOrderEvent($orderData, $metadata);

                $this->processed++;
            } catch (\Throwable $e) {
                // Handle error with DLQ if enabled
                if ($this->dlqHandler && isset($message)) {
                    $shouldRetry = $this->dlqHandler->handleFailedMessage(
                        $message,
                        $e,
                        $metadata,
                        function (string $dlqTopic, string $payload) {
                            // Publish to DLQ topic
                            error_log("DLQ: Would publish to {$dlqTopic}: {$payload}");
                            // TODO: Implement DLQ publishing
                        }
                    );

                    if ($shouldRetry) {
                        error_log("Order event will be retried: {$e->getMessage()}");
                    }
                } else {
                    $this->error("Error processing order event: {$e->getMessage()}");
                    if ($this->hasOption('verbose')) {
                        $this->line($e->getTraceAsString());
                    }
                    $this->errors++;
                }
            }
        }

        $this->line("Batch processed: {$this->processed} orders, {$this->errors} errors");
    }

    /**
     * Extract order data from message.
     *
     * @param mixed $message Message object (MessageInterface)
     * @return array<string, mixed> Order data
     */
    private function extractOrderData(mixed $message): array
    {
        // Message is MessageInterface from Kafka
        // Format: Message with event='order.created' and data={order_id, user_id, ...}

        if ($message instanceof \Toporia\Framework\Realtime\Contracts\MessageInterface) {
            $data = $message->getData();

            // If data is already an array with order info, return it
            if (is_array($data)) {
                // Add event from message if not in data
                if (!isset($data['event']) && $message->getEvent()) {
                    $data['event'] = $message->getEvent();
                }
                return $data;
            }
        }

        // Fallback: try to decode as JSON string
        if (is_string($message)) {
            $decoded = json_decode($message, true);
            return is_array($decoded) ? $decoded : [];
        }

        // Fallback: if it's already an array
        if (is_array($message)) {
            return $message;
        }

        return [];
    }

    /**
     * Process order event based on event type.
     *
     * @param array<string, mixed> $orderData Order data
     * @param array<string, mixed> $metadata Message metadata
     * @return void
     */
    private function processOrderEvent(array $orderData, array $metadata): void
    {
        $event = $orderData['event'] ?? 'unknown';
        $orderId = $orderData['order_id'] ?? null;

        if (!$orderId) {
            throw new \InvalidArgumentException('Order ID is required');
        }

        // Route to appropriate handler based on event type
        match ($event) {
            'order.created' => $this->handleOrderCreated($orderData),
            'order.updated' => $this->handleOrderUpdated($orderData),
            'order.shipped' => $this->handleOrderShipped($orderData),
            'order.delivered' => $this->handleOrderDelivered($orderData),
            'order.cancelled' => $this->handleOrderCancelled($orderData),
            default => $this->handleUnknownEvent($orderData, $event),
        };
    }

    /**
     * Handle order created event.
     *
     * @param array<string, mixed> $orderData
     * @return void
     */
    private function handleOrderCreated(array $orderData): void
    {
        $orderId = $orderData['order_id'];

        // Your business logic here
        // Example:
        // - Create order tracking record in database
        // - Send confirmation email
        // - Update inventory
        // - Generate analytics
        Log::info("Order created: {$orderId}");
        $this->line("  ✓ Order created: {$orderId}");

        // TODO: Implement your business logic
        // Example:
        // OrderTracking::create([
        //     'order_id' => $orderId,
        //     'status' => 'created',
        //     'timestamp' => now(),
        // ]);
    }

    /**
     * Handle order updated event.
     *
     * @param array<string, mixed> $orderData
     * @return void
     */
    private function handleOrderUpdated(array $orderData): void
    {
        $orderId = $orderData['order_id'];
        $changes = $orderData['changes'] ?? [];

        $this->line("  ✓ Order updated: {$orderId}");

        // TODO: Implement your business logic
        // Example:
        // OrderTracking::updateStatus($orderId, $orderData['status']);
    }

    /**
     * Handle order shipped event.
     *
     * @param array<string, mixed> $orderData
     * @return void
     */
    private function handleOrderShipped(array $orderData): void
    {
        $orderId = $orderData['order_id'];
        $trackingNumber = $orderData['tracking_number'] ?? null;

        $this->line("  ✓ Order shipped: {$orderId}, Tracking: {$trackingNumber}");

        // TODO: Implement your business logic
        // Example:
        // OrderTracking::updateStatus($orderId, 'shipped', [
        //     'tracking_number' => $trackingNumber,
        //     'shipped_at' => now(),
        // ]);
        // NotificationService::sendShippingNotification($orderId);
    }

    /**
     * Handle order delivered event.
     *
     * @param array<string, mixed> $orderData
     * @return void
     */
    private function handleOrderDelivered(array $orderData): void
    {
        $orderId = $orderData['order_id'];

        $this->line("  ✓ Order delivered: {$orderId}");

        // TODO: Implement your business logic
        // Example:
        // OrderTracking::updateStatus($orderId, 'delivered', [
        //     'delivered_at' => now(),
        // ]);
        // NotificationService::sendDeliveryNotification($orderId);
        // AnalyticsService::recordDelivery($orderId);
    }

    /**
     * Handle order cancelled event.
     *
     * @param array<string, mixed> $orderData
     * @return void
     */
    private function handleOrderCancelled(array $orderData): void
    {
        $orderId = $orderData['order_id'];
        $reason = $orderData['reason'] ?? 'unknown';

        $this->line("  ✓ Order cancelled: {$orderId}, Reason: {$reason}");

        // TODO: Implement your business logic
        // Example:
        // OrderTracking::updateStatus($orderId, 'cancelled', [
        //     'cancelled_at' => now(),
        //     'reason' => $reason,
        // ]);
        // InventoryService::restoreItems($orderId);
    }

    /**
     * Handle unknown event type.
     *
     * @param array<string, mixed> $orderData
     * @param string $event
     * @return void
     */
    private function handleUnknownEvent(array $orderData, string $event): void
    {
        $orderId = $orderData['order_id'] ?? 'unknown';
        $this->warn("  ⚠ Unknown event type '{$event}' for order {$orderId}");

        // Log for investigation
        error_log("Unknown order event: {$event} for order {$orderId}");
    }
}
