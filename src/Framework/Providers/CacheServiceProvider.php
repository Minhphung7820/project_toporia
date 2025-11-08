<?php

declare(strict_types=1);

namespace Toporia\Framework\Providers;

use Toporia\Framework\Container\ContainerInterface;
use Toporia\Framework\Foundation\ServiceProvider;
use Toporia\Framework\Cache\CacheInterface;
use Toporia\Framework\Cache\CacheManager;
use Toporia\Framework\Cache\CacheManagerInterface;

/**
 * Cache Service Provider
 *
 * Registers cache services with multiple driver support.
 */
final class CacheServiceProvider extends ServiceProvider
{
    public function register(ContainerInterface $container): void
    {
        $container->singleton(CacheManager::class, function ($c) {
            $config = $c->has('config')
                ? $c->get('config')->get('cache', [])
                : $this->getDefaultConfig();

            return new CacheManager($config);
        });

        $container->bind(CacheManagerInterface::class, fn($c) => $c->get(CacheManager::class));
        $container->bind(CacheInterface::class, fn($c) => $c->get(CacheManager::class));
        $container->bind('cache', fn($c) => $c->get(CacheManager::class));
    }

    /**
     * Get default cache configuration
     *
     * @return array
     */
    private function getDefaultConfig(): array
    {
        return [
            'default' => 'file',
            'stores' => [
                'file' => [
                    'driver' => 'file',
                    'path' => sys_get_temp_dir() . '/cache',
                ],
                'memory' => [
                    'driver' => 'memory',
                ],
            ],
        ];
    }
}
