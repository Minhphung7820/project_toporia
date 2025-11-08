<?php

declare(strict_types=1);

namespace App\Providers;

use Framework\Container\ContainerInterface;
use Framework\Foundation\ServiceProvider;
use App\Infrastructure\Auth\SessionAuth;

/**
 * Application Service Provider
 *
 * Register core application services here.
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * {@inheritdoc}
     */
    public function register(ContainerInterface $container): void
    {
        // Authentication - Singleton to maintain auth state
        $container->singleton('auth', fn() => new SessionAuth());
    }
}
