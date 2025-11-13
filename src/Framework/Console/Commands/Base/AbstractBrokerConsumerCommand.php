<?php

declare(strict_types=1);

namespace Toporia\Framework\Console\Commands\Base;

use Toporia\Framework\Console\Command;
use Toporia\Framework\Realtime\Contracts\BrokerInterface;
use Toporia\Framework\Realtime\Contracts\RealtimeManagerInterface;

/**
 * Abstract Broker Consumer Command
 *
 * Base class for all broker consumer commands (Redis, Kafka, RabbitMQ, etc.).
 * Provides common functionality for consuming messages from brokers.
 *
 * Architecture:
 * - All realtime brokers MUST have CLI consumer commands
 * - Consumers run as long-lived processes (daemons)
 * - Consumers receive messages from broker and broadcast locally
 *
 * SOLID Principles:
 * - Single Responsibility: Base consumer functionality only
 * - Open/Closed: Extensible via abstract methods
 * - Dependency Inversion: Depends on BrokerInterface
 *
 * @package Toporia\Framework\Console\Commands\Base
 */
abstract class AbstractBrokerConsumerCommand extends Command
{
    /**
     * @param RealtimeManagerInterface $realtime Realtime manager instance
     */
    public function __construct(
        protected readonly RealtimeManagerInterface $realtime
    ) {
        // Note: Command class doesn't have a constructor, so no parent::__construct() call needed
    }

    /**
     * Get broker instance.
     *
     * @param string|null $brokerName Broker name (default: from option)
     * @return BrokerInterface
     * @throws \RuntimeException If broker not found
     */
    protected function getBroker(?string $brokerName = null): BrokerInterface
    {
        $brokerName = $brokerName ?? $this->getBrokerName();
        $broker = $this->realtime->broker($brokerName);

        if (!$broker) {
            throw new \RuntimeException(
                "Broker '{$brokerName}' not found. " .
                    "Configure it in config/realtime.php"
            );
        }

        if (!$broker instanceof BrokerInterface) {
            throw new \RuntimeException("Broker '{$brokerName}' is not a valid broker");
        }

        return $broker;
    }

    /**
     * Get broker name from option or config.
     *
     * @return string
     */
    protected function getBrokerName(): string
    {
        return $this->option('broker', $this->getDefaultBrokerName());
    }

    /**
     * Get default broker name.
     *
     * @return string
     */
    abstract protected function getDefaultBrokerName(): string;

    /**
     * Display header information.
     *
     * @param string $title Command title
     * @param array<string, mixed> $info Additional information
     * @return void
     */
    protected function displayHeader(string $title, array $info = []): void
    {
        $this->info("╔════════════════════════════════════════════════════════════╗");
        $this->info("║ " . str_pad($title, 60) . " ║");
        $this->info("╠════════════════════════════════════════════════════════════╣");

        foreach ($info as $key => $value) {
            $key = ucfirst(str_replace('_', ' ', $key));
            $this->info("║ " . str_pad("{$key}: {$value}", 60) . " ║");
        }

        $this->info("╚════════════════════════════════════════════════════════════╝");
        $this->newLine();
    }

    /**
     * Display summary statistics.
     *
     * @return void
     */
    protected function displaySummary(): void
    {
        $this->newLine();
        $this->info('Consumer stopped.');
    }

    /**
     * Setup signal handlers for graceful shutdown.
     *
     * @param callable $shutdownCallback Callback to execute on shutdown
     * @return void
     */
    /**
     * Setup signal handlers for graceful shutdown.
     *
     * Performance:
     * - Signal handlers are registered once
     * - Minimal overhead during normal operation
     * - Fast response to shutdown signals
     *
     * Architecture:
     * - Uses PCNTL extension for signal handling
     * - Supports SIGTERM (termination) and SIGINT (Ctrl+C)
     * - Calls shutdown callback before exit
     * - Uses async signals for non-blocking signal handling
     *
     * @param callable $shutdownCallback Callback to execute on shutdown
     * @return void
     */
    protected function setupSignalHandlers(callable $shutdownCallback): void
    {
        if (!function_exists('pcntl_signal')) {
            $this->warn('PCNTL extension not available. Graceful shutdown may not work.');
            $this->warn('Install php-pcntl extension for proper signal handling.');
            return;
        }

        // Enable async signal handling (non-blocking)
        // This allows signals to be handled even during blocking operations
        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
        }

        // Handle SIGTERM (termination signal)
        pcntl_signal(SIGTERM, function () use ($shutdownCallback) {
            $this->info("\nReceived SIGTERM. Shutting down gracefully...");
            $shutdownCallback();
            exit(0);
        });

        // Handle SIGINT (Ctrl+C)
        pcntl_signal(SIGINT, function () use ($shutdownCallback) {
            $this->info("\nReceived SIGINT. Shutting down gracefully...");
            $shutdownCallback();
            exit(0);
        });
    }

    /**
     * Log error with context.
     *
     * @param \Throwable $error Error exception
     * @param array<string, mixed> $context Additional context
     * @return void
     */
    protected function logError(\Throwable $error, array $context = []): void
    {
        $message = "Error: {$error->getMessage()}";
        if (!empty($context)) {
            $message .= ' | Context: ' . json_encode($context);
        }

        $this->error($message);

        if ($this->hasOption('verbose')) {
            $this->line($error->getTraceAsString());
        }
    }
}
