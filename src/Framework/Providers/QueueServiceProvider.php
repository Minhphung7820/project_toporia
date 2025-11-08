<?php

declare(strict_types=1);

namespace Toporia\Framework\Providers;

use Toporia\Framework\Container\ContainerInterface;
use Toporia\Framework\Foundation\ServiceProvider;
use Toporia\Framework\Queue\QueueInterface;
use Toporia\Framework\Queue\QueueManager;
use Toporia\Framework\Queue\QueueManagerInterface;

/**
 * Queue Service Provider
 *
 * Registers queue services with multiple driver support.
 */
final class QueueServiceProvider extends ServiceProvider
{
    public function register(ContainerInterface $container): void
    {
        $container->singleton(QueueManager::class, function ($c) {
            $config = $c->has('config')
                ? $c->get('config')->get('queue', [])
                : $this->getDefaultConfig();

            // Note: Database connection injection is handled lazily by QueueManager
            // when the database driver is actually used, not during registration.
            // This prevents boot-time connection errors.

            return new QueueManager($config, $c);
        });

        $container->bind(QueueManagerInterface::class, fn($c) => $c->get(QueueManager::class));
        $container->bind(QueueInterface::class, fn($c) => $c->get(QueueManager::class)->driver());
        $container->bind('queue', fn($c) => $c->get(QueueManager::class));
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
