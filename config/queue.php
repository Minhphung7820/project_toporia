<?php

declare(strict_types=1);

/**
 * Queue Configuration
 *
 * Configure queue drivers and connections.
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Default Queue Connection
    |--------------------------------------------------------------------------
    |
    | The default queue connection that should be used by the framework.
    |
    | Options:
    | - 'sync' = Execute immediately (development, testing)
    | - 'database' = Store in database (production, requires worker)
    | - 'redis' = Use Redis (high performance, requires Redis server)
    |
    */
    'default' => env('QUEUE_CONNECTION', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Queue Connections
    |--------------------------------------------------------------------------
    |
    | Here you may configure the connection options for each queue backend.
    |
    | Supported drivers: "sync", "database", "redis"
    |
    */
    'connections' => [
        'sync' => [
            'driver' => 'sync',
        ],

        'database' => [
            'driver' => 'database',
            'table' => 'jobs',
            'queue' => 'default',
            'retry_after' => 90,
            // 'connection' will be injected by QueueServiceProvider
        ],

        'redis' => [
            'driver' => 'redis',
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'port' => (int) env('REDIS_PORT', 6379),
            'password' => env('REDIS_PASSWORD'),
            'database' => (int) env('REDIS_DATABASE', 0),
            'queue' => 'default',
            'retry_after' => 90,
            'prefix' => 'queues',                    // Redis key prefix
            'timeout' => 2.0,                        // Connection timeout (seconds)
            'read_timeout' => 2.0,                   // Read timeout (seconds)
            'retry_interval' => 100,                 // Retry interval (milliseconds)
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Failed Queue Jobs
    |--------------------------------------------------------------------------
    |
    | These options configure the behavior of failed queue job logging so you
    | can control how and where failed jobs are stored.
    |
    */
    'failed' => [
        'driver' => 'database',
        'table' => 'failed_jobs',
    ],
];
