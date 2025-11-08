<?php

declare(strict_types=1);

/**
 * Cache Configuration
 *
 * Configure cache drivers and default settings.
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Default Cache Store
    |--------------------------------------------------------------------------
    |
    | This option controls the default cache store that will be used by the
    | framework. This store is used when another is not explicitly specified.
    |
    */
    'default' => env('CACHE_DRIVER', 'file'),

    /*
    |--------------------------------------------------------------------------
    | Cache Stores
    |--------------------------------------------------------------------------
    |
    | Here you may define all of the cache stores for your application as
    | well as their drivers.
    |
    | Supported drivers: "file", "redis", "memory"
    |
    */
    'stores' => [
        'file' => [
            'driver' => 'file',
            'path' => __DIR__ . '/../storage/cache',
        ],

        'redis' => [
            'driver' => 'redis',
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'port' => env('REDIS_PORT', 6379),
            'password' => env('REDIS_PASSWORD'),
            'database' => env('REDIS_CACHE_DB', 1),
        ],

        'memory' => [
            'driver' => 'memory',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Key Prefix
    |--------------------------------------------------------------------------
    |
    | When utilizing a RAM based store such as Redis, there might be other
    | applications utilizing the same cache. So, we'll specify a prefix to
    | get to avoid collisions.
    |
    */
    'prefix' => env('CACHE_PREFIX', 'toporia_cache'),
];
