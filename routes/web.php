<?php
use Framework\Routing\Router;
/** @var Router $router */
$router = app(Framework\Routing\Router::class);

use App\Presentation\Http\Middleware\Authenticate;

$router->get('/', [App\Presentation\Http\Controllers\HomeController::class, 'index']);
$router->get('/login', [App\Presentation\Http\Controllers\AuthController::class, 'showLogin']);
$router->post('/login', [App\Presentation\Http\Controllers\AuthController::class, 'login']);
$router->get('/logout', [App\Presentation\Http\Controllers\AuthController::class, 'logout']);

$router->get('/dashboard', [App\Presentation\Http\Controllers\HomeController::class, 'dashboard'], [Authenticate::class]);
$router->get('/products/create', [App\Presentation\Http\Controllers\ProductsController::class, 'create'], [Authenticate::class]);
$router->post('/products', [App\Presentation\Http\Controllers\ProductsController::class, 'store'], [Authenticate::class]);
$router->get('/products/{id}', [App\Presentation\Http\Controllers\ProductsController::class, 'show']);

$router->post('/v2/products', [App\Presentation\Http\Action\Product\CreateProductAction::class, '__invoke']);
