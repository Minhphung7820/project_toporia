<?php

declare(strict_types=1);

namespace Toporia\Framework\Providers;

use Toporia\Framework\Container\ContainerInterface;
use Toporia\Framework\Foundation\ServiceProvider;
use Toporia\Framework\Queue\Contracts\Dispatcher;
use Toporia\Framework\Queue\JobDispatcher;
use Toporia\Framework\Queue\QueueInterface;
use Toporia\Framework\Queue\QueueManager;
use Toporia\Framework\Queue\QueueManagerInterface;

/**
 * Queue Service Provider
 *
 * Registers queue services with multiple driver support.
 *
 * Services Registered:
 * - QueueManager: Manages queue connections (sync, database, redis)
 * - JobDispatcher: Dispatches jobs with dependency injection
 * - PendingDispatch: Fluent API for job dispatch configuration
 *
 * Performance Optimizations:
 * - Singleton pattern for QueueManager (reuses instances)
 * - Lazy loading of queue drivers (only creates when needed)
 * - Container-based DI (auto-resolves job dependencies)
 *
 * SOLID Principles:
 * - Single Responsibility: Only registers queue services
 * - Dependency Inversion: Binds interfaces to implementations
 * - Open/Closed: Extend via custom queue drivers
 */
final class QueueServiceProvider extends ServiceProvider
{
    public function register(ContainerInterface $container): void
    {
        // Register QueueManager (manages multiple queue drivers)
        $container->singleton(QueueManager::class, function ($c) {
            $config = $c->has('config')
                ? $c->get('config')->get('queue', [])
                : $this->getDefaultConfig();

            // Note: Database connection injection is handled lazily by QueueManager
            // when the database driver is actually used, not during registration.
            // This prevents boot-time connection errors.

            return new QueueManager($config, $c);
        });

        // Register queue interface bindings
        $container->bind(QueueManagerInterface::class, fn($c) => $c->get(QueueManager::class));
        $container->bind(QueueInterface::class, fn($c) => $c->get(QueueManager::class)->driver());
        $container->bind('queue', fn($c) => $c->get(QueueManager::class));

        // Register JobDispatcher for job dispatching with DI
        $container->singleton(JobDispatcher::class, function ($c) {
            // Default queue NAME (not connection name)
            // This is the queue name used for organizing jobs (e.g., 'emails', 'reports', 'default')
            // NOT the connection name ('sync', 'database', 'redis')
            $defaultQueueName = 'default';

            return new JobDispatcher($c, $defaultQueueName);
        });

        // Register dispatcher interface and alias
        $container->bind(Dispatcher::class, fn($c) => $c->get(JobDispatcher::class));
        $container->bind('dispatcher', fn($c) => $c->get(JobDispatcher::class));
    }

    /**
     * Get default queue configuration
     *
     * @return array
     */
    private function getDefaultConfig(): array
    {
        return [
            'default' => 'sync',
            'connections' => [
                'sync' => [
                    'driver' => 'sync',
                ],
            ],
        ];
    }
}
