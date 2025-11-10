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
use App\Presentation\Http\Controllers\FileUploadController;
use App\Presentation\Http\Action\Product\CreateProductAction;

/** @var Router $router */

// Public routes
$router->get('/', [HomeController::class, 'index']);
$router->get('/login', [AuthController::class, 'showLoginForm']);
$router->post('/login', [AuthController::class, 'login']);
$router->get('/register', [AuthController::class, 'showRegisterForm']);
$router->post('/register', [AuthController::class, 'register']);

// Auth routes
$router->post('/logout', [AuthController::class, 'logout']);

// API routes - Authentication
$router->post('/api/login', [AuthController::class, 'login']);
$router->post('/api/register', [AuthController::class, 'register']);
$router->post('/api/logout', [AuthController::class, 'logout']);
$router->get('/api/me', [AuthController::class, 'me'])->middleware(['auth:api']);

// Protected routes (require authentication)
$router->get('/dashboard', [HomeController::class, 'dashboard'], [Authenticate::class]);
$router->get('/products/create', [ProductsController::class, 'create'], [Authenticate::class]);
$router->post('/products', [ProductsController::class, 'store'], [Authenticate::class]);
$router->get('/products/{id}', [ProductsController::class, 'show']);

// API routes (ADR pattern)
$router->post('/v2/products', [CreateProductAction::class, '__invoke']);

// File Upload routes
$router->get('/upload', [FileUploadController::class, 'showForm']);
$router->post('/upload/local', [FileUploadController::class, 'uploadLocal']);
$router->post('/upload/s3', [FileUploadController::class, 'uploadToS3']);
$router->get('/upload/list', [FileUploadController::class, 'listFiles']);
$router->get('/upload/download/{filename}', [FileUploadController::class, 'download']);
$router->delete('/upload/{filename}', [FileUploadController::class, 'delete']);
