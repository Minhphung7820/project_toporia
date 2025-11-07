<?php
declare(strict_types=1);

ini_set('display_errors', '1'); error_reporting(E_ALL);
session_start();

require __DIR__ . '/../vendor/autoload.php';

use Framework\Container\Container;
use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Routing\Router;
use Framework\Events\Dispatcher;
use App\Presentation\Http\Middleware\Authenticate;
use App\Infrastructure\Auth\SessionAuth;

// Build container
$container = new Container();
$container->bind('request', fn() => Request::capture());
$container->bind('response', fn() => new Response());
$container->bind('events', fn() => new Dispatcher());
$container->singleton('auth', fn() => new SessionAuth());
$container->bind(Router::class, fn($c) => new Router($c->make('request'), $c->make('response'), $c));

// Small helpers (optional)
function app(?string $key = null) {
    global $container;
    return $key ? $container->make($key) : $container;
}
function event(string $name, array $payload = []) { return app('events')->dispatch($name, $payload); }
function auth() { return app('auth'); }

$router = app(Router::class);

// Routes
require __DIR__ . '/../routes/web.php';

// Dispatch
$router->dispatch();
