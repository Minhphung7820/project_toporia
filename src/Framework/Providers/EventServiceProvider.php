<?php

declare(strict_types=1);

namespace Framework\Providers;

use Framework\Container\ContainerInterface;
use Framework\Events\Dispatcher;
use Framework\Events\EventDispatcherInterface;
use Framework\Foundation\ServiceProvider;

/**
 * Event Service Provider
 *
 * Registers the event dispatcher as a singleton.
 */
class EventServiceProvider extends ServiceProvider
{
    /**
     * {@inheritdoc}
     */
    public function register(ContainerInterface $container): void
    {
        // Event Dispatcher - Singleton for global event handling
        $container->singleton(EventDispatcherInterface::class, fn() => new Dispatcher());
        $container->singleton(Dispatcher::class, fn() => new Dispatcher());
        $container->singleton('events', fn() => new Dispatcher());
    }
}
