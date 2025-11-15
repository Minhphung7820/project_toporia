<?php

declare(strict_types=1);

namespace App\Presentation\Console;

use Toporia\Framework\Console\Application;
use Toporia\Framework\Console\Command;
use Toporia\Framework\Console\CommandRegistry;

/**
 * Console Kernel
 *
 * APPLICATION-LEVEL command registration.
 * Framework commands are auto-registered in ConsoleServiceProvider.
 *
 * Only register YOUR APPLICATION-SPECIFIC commands here.
 *
 * This follows Clean Architecture:
 * - Framework layer manages framework commands
 * - Application layer manages application commands
 */
final class Kernel
{
    /**
     * Return APPLICATION-SPECIFIC command classes to be registered.
     *
     * Framework commands (migrate, cache:clear, queue:work, etc.)
     * are automatically registered by the framework.
     *
     * Only add YOUR CUSTOM application commands here.
     *
     * @return array<int, class-string<Command>>
     */
    public function commands(): array
    {
        return [
            // Test commands
            // Business logic consumers
            \App\Console\Commands\OrderTrackingConsumerCommand::class,

            // Kafka topic management
            \App\Console\Commands\KafkaTopicManagerCommand::class,

            // Add your custom application commands here...
            // Example:
            // \App\Console\Commands\SendNewsletterCommand::class,
            // \App\Console\Commands\GenerateReportCommand::class,
        ];
    }

    /**
     * Bootstrap the console application by registering commands.
     *
     * @param Application $app Console application
     * @param CommandRegistry $registry Command registry with framework commands
     * @return void
     */
    public function bootstrap(Application $app, CommandRegistry $registry): void
    {
        // Register application-specific commands
        $registry->registerMany($this->commands());

        // Register all commands (framework + application) to console app
        foreach ($registry->all() as $commandClass) {
            $app->register($commandClass);
        }
    }
}
