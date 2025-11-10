<?php

declare(strict_types=1);

namespace App\Presentation\Console;

use Toporia\Framework\Console\Application;
use Toporia\Framework\Console\Command;
use Toporia\Framework\Console\Commands\MigrateCommand;
use Toporia\Framework\Console\Commands\MigrateRollbackCommand;
use Toporia\Framework\Console\Commands\MigrateStatusCommand;
use Toporia\Framework\Console\Commands\CacheClearCommand;
use Toporia\Framework\Console\Commands\QueueWorkCommand;
use Toporia\Framework\Console\Commands\ScheduleRunCommand;
use Toporia\Framework\Console\Commands\ScheduleWorkCommand;
use Toporia\Framework\Console\Commands\ScheduleListCommand;
use Toporia\Framework\Console\Commands\RealtimeServeCommand;

/**
 * Console Kernel
 *
 * Central place to register CLI commands.
 * Keeps entry files thin and enables reuse and testability.
 *
 * This follows the Service Provider pattern for console commands.
 */
final class Kernel
{
    /**
     * Return the list of command classes to be registered.
     *
     * Add your custom commands here.
     *
     * @return array<int, class-string<Command>>
     */
    public function commands(): array
    {
        return [
            // Database commands
            MigrateCommand::class,
            MigrateRollbackCommand::class,
            MigrateStatusCommand::class,

            // Cache commands
            CacheClearCommand::class,

            // Queue commands
            QueueWorkCommand::class,

            // Schedule commands
            ScheduleRunCommand::class,
            ScheduleWorkCommand::class,
            ScheduleListCommand::class,

            // Realtime commands
            RealtimeServeCommand::class,

            // Add your custom commands here...
        ];
    }

    /**
     * Bootstrap the console application by registering commands.
     *
     * @param Application $app
     * @return void
     */
    public function bootstrap(Application $app): void
    {
        foreach ($this->commands() as $commandClass) {
            $app->register($commandClass);
        }
    }
}
