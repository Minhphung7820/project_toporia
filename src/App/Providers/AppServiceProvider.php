<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\User\UserRepository;
use App\Infrastructure\Persistence\InMemoryUserRepository;
use Toporia\Framework\Container\ContainerInterface;
use Toporia\Framework\Foundation\ServiceProvider;

/**
 * Application Service Provider
 *
 * Register core application-level services here.
 *
 * Keep this provider focused on application business logic services.
 * Framework-level services (Auth, Events, etc.) should be in Framework providers.
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * {@inheritdoc}
     */
    public function register(ContainerInterface $container): void
    {
        // User Repository - Singleton
        // TODO: Replace InMemoryUserRepository with PdoUserRepository for production
        $container->singleton(UserRepository::class, fn() => new InMemoryUserRepository());

        // Register other application services here
        // Examples:
        // - Custom services
        // - Business logic handlers
        // - Domain-specific repositories
    }
}
