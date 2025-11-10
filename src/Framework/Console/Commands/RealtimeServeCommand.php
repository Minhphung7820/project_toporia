<?php

declare(strict_types=1);

namespace Toporia\Framework\Console\Commands;

use Toporia\Framework\Console\Command;
use Toporia\Framework\Realtime\Contracts\RealtimeManagerInterface;

/**
 * Realtime Server Command
 *
 * Start the realtime server with configured transport.
 *
 * Usage:
 * - php console realtime:serve                    # Start with default config
 * - php console realtime:serve --transport=websocket
 * - php console realtime:serve --host=0.0.0.0 --port=6001
 * - php console realtime:serve --transport=sse
 *
 * @package Toporia\Framework\Console\Commands
 */
final class RealtimeServeCommand extends Command
{
    protected string $signature = 'realtime:serve';
    protected string $description = 'Start the realtime server';

    public function __construct(
        private readonly RealtimeManagerInterface $realtime
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function handle(): int
    {
        $transport = $this->option('transport', config('realtime.default_transport', 'memory'));
        $host = $this->option('host', '0.0.0.0');
        $port = (int) $this->option('port', 6001);

        $this->info("Starting Realtime Server");
        $this->line("Transport: {$transport}");
        $this->line("Host: {$host}");
        $this->line("Port: {$port}");
        $this->line(str_repeat('-', 50));

        // Check transport requirements
        if ($transport === 'websocket') {
            if (!extension_loaded('swoole')) {
                $this->error('Swoole extension is required for WebSocket transport');
                $this->warn('Install: pecl install swoole');
                return 1;
            }

            $this->success('Swoole extension detected');
        }

        try {
            // Get transport instance
            $transportInstance = $this->realtime->transport($transport);

            // Register signal handlers for graceful shutdown
            $this->registerSignalHandlers($transportInstance);

            // Start server
            $this->info("Server starting on {$host}:{$port}...\n");

            $transportInstance->start($host, $port);

            // This line is reached when server stops
            $this->info("\nServer stopped");

            return 0;
        } catch (\Throwable $e) {
            $this->error("Failed to start server: {$e->getMessage()}");

            if ($this->hasOption('verbose')) {
                $this->line($e->getTraceAsString());
            }

            return 1;
        }
    }

    /**
     * Register signal handlers for graceful shutdown.
     *
     * @param mixed $transport Transport instance
     * @return void
     */
    private function registerSignalHandlers($transport): void
    {
        if (!function_exists('pcntl_signal')) {
            return; // pcntl not available
        }

        // Handle SIGINT (Ctrl+C)
        pcntl_signal(SIGINT, function () use ($transport) {
            $this->warn("\nReceived shutdown signal, stopping server...");
            $transport->stop();
            exit(0);
        });

        // Handle SIGTERM
        pcntl_signal(SIGTERM, function () use ($transport) {
            $this->warn("\nReceived termination signal, stopping server...");
            $transport->stop();
            exit(0);
        });
    }
}
