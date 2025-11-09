<?php

declare(strict_types=1);

namespace Toporia\Framework\Providers;

use Toporia\Framework\Container\ContainerInterface;
use Toporia\Framework\Events\Dispatcher;
use Toporia\Framework\Events\Contracts\EventDispatcherInterface;
use Toporia\Framework\Foundation\ServiceProvider;

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
