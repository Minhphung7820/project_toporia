<?php

declare(strict_types=1);

namespace Toporia\Framework\Providers;

use Toporia\Framework\Container\ContainerInterface;
use Toporia\Framework\Foundation\ServiceProvider;
use Toporia\Framework\Schedule\Scheduler;

/**
 * Schedule Service Provider
 *
 * Registers the task scheduler service.
 */
final class ScheduleServiceProvider extends ServiceProvider
{
    public function register(ContainerInterface $container): void
    {
        $container->singleton(Scheduler::class, fn() => new Scheduler());
        $container->bind('schedule', fn($c) => $c->get(Scheduler::class));
    }

    public function boot(ContainerInterface $container): void
    {
        // This is where you would define scheduled tasks
        // For example, load from routes/schedule.php or app/schedule.php
    }
}
