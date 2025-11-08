<?php

declare(strict_types=1);

namespace App\Presentation\Console;

use Toporia\Framework\Console\Application;
use Toporia\Framework\Console\Command;
use App\Application\Console\Commands\CacheClearCommand;
use App\Application\Console\Commands\QueueWorkCommand;
use App\Application\Console\Commands\ScheduleRunCommand;
use App\Application\Console\Commands\ScheduleListCommand;

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
            // Cache commands
            CacheClearCommand::class,

            // Queue commands
            QueueWorkCommand::class,

            // Schedule commands
            ScheduleRunCommand::class,
            ScheduleListCommand::class,

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
