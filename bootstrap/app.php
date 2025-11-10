<?php

declare(strict_types=1);

/**
 * Application Bootstrap Configuration
 *
 * This file creates and configures the application instance.
 * It registers all service providers needed by the framework and application.
 */

use Toporia\Framework\Foundation\Application;

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
| Register Error Handler
|--------------------------------------------------------------------------
|
| Register beautiful error handler for debugging and production.
| This must be done early to catch all errors.
|
*/

$debug = ($_ENV['APP_DEBUG'] ?? 'true') === 'true';
$errorHandler = new \Toporia\Framework\Error\ErrorHandler($debug);
$errorHandler->register();

/*
|--------------------------------------------------------------------------
| Load Environment Variables
|--------------------------------------------------------------------------
|
| Load environment variables from .env file into $_ENV.
| This must be done before loading helpers and providers.
|
*/

$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue; // Skip comments
        }
        if (strpos($line, '=') !== false) {
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Remove quotes if present
            if (preg_match('/^"(.*)"$/', $value, $matches)) {
                $value = $matches[1];
            } elseif (preg_match("/^'(.*)'$/", $value, $matches)) {
                $value = $matches[1];
            }

            // Expand variables like ${VAR}
            $value = preg_replace_callback('/\$\{([A-Z_]+)\}/', function ($m) {
                return $_ENV[$m[1]] ?? '';
            }, $value);

            $_ENV[$key] = $value;
            putenv("{$key}={$value}");
        }
    }
}

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
    \Toporia\Framework\Providers\HttpServiceProvider::class,
    \Toporia\Framework\Providers\EventServiceProvider::class,
    \Toporia\Framework\Providers\RoutingServiceProvider::class,
    \Toporia\Framework\Providers\ConsoleServiceProvider::class,
    \Toporia\Framework\Providers\AuthServiceProvider::class,      // Auth system
    \Toporia\Framework\Providers\SecurityServiceProvider::class,  // Security (CSRF, Gates, Cookies)
    \Toporia\Framework\Providers\CacheServiceProvider::class,     // Cache system
    \Toporia\Framework\Providers\QueueServiceProvider::class,     // Queue system
    \Toporia\Framework\Providers\ScheduleServiceProvider::class,  // Task scheduler
    \Toporia\Framework\Providers\MailServiceProvider::class,      // Mail system
    \Toporia\Framework\Providers\HttpClientServiceProvider::class, // HTTP client (API calls)
    \Toporia\Framework\Providers\DatabaseServiceProvider::class,  // Database system
    \Toporia\Framework\Providers\StorageServiceProvider::class,   // Storage system (Local, S3, etc.)
    \Toporia\Framework\Providers\NotificationServiceProvider::class, // Notification system (Mail, Database, SMS, Slack)
    \Toporia\Framework\Providers\RealtimeServiceProvider::class,  // Realtime system (WebSocket, SSE, Redis Pub/Sub)

    // Application providers
    \App\Providers\AppServiceProvider::class,
    \App\Providers\RepositoryServiceProvider::class,
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
