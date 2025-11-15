<?php

declare(strict_types=1);

/**
 * HTTP Client Configuration
 *
 * Configure HTTP clients for calling external APIs.
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Default HTTP Client
    |--------------------------------------------------------------------------
    |
    | Default client to use when no specific client is specified.
    |
    */
    'default' => env('HTTP_CLIENT', 'default'),

    /*
    |--------------------------------------------------------------------------
    | HTTP Clients
    |--------------------------------------------------------------------------
    |
    | Configure multiple HTTP clients for different APIs.
    | Each client can have its own base URL, headers, timeout, retry logic, etc.
    |
    */
    'clients' => [
        // Default REST client
        'default' => [
            'driver' => 'rest',
            'timeout' => 30,
            'retry' => [
                'times' => 3,
                'sleep' => 100, // milliseconds
            ],
        ],

        // Example: GitHub API
        'github' => [
            'driver' => 'rest',
            'base_url' => 'https://api.github.com',
            'headers' => [
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => env('APP_NAME', 'Toporia-Framework'),
            ],
            'auth' => [
                'type' => 'bearer',
                'token' => env('GITHUB_TOKEN', ''),
            ],
            'timeout' => 15,
        ],

        // Example: Stripe API
        'stripe' => [
            'driver' => 'rest',
            'base_url' => 'https://api.stripe.com/v1',
            'headers' => [
                'Stripe-Version' => '2023-10-16',
            ],
            'auth' => [
                'type' => 'bearer',
                'token' => env('STRIPE_SECRET_KEY', ''),
            ],
            'timeout' => 20,
            'retry' => [
                'times' => 2,
                'sleep' => 500,
            ],
        ],

        // Example: Internal Microservice
        'microservice' => [
            'driver' => 'rest',
            'base_url' => env('MICROSERVICE_URL', 'http://localhost:8000'),
            'headers' => [
                'X-API-Key' => env('MICROSERVICE_API_KEY', ''),
            ],
            'timeout' => 10,
        ],

        // Example: GraphQL API
        'graphql_api' => [
            'driver' => 'rest',
            'base_url' => env('GRAPHQL_API_URL', 'https://api.example.com/graphql'),
            'auth' => [
                'type' => 'bearer',
                'token' => env('GRAPHQL_API_TOKEN', ''),
            ],
            'timeout' => 20,
        ],

        // Example: OAuth API with Basic Auth
        'oauth_service' => [
            'driver' => 'rest',
            'base_url' => 'https://oauth.example.com',
            'auth' => [
                'type' => 'basic',
                'username' => env('OAUTH_CLIENT_ID', ''),
                'password' => env('OAUTH_CLIENT_SECRET', ''),
            ],
            'timeout' => 15,
        ],
    ],
];
