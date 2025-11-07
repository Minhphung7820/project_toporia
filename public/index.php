<?php

declare(strict_types=1);

/**
 * Application Bootstrap
 *
 * This file is the entry point for all HTTP requests.
 * It initializes the dependency injection container, registers core services,
 * loads routes, and dispatches the request to the appropriate handler.
 */

// Error reporting for development
ini_set('display_errors', '1');
error_reporting(E_ALL);

// Start session
session_start();

// Autoloader
require __DIR__ . '/../vendor/autoload.php';

use Framework\Container\Container;
use Framework\Container\ContainerInterface;
use Framework\Events\Dispatcher;
use Framework\Events\EventDispatcherInterface;
use Framework\Http\Request;
use Framework\Http\RequestInterface;
use Framework\Http\Response;
use Framework\Http\ResponseInterface;
use Framework\Routing\Router;
use Framework\Routing\RouterInterface;
use App\Infrastructure\Auth\SessionAuth;

/*
|--------------------------------------------------------------------------
| Create The Application Container
|--------------------------------------------------------------------------
|
| The container manages dependency injection and service location for the
| entire application. Services can be bound as factories or singletons.
|
*/

$container = new Container();

/*
|--------------------------------------------------------------------------
| Register Core Services
|--------------------------------------------------------------------------
|
| Bind framework core services to the container. These services are
| available throughout the application via dependency injection.
|
*/

// HTTP Layer - Request and Response are created per request
$container->bind(RequestInterface::class, fn() => Request::capture());
$container->bind(Request::class, fn() => Request::capture());
$container->bind('request', fn() => Request::capture());

$container->bind(ResponseInterface::class, fn() => new Response());
$container->bind(Response::class, fn() => new Response());
$container->bind('response', fn() => new Response());

// Event Dispatcher - Singleton for global event handling
$container->singleton(EventDispatcherInterface::class, fn() => new Dispatcher());
$container->singleton(Dispatcher::class, fn() => new Dispatcher());
$container->singleton('events', fn() => new Dispatcher());

// Router - Singleton with injected dependencies
$container->singleton(RouterInterface::class, fn(ContainerInterface $c) => new Router(
    $c->get(Request::class),
    $c->get(Response::class),
    $c
));
$container->singleton(Router::class, fn(ContainerInterface $c) => new Router(
    $c->get(Request::class),
    $c->get(Response::class),
    $c
));

/*
|--------------------------------------------------------------------------
| Register Application Services
|--------------------------------------------------------------------------
|
| Bind application-specific services here. These are services from the
| App namespace that provide business functionality.
|
*/

// Authentication - Singleton to maintain auth state
$container->singleton('auth', fn() => new SessionAuth());

/*
|--------------------------------------------------------------------------
| Register Helper Functions
|--------------------------------------------------------------------------
|
| Global helper functions for convenient access to common services.
| These are optional but provide a cleaner syntax.
|
*/

/**
 * Get the container instance or resolve a service.
 *
 * @param string|null $id Service identifier or null for container.
 * @return mixed
 */
function app(?string $id = null): mixed
{
    global $container;
    return $id !== null ? $container->get($id) : $container;
}

/**
 * Dispatch an event.
 *
 * @param string|\Framework\Events\EventInterface $event Event name or object.
 * @param array $payload Event payload data.
 * @return \Framework\Events\EventInterface
 */
function event(string|\Framework\Events\EventInterface $event, array $payload = []): \Framework\Events\EventInterface
{
    return app('events')->dispatch($event, $payload);
}

/**
 * Get the authentication service.
 *
 * @return mixed
 */
function auth(): mixed
{
    return app('auth');
}

/*
|--------------------------------------------------------------------------
| Load Route Definitions
|--------------------------------------------------------------------------
|
| Load all route definitions from the routes directory. Routes are
| registered with the router and will be matched during dispatch.
|
*/

$router = app(Router::class);
require __DIR__ . '/../routes/web.php';

/*
|--------------------------------------------------------------------------
| Dispatch The Request
|--------------------------------------------------------------------------
|
| Process the incoming HTTP request through the router. The router will
| match the request to a route and execute it through the middleware pipeline.
|
*/

$router->dispatch();
