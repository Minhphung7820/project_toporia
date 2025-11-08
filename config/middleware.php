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
    | Middleware Groups
    |--------------------------------------------------------------------------
    |
    | Middleware groups allow you to apply multiple middleware to routes easily.
    | Each group is applied to a specific set of routes (e.g., web, api).
    |
    | Groups are automatically applied by RouteServiceProvider when loading
    | route files:
    | - routes/web.php   -> 'web' middleware group
    | - routes/api.php   -> 'api' middleware group
    |
    */
    'groups' => [
        'web' => [
            // Web routes middleware
            AddSecurityHeaders::class,  // Security headers for web
            // LogRequest::class,        // Uncomment to log web requests
        ],

        'api' => [
            // API routes middleware
            ValidateJsonRequest::class,  // Validate JSON for API
            // LogRequest::class,         // Uncomment to log API requests
            // ThrottleRequests::class,   // Uncomment for rate limiting
        ],
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
        'auth' => Authenticate::class,  // Uses 'web' guard by default
        'auth:api' => fn($container) => new Authenticate('api'),  // Uses 'api' guard

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
