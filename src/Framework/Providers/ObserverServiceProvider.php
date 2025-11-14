<?php

declare(strict_types=1);

namespace Toporia\Framework\Providers;

use Toporia\Framework\Container\ContainerInterface;
use Toporia\Framework\Foundation\ServiceProvider;
use Toporia\Framework\Observer\ObserverManager;
use Toporia\Framework\Observer\Contracts\ObserverManagerInterface;

/**
 * Observer Service Provider
 *
 * Registers the observer manager and bootstraps observers.
 *
 * Performance:
 * - Singleton observer manager (shared instance)
 * - Lazy observer loading (only when needed)
 * - Efficient observer registration
 *
 * SOLID Principles:
 * - Single Responsibility: Only manages observer service registration
 * - Dependency Inversion: Depends on ObserverManagerInterface abstraction
 *
 * @package Toporia\Framework\Providers
 */
final class ObserverServiceProvider extends ServiceProvider
{
    /**
     * {@inheritdoc}
     */
    public function register(ContainerInterface $container): void
    {
        // Register Observer Manager as singleton
        $container->singleton(ObserverManagerInterface::class, function ($c) {
            return new ObserverManager($c);
        });

        $container->singleton(ObserverManager::class, function ($c) {
            return $c->get(ObserverManagerInterface::class);
        });

        $container->bind('observer', fn($c) => $c->get(ObserverManagerInterface::class));
    }

    /**
     * {@inheritdoc}
     */
    public function boot(ContainerInterface $container): void
    {
        // Bootstrap observers from application
        // This allows application-level observers to be registered
        $this->bootstrapObservers($container);
    }

    /**
     * Bootstrap observers from application configuration.
     *
     * @param ContainerInterface $container Container instance
     * @return void
     */
    private function bootstrapObservers(ContainerInterface $container): void
    {
        $manager = $container->get(ObserverManagerInterface::class);

        // Get observer configuration
        $observers = config('observers', []);

        // Register observers from config
        foreach ($observers as $observableClass => $observerConfig) {
            if (is_string($observerConfig)) {
                // Simple: single observer class
                $manager->register($observableClass, $observerConfig);
            } elseif (is_array($observerConfig)) {
                // Advanced: multiple observers with options
                foreach ($observerConfig as $observer) {
                    if (is_string($observer)) {
                        $manager->register($observableClass, $observer);
                    } elseif (is_array($observer)) {
                        $observerClass = $observer['class'] ?? null;
                        $event = $observer['event'] ?? null;
                        $priority = (int) ($observer['priority'] ?? 0);

                        if ($observerClass) {
                            $manager->register($observableClass, $observerClass, $event, $priority);
                        }
                    }
                }
            }
        }
    }
}

