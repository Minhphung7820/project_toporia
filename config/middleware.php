<?php

declare(strict_types=1);

/**
 * Middleware Configuration
 *
 * Configure global middleware and middleware aliases here.
 */

use App\Presentation\Http\Middleware\Authenticate;

return [
    /*
    |--------------------------------------------------------------------------
    | Global Middleware
    |--------------------------------------------------------------------------
    |
    | Global middleware are executed on every request to your application.
    | Add middleware class names to this array.
    |
    | Example:
    | - TrimStrings::class
    | - ConvertEmptyStringsToNull::class
    | - CheckForMaintenanceMode::class
    |
    */
    'global' => [
        // Add global middleware here
        // Example: TrimStrings::class,
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
    | $router->get('/dashboard', [Controller::class, 'index'])->middleware(['auth', 'verified']);
    |
    */
    'aliases' => [
        'auth' => Authenticate::class,
        // Add more aliases here
        // 'guest' => GuestMiddleware::class,
        // 'verified' => EnsureEmailIsVerified::class,
        // 'throttle' => ThrottleRequests::class,
        // 'admin' => AdminMiddleware::class,
    ],
];
