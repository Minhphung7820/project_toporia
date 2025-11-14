<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Toporia\Framework\Console\Commands\Kafka\Contracts\BatchingMessagesHandlerInterface;
use Toporia\Framework\Console\Commands\RabbitMq\Base\AbstractBatchRabbitMqConsumer;
use Toporia\Framework\Realtime\Contracts\MessageInterface;
use Toporia\Framework\Realtime\Contracts\RealtimeManagerInterface;
use Toporia\Framework\Support\Collection;
use Toporia\Framework\Support\Accessors\Log;

/**
 * RabbitMQ Event Consumer Command
 *
 * Example consumer for processing events from RabbitMQ.
 * Demonstrates batch processing with RabbitMQ broker.
 *
 * Usage:
 *   php console rabbitmq:events:consume
 *   php console rabbitmq:events:consume --channels=user.1,user.2 --batch-size=50
 *   php console rabbitmq:events:consume --max-messages=1000
 *
 * @package App\Console\Commands
 */
final class RabbitMqEventConsumerCommand extends AbstractBatchRabbitMqConsumer implements BatchingMessagesHandlerInterface
{
    protected string $signature = 'rabbitmq:events:consume {--channels=*} {--batch-size=100} {--timeout=1000} {--max-messages=0}';

    protected string $description = 'Consume events from RabbitMQ with batch processing';

    /**
     * @var array<string> Channels to subscribe
     */
    private array $channels = [];

    /**
     * @param RealtimeManagerInterface $realtime Realtime manager instance
     */
    public function __construct(RealtimeManagerInterface $realtime)
    {
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

        // Default channels if none specified
        if (empty($this->channels)) {
            $this->channels = ['events.default', 'events.user', 'events.system'];
        }

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
        return 1500; // 1.5 seconds
    }

    /**
     * {@inheritdoc}
     */
    public function handleMessages(Collection $messages): void
    {
        $count = $messages->count();
        $this->writeln(sprintf("----------- %s -----------", date('Y-m-d H:i:s')));
        $this->writeln(sprintf("[RabbitMQEvents] Processing batch of %s event(s)", $count));

        foreach ($messages as $item) {
            try {
                /** @var MessageInterface $message */
                $message = $item['message'] ?? null;
                $metadata = $item['metadata'] ?? [];

                if (!$message) {
                    $this->warn("[RabbitMQEvents] Skipping message: message is null");
                    continue;
                }

                $channel = $metadata['channel'] ?? $message->getChannel() ?? 'unknown';
                $event = $message->getEvent() ?? 'unknown';
                $data = $message->getData() ?? [];

                $this->writeln(sprintf(
                    "[%s] Channel: %s | Event: %s | Data: %s",
                    date('H:i:s'),
                    $channel,
                    $event,
                    json_encode($data, JSON_UNESCAPED_UNICODE)
                ));

                // Process event based on type
                $this->processEvent($channel, $event, $data, $metadata);
                $this->processed++;
            } catch (\Throwable $e) {
                $this->logError($e, [
                    'channel' => $metadata['channel'] ?? 'unknown',
                    'event' => $message->getEvent() ?? 'unknown',
                ]);
                $this->errors++;
            }
        }

        $this->writeln(sprintf("[RabbitMQEvents] Batch processed: %s events, %s errors", $this->processed, $this->errors));
    }

    /**
     * Process individual event.
     *
     * @param string $channel Channel name
     * @param string $event Event type
     * @param array<string, mixed> $data Event data
     * @param array<string, mixed> $metadata Message metadata
     * @return void
     */
    private function processEvent(string $channel, string $event, array $data, array $metadata): void
    {
        // Log to application log
        Log::info("RabbitMQ event received", [
            'channel' => $channel,
            'event' => $event,
            'data' => $data,
            'metadata' => $metadata,
        ]);

        // Example: Handle different event types
        match ($event) {
            'user.created' => $this->handleUserCreated($data),
            'user.updated' => $this->handleUserUpdated($data),
            'order.created' => $this->handleOrderCreated($data),
            default => $this->handleDefaultEvent($channel, $event, $data),
        };
    }

    /**
     * Handle user.created event.
     *
     * @param array<string, mixed> $data Event data
     * @return void
     */
    private function handleUserCreated(array $data): void
    {
        $userId = $data['user_id'] ?? 'unknown';
        Log::info("User created: {$userId}");
        // Add your business logic here
    }

    /**
     * Handle user.updated event.
     *
     * @param array<string, mixed> $data Event data
     * @return void
     */
    private function handleUserUpdated(array $data): void
    {
        $userId = $data['user_id'] ?? 'unknown';
        Log::info("User updated: {$userId}");
        // Add your business logic here
    }

    /**
     * Handle order.created event.
     *
     * @param array<string, mixed> $data Event data
     * @return void
     */
    private function handleOrderCreated(array $data): void
    {
        $orderId = $data['order_id'] ?? 'unknown';
        Log::info("Order created: {$orderId}");
        // Add your business logic here
    }

    /**
     * Handle default event.
     *
     * @param string $channel Channel name
     * @param string $event Event type
     * @param array<string, mixed> $data Event data
     * @return void
     */
    private function handleDefaultEvent(string $channel, string $event, array $data): void
    {
        // Default handler for unknown events
        Log::info("Default event handler", [
            'channel' => $channel,
            'event' => $event,
            'data' => $data,
        ]);
    }

    /**
     * Parse channels from options.
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
