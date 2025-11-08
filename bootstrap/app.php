<?php

declare(strict_types=1);

/**
 * Application Bootstrap Configuration
 *
 * This file creates and configures the application instance.
 * It registers all service providers needed by the framework and application.
 */

use Framework\Foundation\Application;

// Framework Service Providers
use Framework\Providers\HttpServiceProvider;
use Framework\Providers\EventServiceProvider;
use Framework\Providers\RoutingServiceProvider;
use Framework\Providers\DatabaseServiceProvider;

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
    \Framework\Providers\ConfigServiceProvider::class,
    HttpServiceProvider::class,
    EventServiceProvider::class,
    RoutingServiceProvider::class,
    // DatabaseServiceProvider::class, // Uncomment when you need database

    // Application providers
    AppServiceProvider::class,
    RepositoryServiceProvider::class,
    \App\Providers\EventServiceProvider::class,
    \App\Providers\RouteServiceProvider::class,
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
| Return The Application
|--------------------------------------------------------------------------
|
| This script returns the application instance. The instance is given to
| the calling script so we can separate the building of the instances
| from the actual running of the application and sending responses.
|
*/

return $app;
