<?php

declare(strict_types=1);

namespace Toporia\Framework\Providers;

use Toporia\Framework\Container\ContainerInterface;
use Toporia\Framework\Foundation\ServiceProvider;
use Toporia\Framework\Log\LogManager;
use Toporia\Framework\Log\Contracts\LoggerInterface;

/**
 * Log Service Provider
 *
 * Registers logging services into the container.
 * Provides both LogManager (multi-channel) and default logger.
 */
final class LogServiceProvider extends ServiceProvider
{
    public function register(ContainerInterface $container): void
    {
        // Register LogManager singleton
        $container->singleton(LogManager::class, function ($c) {
            $config = $c->get('config')->get('logging', []);
            return new LogManager($config);
        });

        // Register default logger (convenience binding)
        $container->singleton(LoggerInterface::class, function ($c) {
            return $c->get(LogManager::class)->channel();
        });

        // Convenience bindings
        $container->bind('log', fn($c) => $c->get(LogManager::class));
        $container->bind('logger', fn($c) => $c->get(LoggerInterface::class));
    }
}
