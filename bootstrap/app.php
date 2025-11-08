<?php

declare(strict_types=1);

/**
 * Application Bootstrap Configuration
 *
 * This file creates and configures the application instance.
 * It registers all service providers needed by the framework and application.
 */

use Toporia\Framework\Foundation\Application;

// Framework Service Providers
use Toporia\Framework\Providers\HttpServiceProvider;
use Toporia\Framework\Providers\EventServiceProvider;
use Toporia\Framework\Providers\RoutingServiceProvider;
use Toporia\Framework\Providers\DatabaseServiceProvider;
use Toporia\Framework\Providers\ConsoleServiceProvider;

// Application Service Providers
use App\Providers\AppServiceProvider;
use App\Providers\RepositoryServiceProvider;

/*
|--------------------------------------------------------------------------
| Create The Application
|--------------------------------------------------------------------------
|
| The first thing we will do is create a new application instance which
| serves as the central hub for the entire framework.
|
*/

$app = new Application(
    basePath: dirname(__DIR__)
);

/*
|--------------------------------------------------------------------------
| Load Helper Functions Early
|--------------------------------------------------------------------------
|
| Load helper functions before booting providers so they're available
| in route files and other boot methods.
|
*/

require __DIR__ . '/helpers.php';

/*
|--------------------------------------------------------------------------
| Register Service Providers
|--------------------------------------------------------------------------
|
| Register all service providers for the application. Service providers
| are responsible for binding services into the container.
|
| Order matters:
| 1. Framework core providers (HTTP, Events, etc.)
| 2. Application providers (Auth, Repositories, etc.)
|
*/

$app->registerProviders([
    // Framework core providers (order matters!)
    \Toporia\Framework\Providers\ConfigServiceProvider::class,
    HttpServiceProvider::class,
    EventServiceProvider::class,
    RoutingServiceProvider::class,
    ConsoleServiceProvider::class,
    \Toporia\Framework\Providers\AuthServiceProvider::class,  // Auth system
    \Toporia\Framework\Providers\SecurityServiceProvider::class,  // Security (CSRF, Gates, Cookies)
    \Toporia\Framework\Providers\CacheServiceProvider::class,     // Cache system
    \Toporia\Framework\Providers\QueueServiceProvider::class,     // Queue system
    \Toporia\Framework\Providers\ScheduleServiceProvider::class,  // Task scheduler
    \Toporia\Framework\Providers\MailServiceProvider::class,      // Mail system
    // DatabaseServiceProvider::class, // Uncomment when you need database

    // Application providers
    AppServiceProvider::class,
    RepositoryServiceProvider::class,
    \App\Providers\EventServiceProvider::class,
    \App\Providers\RouteServiceProvider::class,
    \App\Providers\ScheduleServiceProvider::class,  // Scheduled tasks configuration
]);

/*
|--------------------------------------------------------------------------
| Boot Service Providers
|--------------------------------------------------------------------------
|
| Boot all registered service providers. This is where event listeners
| are registered and other post-registration setup occurs.
|
*/

$app->boot();

/*
|--------------------------------------------------------------------------
| Set Container for Service Accessors
|--------------------------------------------------------------------------
|
| Set the container instance for the ServiceAccessor system.
| This enables static-like access to services (e.g., Cache::get()).
|
*/

\Toporia\Framework\Foundation\ServiceAccessor::setContainer($app->getContainer());

/*
|--------------------------------------------------------------------------
| Return The Application
|--------------------------------------------------------------------------
|
| This script returns the application instance. The instance is given to
| the calling script so we can separate the building of the instances
| from the actual running of the application and sending responses.
|
*/

return $app;
