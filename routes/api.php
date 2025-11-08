<?php

declare(strict_types=1);

/**
 * API Routes
 *
 * These routes are loaded by RouteServiceProvider within the 'api' middleware group.
 * All routes here are automatically prefixed with '/api'.
 * All routes receive the middleware from the 'api' group in config/middleware.php.
 *
 * Example routes available:
 * - GET    /api/products
 * - GET    /api/products/{id}
 * - POST   /api/products
 * - PUT    /api/products/{id}
 * - DELETE /api/products/{id}
 */

use Toporia\Framework\Routing\Router;

/** @var Router $router */

// Example API routes (commented out - uncomment when you have API controllers)

/*
// Product API endpoints
$router->get('/products', [ProductApiController::class, 'index']);
$router->get('/products/{id}', [ProductApiController::class, 'show']);
$router->post('/products', [ProductApiController::class, 'store'])
    ->middleware(['auth:api']);
$router->put('/products/{id}', [ProductApiController::class, 'update'])
    ->middleware(['auth:api']);
$router->delete('/products/{id}', [ProductApiController::class, 'destroy'])
    ->middleware(['auth:api']);

// Health check endpoint (public)
$router->get('/health', function() {
    return ['status' => 'ok', 'timestamp' => time()];
});

// Version endpoint (public)
$router->get('/version', function() {
    return ['version' => '1.0.0', 'api' => 'v1'];
});
*/

// Versioned API routes example
/*
$router->group(['prefix' => 'v1'], function (Router $router) {
    // All routes here will be prefixed with /api/v1/

    $router->get('/users', [UserApiController::class, 'index']);
    $router->get('/users/{id}', [UserApiController::class, 'show']);

    // Protected routes
    $router->group(['middleware' => ['auth:api']], function (Router $router) {
        $router->post('/users', [UserApiController::class, 'store']);
        $router->put('/users/{id}', [UserApiController::class, 'update']);
        $router->delete('/users/{id}', [UserApiController::class, 'destroy']);
    });
});
*/
