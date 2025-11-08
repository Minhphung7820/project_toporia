<?php

declare(strict_types=1);

namespace App\Providers;

use Framework\Container\ContainerInterface;
use Framework\Events\EventDispatcherInterface;
use Framework\Foundation\ServiceProvider;

/**
 * Application Event Service Provider
 *
 * Register event listeners and subscribers here.
 */
class EventServiceProvider extends ServiceProvider
{
    /**
     * {@inheritdoc}
     */
    public function boot(ContainerInterface $container): void
    {
        /** @var EventDispatcherInterface $events */
        $events = $container->get(EventDispatcherInterface::class);

        // Register event listeners
        $this->registerListeners($events);
    }

    /**
     * Register event listeners.
     *
     * @param EventDispatcherInterface $events
     * @return void
     */
    protected function registerListeners(EventDispatcherInterface $events): void
    {
        // User events
        $events->listen('UserLoggedIn', function ($event) {
            $payload = $event->getPayload();
            error_log('[Login] ' . ($payload['email'] ?? 'unknown'));
        });

        // Product events
        $events->listen('ProductCreated', function ($event) {
            $payload = $event->getPayload();
            error_log('[ProductCreated] ' . json_encode($payload));
        });
    }
}
