<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Toporia\Framework\Console\Commands\Base\AbstractBrokerConsumerCommand;
use Toporia\Framework\Realtime\Brokers\KafkaBroker;
use Toporia\Framework\Realtime\Contracts\MessageInterface;
use Toporia\Framework\Realtime\Contracts\RealtimeManagerInterface;

/**
 * Test Kafka Consumer Command
 *
 * Simple test consumer to receive and log Kafka messages.
 * Used for testing Kafka broker functionality.
 *
 * Usage:
 *   php console test:kafka:consume
 *   php console test:kafka:consume --channels=test.channel
 *
 * @package App\Console\Commands
 */
final class TestKafkaConsumerCommand extends AbstractBrokerConsumerCommand
{
    protected string $signature = 'test:kafka:consume {--broker=kafka} {--channels=test.channel}';

    protected string $description = 'Test Kafka consumer - receives and logs messages';

    /**
     * @var array<string> Channels to subscribe to
     */
    private array $channels = [];

    /**
     * @var int Processed message count
     */
    private int $processed = 0;

    /**
     * {@inheritdoc}
     */
    protected function getDefaultBrokerName(): string
    {
        return 'kafka';
    }

    /**
     * {@inheritdoc}
     */
    public function handle(): int
    {
        try {
            // Parse channels
            $channelsOption = $this->option('channels', 'test.channel');
            $this->channels = $this->parseChannels($channelsOption);

            // Get broker
            $broker = $this->getBroker();

            if (!$broker instanceof KafkaBroker) {
                $this->error("Broker is not a Kafka broker. Got: " . get_class($broker));
                return 1;
            }

            // Display header
            $this->displayHeader('Test Kafka Consumer', [
                'broker' => $this->getBrokerName(),
                'channels' => implode(', ', $this->channels),
            ]);

            // Setup graceful shutdown
            $this->setupSignalHandlers(function () use ($broker) {
                $this->info("\nStopping consumer...");
                $broker->stopConsuming();
            });

            // Subscribe to all channels
            foreach ($this->channels as $channel) {
                $broker->subscribe($channel, function (MessageInterface $message) use ($channel) {
                    $this->handleMessage($channel, $message);
                });
                $this->line("Subscribed to channel: <info>{$channel}</info>");
            }

            $this->info('Starting consumer... Press Ctrl+C to stop.');
            $this->newLine();

            // Start consuming (Kafka uses batch processing via AbstractBatchKafkaConsumer)
            // For simple test, we'll use the broker's consume method directly
            // Note: This is a simplified version - in production use RealtimeKafkaConsumerCommand
            try {
                $broker->consume(1000, 100);
            } catch (\Throwable $e) {
                $this->error("Error consuming: {$e->getMessage()}");
                return 1;
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
     * Handle incoming message.
     *
     * @param string $channel Channel name
     * @param MessageInterface $message Message
     * @return void
     */
    private function handleMessage(string $channel, MessageInterface $message): void
    {
        $this->processed++;

        // Log message details
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("📨 Message #{$this->processed} received");
        $this->line("   Channel: <comment>{$channel}</comment>");
        $this->line("   Event: <comment>{$message->getEvent()}</comment>");
        $this->line("   Message ID: <comment>{$message->getId()}</comment>");

        $data = $message->getData();
        if ($data !== null) {
            $this->line("   Data: <info>" . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</info>");
        }

        $this->line("   Timestamp: <comment>" . date('Y-m-d H:i:s') . "</comment>");
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->newLine();
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

    /**
     * {@inheritdoc}
     */
    protected function displaySummary(): void
    {
        $this->newLine();
        $this->info("╔════════════════════════════════════════════════════════════╗");
        $this->info("║ " . str_pad('Test Consumer Summary', 60) . " ║");
        $this->info("╠════════════════════════════════════════════════════════════╣");
        $this->info("║ " . str_pad("Total messages received: {$this->processed}", 60) . " ║");
        $this->info("╚════════════════════════════════════════════════════════════╝");
    }
}

