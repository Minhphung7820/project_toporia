<?php

declare(strict_types=1);

namespace Toporia\Framework\Console\Commands\Kafka\Base;

use Toporia\Framework\Console\Command;
use Toporia\Framework\Realtime\Contracts\RealtimeManagerInterface;
use Toporia\Framework\Realtime\Brokers\KafkaBroker;

/**
 * Abstract Kafka Consumer Base Class
 *
 * Base class for all Kafka consumers with common functionality.
 * Provides configuration, broker access, and common utilities.
 *
 * SOLID Principles:
 * - Single Responsibility: Manages Kafka consumer configuration and setup
 * - Open/Closed: Extensible via inheritance
 * - Dependency Inversion: Depends on RealtimeManagerInterface
 *
 * @package Toporia\Framework\Console\Commands\Kafka\Base
 */
abstract class AbstractKafkaConsumer extends Command
{
    /**
     * @var bool Whether consumer should stop
     */
    protected bool $shouldQuit = false;

    /**
     * @var int Number of messages processed
     */
    protected int $processed = 0;

    /**
     * @var int Number of errors encountered
     */
    protected int $errors = 0;

    /**
     * @var float Start time for performance tracking
     */
    protected float $startTime;

    /**
     * @param RealtimeManagerInterface $realtime Realtime manager instance
     */
    public function __construct(
        protected readonly RealtimeManagerInterface $realtime
    ) {
        $this->startTime = microtime(true);
    }

    /**
     * Get Kafka broker instance.
     *
     * @param string|null $brokerName Broker name (default: 'kafka')
     * @return KafkaBroker
     * @throws \RuntimeException If broker not found or not a Kafka broker
     */
    protected function getBroker(?string $brokerName = null): KafkaBroker
    {
        $brokerName = $brokerName ?? $this->getBrokerName();
        $broker = $this->realtime->broker($brokerName);

        if (!$broker) {
            throw new \RuntimeException(
                "Kafka broker '{$brokerName}' not found in configuration. " .
                    "Make sure 'default_broker' is set to 'kafka' in config/realtime.php"
            );
        }

        if (!$broker instanceof KafkaBroker) {
            throw new \RuntimeException("Broker '{$brokerName}' is not a Kafka broker");
        }

        return $broker;
    }

    /**
     * Get broker name from config or option.
     *
     * @return string
     */
    protected function getBrokerName(): string
    {
        return $this->option('broker', 'kafka');
    }

    /**
     * Get Kafka brokers list from configuration.
     *
     * @return array<string>
     */
    protected function getBrokers(): array
    {
        $broker = $this->getBroker();
        // Access brokers through reflection or add getter to KafkaBroker
        // For now, get from config
        $config = config('realtime.brokers.kafka', []);
        $brokers = $config['brokers'] ?? ['localhost:9092'];
        return is_array($brokers) ? $brokers : [$brokers];
    }

    /**
     * Get topic name.
     *
     * @return string
     */
    abstract protected function getTopic(): string;

    /**
     * Get consumer group ID.
     *
     * @return string
     */
    abstract protected function getGroupId(): string;

    /**
     * Get offset reset strategy.
     *
     * Options: 'earliest', 'latest', 'none'
     *
     * @return string
     */
    abstract protected function getOffset(): string;

    /**
     * Setup signal handlers for graceful shutdown.
     *
     * @param callable $shutdownCallback Callback to execute on shutdown
     * @return void
     */
    protected function setupSignalHandlers(callable $shutdownCallback): void
    {
        if (!function_exists('pcntl_signal')) {
            return; // pcntl not available
        }

        // Handle SIGTERM (graceful shutdown)
        pcntl_signal(SIGTERM, function () use ($shutdownCallback) {
            $this->shouldQuit = true;
            $this->warn("\nReceived SIGTERM, shutting down gracefully...");
            $shutdownCallback();
        });

        // Handle SIGINT (Ctrl+C)
        pcntl_signal(SIGINT, function () use ($shutdownCallback) {
            $this->shouldQuit = true;
            $this->warn("\nReceived SIGINT, shutting down gracefully...");
            $shutdownCallback();
        });

        // Enable async signal handling
        pcntl_async_signals(true);
    }

    /**
     * Display command header.
     *
     * @param string $consumerType Consumer type description
     * @param array<string, mixed> $options Additional options to display
     * @return void
     */
    protected function displayHeader(string $consumerType, array $options = []): void
    {
        // Prevent multiple calls (static flag)
        static $displayed = false;
        if ($displayed) {
            return;
        }
        $displayed = true;

        $this->newLine();
        $this->info("Kafka Consumer: {$consumerType}");
        $this->line('=', 60); // Line separator
        $this->writeln("Topic: <info>{$this->getTopic()}</info>");
        $this->writeln("Group ID: <info>{$this->getGroupId()}</info>");
        $this->writeln("Offset: <info>{$this->getOffset()}</info>");

        foreach ($options as $key => $value) {
            $this->writeln(ucfirst($key) . ": <info>{$value}</info>");
        }

        $this->line('-', 60); // Line separator
        $this->info('Consumer started. Press Ctrl+C to stop.');
        $this->newLine();
    }

    /**
     * Display summary statistics.
     *
     * @return void
     */
    protected function displaySummary(): void
    {
        $duration = microtime(true) - $this->startTime;
        $rate = $duration > 0 ? round($this->processed / $duration, 2) : 0;

        $this->newLine();
        $this->line(str_repeat('=', 60));
        $this->info('Consumer Summary');
        $this->line(str_repeat('-', 60));
        $this->line("Messages Processed: <info>{$this->processed}</info>");
        $this->line("Errors: <info>{$this->errors}</info>");
        $this->line("Duration: <info>" . round($duration, 2) . "s</info>");
        $this->line("Rate: <info>{$rate} msg/s</info>");
        $this->line(str_repeat('=', 60));
    }

    /**
     * Log error with context.
     *
     * @param \Throwable $exception Exception
     * @param array<string, mixed> $context Additional context
     * @return void
     */
    protected function logError(\Throwable $exception, array $context = []): void
    {
        $this->errors++;
        $message = "Error processing Kafka message: {$exception->getMessage()}";

        error_log($message);
        if (function_exists('logger')) {
            logger()->error($message, array_merge($context, [
                'exception' => $exception,
                'trace' => $exception->getTraceAsString(),
            ]));
        }
    }

    /**
     * Render a styled Kafka log line similar to queue worker output.
     *
     * @param string $label   Short label (eg. MESSAGE, BATCH)
     * @param string $message Message body (can contain ANSI tags)
     * @param string $style   info|success|warn|error|debug
     * @return void
     */
    protected function logKafkaEvent(string $label, string $message, string $style = 'info'): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $label = strtoupper($label);

        $styleMap = [
            'info' => 'fg=cyan;options=bold',
            'success' => 'fg=green;options=bold',
            'warn' => 'fg=yellow;options=bold',
            'error' => 'fg=red;options=bold',
            'debug' => 'fg=magenta;options=bold',
        ];

        $labelStyle = $styleMap[$style] ?? $styleMap['info'];
        $formattedLabel = sprintf('<%s>%s</>', $labelStyle, str_pad($label, 9));
        $line = sprintf('[%s] %s  %s', $timestamp, $formattedLabel, $message);

        match ($style) {
            'success' => $this->info($line),
            'warn' => $this->warn($line),
            'error' => $this->error($line),
            default => $this->writeln($line),
        };
    }
}
