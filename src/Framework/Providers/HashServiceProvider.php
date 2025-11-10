<?php

declare(strict_types=1);

namespace Toporia\Framework\Providers;

use Toporia\Framework\Container\ContainerInterface;
use Toporia\Framework\Foundation\ServiceProvider;
use Toporia\Framework\Hashing\HashManager;

/**
 * Hash Service Provider
 *
 * Registers hashing services into the container.
 * Provides password hashing functionality across the application.
 *
 * Services Registered:
 * - 'hash' => HashManager instance
 *
 * Usage:
 * ```php
 * $hash = app('hash');
 * $hashed = $hash->make('password');
 * ```
 *
 * @package Toporia\Framework\Providers
 */
final class HashServiceProvider extends ServiceProvider
{
    /**
     * {@inheritdoc}
     */
    public function register(ContainerInterface $container): void
    {
        // Register HashManager as singleton
        $container->singleton('hash', function ($c) {
            // Get hashing configuration
            $config = [];
            if ($c->has('config')) {
                $config = $c->get('config')->get('hashing', []);
            }

            return new HashManager($config);
        });

        // Register HasherInterface binding (for dependency injection)
        $container->bind(
            \Toporia\Framework\Hashing\Contracts\HasherInterface::class,
            fn($c) => $c->get('hash')->driver()
        );
    }

    /**
     * {@inheritdoc}
     */
    public function boot(ContainerInterface $container): void
    {
        // Nothing to boot for hash service
    }
}
