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
    | - 'rabbitmq' = Use RabbitMQ (enterprise-grade, requires RabbitMQ server)
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
    | Supported drivers: "sync", "database", "redis", "rabbitmq"
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

        'rabbitmq' => [
            'driver' => 'rabbitmq',
            'host' => env('RABBITMQ_HOST', '127.0.0.1'),
            'port' => (int) env('RABBITMQ_PORT', 5672),
            'user' => env('RABBITMQ_USER', 'guest'),
            'password' => env('RABBITMQ_PASSWORD', 'guest'),
            'vhost' => env('RABBITMQ_VHOST', '/'),
            'exchange' => env('RABBITMQ_EXCHANGE', 'toporia'),
            'exchange_type' => env('RABBITMQ_EXCHANGE_TYPE', 'direct'),
            'queue' => env('RABBITMQ_QUEUE', 'default'),
            'routing_key' => env('RABBITMQ_ROUTING_KEY'),
            'durable' => (bool) env('RABBITMQ_DURABLE', true),
            'connection_timeout' => (float) env('RABBITMQ_CONNECTION_TIMEOUT', 3.0),
            'read_write_timeout' => (float) env('RABBITMQ_READ_WRITE_TIMEOUT', 3.0),
            'heartbeat' => (int) env('RABBITMQ_HEARTBEAT', 0),
            'prefetch_count' => (int) env('RABBITMQ_PREFETCH_COUNT', 10), // Default 10 for better throughput
            'pop_timeout' => (float) env('RABBITMQ_POP_TIMEOUT', 0.5), // Timeout for blocking wait (seconds) - shorter for faster Ctrl+C response
            // Delayed message plugin (optional)
            'delayed_exchange' => env('RABBITMQ_DELAYED_EXCHANGE', false),
            // Dead letter queue (optional)
            'dead_letter_exchange' => env('RABBITMQ_DEAD_LETTER_EXCHANGE'),
            // Message TTL (optional, in milliseconds)
            'message_ttl' => env('RABBITMQ_MESSAGE_TTL'),
            // Max priority (optional, 0-255)
            'max_priority' => env('RABBITMQ_MAX_PRIORITY'),
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
