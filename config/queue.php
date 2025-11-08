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
    */
    'default' => env('QUEUE_CONNECTION', 'sync'),

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
            'port' => env('REDIS_PORT', 6379),
            'password' => env('REDIS_PASSWORD'),
            'database' => env('REDIS_QUEUE_DB', 0),
            'queue' => 'default',
            'retry_after' => 90,
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
