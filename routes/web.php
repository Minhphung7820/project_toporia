<?php

declare(strict_types=1);

/**
 * Web Routes
 *
 * Define your application routes here.
 * The $router variable is automatically injected by RouteServiceProvider.
 */

use Toporia\Framework\Routing\Router;
use App\Presentation\Http\Middleware\Authenticate;
use App\Presentation\Http\Controllers\HomeController;
use App\Presentation\Http\Controllers\AuthController;
use App\Presentation\Http\Controllers\ProductsController;
use App\Presentation\Http\Action\Product\CreateProductAction;

/** @var Router $router */

// Public routes
$router->get('/', [HomeController::class, 'index'])->middleware('auth');
$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->get('/logout', [AuthController::class, 'logout']);

// Protected routes (require authentication)
$router->get('/dashboard', [HomeController::class, 'dashboard'], [Authenticate::class]);
$router->get('/products/create', [ProductsController::class, 'create'], [Authenticate::class]);
$router->post('/products', [ProductsController::class, 'store'], [Authenticate::class]);
$router->get('/products/{id}', [ProductsController::class, 'show']);

// API routes (ADR pattern)
$router->post('/v2/products', [CreateProductAction::class, '__invoke']);
