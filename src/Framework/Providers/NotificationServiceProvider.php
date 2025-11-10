<?php

declare(strict_types=1);

namespace Toporia\Framework\Providers;

use Toporia\Framework\Container\ContainerInterface;
use Toporia\Framework\Foundation\ServiceProvider;
use Toporia\Framework\Notification\NotificationManager;
use Toporia\Framework\Notification\Contracts\NotificationManagerInterface;

/**
 * Notification Service Provider
 *
 * Registers notification services with multi-channel support.
 *
 * Services Registered:
 * - NotificationManager: Manages notification channels (mail, database, SMS, Slack)
 * - Channel drivers: Mail, Database, SMS, Slack
 *
 * Performance Optimizations:
 * - Singleton pattern for NotificationManager (reuses instances)
 * - Lazy loading of channels (only creates when needed)
 * - Container-based DI (auto-resolves dependencies)
 *
 * SOLID Principles:
 * - Single Responsibility: Only registers notification services
 * - Dependency Inversion: Binds interfaces to implementations
 * - Open/Closed: Extend via custom channels
 *
 * @package Toporia\Framework\Providers
 */
final class NotificationServiceProvider extends ServiceProvider
{
    /**
     * {@inheritdoc}
     */
    public function register(ContainerInterface $container): void
    {
        // Register NotificationManager (manages multiple channels)
        $container->singleton(NotificationManager::class, function ($c) {
            $config = $c->has('config')
                ? $c->get('config')->get('notification', [])
                : $this->getDefaultConfig();

            return new NotificationManager($config, $c);
        });

        // Register notification interface bindings
        $container->bind(NotificationManagerInterface::class, fn($c) => $c->get(NotificationManager::class));
        $container->bind('notification', fn($c) => $c->get(NotificationManager::class));
    }

    /**
     * Get default notification configuration.
     *
     * @return array
     */
    private function getDefaultConfig(): array
    {
        return [
            'default' => 'mail',
            'channels' => [
                'mail' => [
                    'driver' => 'mail',
                ],
                'database' => [
                    'driver' => 'database',
                    'table' => 'notifications',
                ],
            ],
        ];
    }
}
