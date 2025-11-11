<?php

declare(strict_types=1);

namespace Toporia\Framework\Foundation;

use Toporia\Framework\Container\ContainerInterface;

/**
 * Framework Service Provider
 *
 * Registers all framework-level service providers.
 * This keeps framework concerns separate from application concerns.
 *
 * Clean Architecture:
 * - Framework layer manages its own providers
 * - Application layer doesn't need to know framework internals
 */
final class FrameworkServiceProvider extends ServiceProvider
{
    /**
     * Get all framework service providers.
     *
     * @return array<class-string<ServiceProvider>>
     */
    public static function providers(): array
    {
        return [
            // Core services (order matters!)
            \Toporia\Framework\Providers\ConfigServiceProvider::class,
            \Toporia\Framework\Providers\HttpServiceProvider::class,
            \Toporia\Framework\Providers\EventServiceProvider::class,
            \Toporia\Framework\Providers\RoutingServiceProvider::class,
            \Toporia\Framework\Providers\UrlServiceProvider::class,
            \Toporia\Framework\Providers\LogServiceProvider::class,
            \Toporia\Framework\Providers\ConsoleServiceProvider::class,

            // Feature services
            \Toporia\Framework\Providers\AuthServiceProvider::class,
            \Toporia\Framework\Providers\SecurityServiceProvider::class,
            \Toporia\Framework\Providers\CacheServiceProvider::class,
            \Toporia\Framework\Providers\QueueServiceProvider::class,
            \Toporia\Framework\Providers\ScheduleServiceProvider::class,
            \Toporia\Framework\Providers\MailServiceProvider::class,
            \Toporia\Framework\Providers\HttpClientServiceProvider::class,
            \Toporia\Framework\Providers\DatabaseServiceProvider::class,
            \Toporia\Framework\Providers\StorageServiceProvider::class,
            \Toporia\Framework\Providers\NotificationServiceProvider::class,
            \Toporia\Framework\Providers\RealtimeServiceProvider::class,
        ];
    }

    /**
     * Register framework services.
     *
     * This method is not used - we use static providers() instead.
     */
    public function register(ContainerInterface $container): void
    {
        // Framework providers are registered via providers() method
    }
}
