<?php

declare(strict_types=1);

namespace Toporia\Framework\Providers;

use Toporia\Framework\Container\ContainerInterface;
use Toporia\Framework\Foundation\ServiceProvider;
use Toporia\Framework\Schedule\Scheduler;
use Toporia\Framework\Schedule\MutexInterface;
use Toporia\Framework\Schedule\CacheMutex;

/**
 * Schedule Service Provider
 *
 * Registers the task scheduler service.
 */
final class ScheduleServiceProvider extends ServiceProvider
{
    public function register(ContainerInterface $container): void
    {
        // Register mutex
        $container->singleton(MutexInterface::class, function ($c) {
            return new CacheMutex($c->get('cache'));
        });

        // Register scheduler
        $container->singleton(Scheduler::class, function ($c) {
            $scheduler = new Scheduler();
            $scheduler->setContainer($c);
            $scheduler->setMutex($c->get(MutexInterface::class));
            return $scheduler;
        });
        $container->bind('schedule', fn($c) => $c->get(Scheduler::class));
    }

    public function boot(ContainerInterface $container): void
    {
        // Scheduler is now ready with container injected
        // Scheduled tasks are defined in App\Providers\ScheduleServiceProvider
    }
}
