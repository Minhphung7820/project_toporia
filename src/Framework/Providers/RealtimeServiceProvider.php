<?php

declare(strict_types=1);

namespace Toporia\Framework\Providers;

use Toporia\Framework\Container\ContainerInterface;
use Toporia\Framework\Foundation\ServiceProvider;
use Toporia\Framework\Realtime\RealtimeManager;
use Toporia\Framework\Realtime\Contracts\RealtimeManagerInterface;

/**
 * Realtime Service Provider
 *
 * Registers realtime services with multi-transport and multi-broker support.
 *
 * Services Registered:
 * - RealtimeManager: Central coordinator for realtime communication
 * - Transports: WebSocket, SSE, Long-polling, Memory
 * - Brokers: Redis, RabbitMQ, NATS, PostgreSQL
 *
 * Performance Optimizations:
 * - Singleton pattern for RealtimeManager
 * - Lazy loading of transports/brokers
 * - Container-based DI
 *
 * @package Toporia\Framework\Providers
 */
final class RealtimeServiceProvider extends ServiceProvider
{
    /**
     * {@inheritdoc}
     */
    public function register(ContainerInterface $container): void
    {
        // Register RealtimeManager
        $container->singleton(RealtimeManager::class, function ($c) {
            $config = $c->has('config')
                ? $c->get('config')->get('realtime', [])
                : $this->getDefaultConfig();

            return new RealtimeManager($config, $c);
        });

        // Register interface bindings
        $container->bind(RealtimeManagerInterface::class, fn($c) => $c->get(RealtimeManager::class));
        $container->bind('realtime', fn($c) => $c->get(RealtimeManager::class));
    }

    /**
     * Get default realtime configuration.
     *
     * @return array
     */
    private function getDefaultConfig(): array
    {
        return [
            'default_transport' => 'memory',
            'default_broker' => null,

            'transports' => [
                'memory' => [
                    'driver' => 'memory',
                ],
            ],

            'brokers' => [],

            'authorizers' => [],
        ];
    }
}
