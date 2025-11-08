<?php

declare(strict_types=1);

/**
 * Authentication Configuration
 *
 * Configure authentication guards, user providers, and password options.
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Default Authentication Guard
    |--------------------------------------------------------------------------
    |
    | This option controls the default authentication guard that will be
    | used to authenticate users. You may change it as needed.
    |
    | Supported: "web", "api"
    |
    */
    'defaults' => [
        'guard' => 'web',
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | Guards define how users are authenticated for each request.
    | A guard is a combination of a driver (session, token) and a user provider.
    |
    | Supported drivers: "session", "token"
    |
    */
    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],

        'api' => [
            'driver' => 'token',
            'provider' => 'users',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    |
    | User providers define how users are retrieved from storage.
    | The default is "repository" which uses the UserRepository.
    |
    */
    'providers' => [
        'users' => [
            'driver' => 'repository',
            'repository' => \App\Domain\User\UserRepository::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Options
    |--------------------------------------------------------------------------
    |
    | Configure password hashing options.
    |
    */
    'passwords' => [
        'algorithm' => PASSWORD_DEFAULT,
        'cost' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Session Options
    |--------------------------------------------------------------------------
    |
    | Configure session-based authentication options.
    |
    */
    'session' => [
        'cookie_name' => 'remember_web',
        'lifetime' => 86400 * 30, // 30 days in seconds
    ],
];
