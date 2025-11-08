<?php

declare(strict_types=1);

/**
 * Middleware Configuration
 *
 * Configure global middleware and middleware aliases here.
 */

use App\Presentation\Http\Middleware\AddSecurityHeaders;
use App\Presentation\Http\Middleware\Authenticate;
use App\Presentation\Http\Middleware\LogRequest;
use App\Presentation\Http\Middleware\ValidateJsonRequest;

return [
    /*
    |--------------------------------------------------------------------------
    | Global Middleware
    |--------------------------------------------------------------------------
    |
    | Global middleware are executed on every request to your application.
    | Add middleware class names to this array.
    |
    | Example use cases:
    | - Security headers on all responses
    | - Request/response logging
    | - CORS handling
    | - Input sanitization
    |
    */
    'global' => [
        // Add global middleware here
        // AddSecurityHeaders::class,  // Uncomment to add security headers
        // LogRequest::class,           // Uncomment to log all requests
    ],

    /*
    |--------------------------------------------------------------------------
    | Middleware Aliases
    |--------------------------------------------------------------------------
    |
    | Define short aliases for middleware to use in route definitions.
    | This allows you to use short names like 'auth' instead of the full class name.
    |
    | Example usage in routes:
    | $router->get('/dashboard', [Controller::class, 'index'])->middleware(['auth', 'log']);
    | $router->post('/api/data', [ApiController::class, 'store'])->middleware(['json', 'auth']);
    |
    */
    'aliases' => [
        // Authentication & Authorization
        'auth' => Authenticate::class,

        // Request/Response handling
        'log' => LogRequest::class,
        'security' => AddSecurityHeaders::class,
        'json' => ValidateJsonRequest::class,

        // Add more aliases here as needed
        // 'guest' => GuestMiddleware::class,
        // 'verified' => EnsureEmailIsVerified::class,
        // 'throttle' => ThrottleRequests::class,
        // 'admin' => AdminMiddleware::class,
        // 'cors' => HandleCors::class,
    ],
];
