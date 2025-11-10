<?php

declare(strict_types=1);

/**
 * Realtime Configuration
 *
 * Multi-transport and multi-broker realtime communication system.
 * Supports: WebSocket, SSE, Long-polling, Redis Pub/Sub, RabbitMQ, NATS
 *
 * Performance Tips:
 * - Use WebSocket for best latency (<5ms)
 * - Use Redis broker for multi-server scaling
 * - Use Memory transport for single-server testing
 * - Enable presence channels for online user tracking
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Default Transport
    |--------------------------------------------------------------------------
    |
    | Default transport for client-server communication.
    | Options: 'memory', 'websocket', 'sse', 'longpolling'
    |
    | - memory: In-memory (testing only)
    | - websocket: WebSocket via Swoole/RoadRunner (production)
    | - sse: Server-Sent Events (notifications)
    | - longpolling: HTTP fallback (legacy browsers)
    |
    */
    'default_transport' => env('REALTIME_TRANSPORT', 'memory'),

    /*
    |--------------------------------------------------------------------------
    | Default Broker
    |--------------------------------------------------------------------------
    |
    | Default message broker for multi-server fan-out.
    | Options: null, 'redis', 'rabbitmq', 'nats', 'postgres'
    |
    | null: No broker (single server only)
    | redis: Redis Pub/Sub (simple, fast)
    | rabbitmq: RabbitMQ AMQP (durable, routing)
    | nats: NATS messaging (ultra-fast, clustering)
    | postgres: PostgreSQL LISTEN/NOTIFY (DB-based)
    |
    */
    'default_broker' => env('REALTIME_BROKER', null),

    /*
    |--------------------------------------------------------------------------
    | Transport Drivers
    |--------------------------------------------------------------------------
    |
    | Configure available transport drivers.
    |
    */
    'transports' => [
        'memory' => [
            'driver' => 'memory',
        ],

        'websocket' => [
            'driver' => 'websocket',
            'host' => env('REALTIME_WS_HOST', '0.0.0.0'),
            'port' => env('REALTIME_WS_PORT', 6001),
            'ssl' => env('REALTIME_WS_SSL', false),
            'cert' => env('REALTIME_WS_CERT'),
            'key' => env('REALTIME_WS_KEY'),
        ],

        'sse' => [
            'driver' => 'sse',
            'path' => env('REALTIME_SSE_PATH', '/realtime/sse'),
        ],

        'longpolling' => [
            'driver' => 'longpolling',
            'path' => env('REALTIME_POLLING_PATH', '/realtime/poll'),
            'timeout' => env('REALTIME_POLLING_TIMEOUT', 30), // seconds
        ],

        'socketio' => [
            'driver' => 'socketio',
            'host' => env('REALTIME_SOCKETIO_HOST', '0.0.0.0'),
            'port' => env('REALTIME_SOCKETIO_PORT', 3000),
            'ssl' => env('REALTIME_SOCKETIO_SSL', false),
            'cert' => env('REALTIME_SOCKETIO_CERT'),
            'key' => env('REALTIME_SOCKETIO_KEY'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Broker Drivers
    |--------------------------------------------------------------------------
    |
    | Configure available broker drivers for multi-server scaling.
    |
    */
    'brokers' => [
        'redis' => [
            'driver' => 'redis',
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'port' => env('REDIS_PORT', 6379),
            'password' => env('REDIS_PASSWORD'),
            'database' => env('REDIS_DB', 0),
        ],

        'rabbitmq' => [
            'driver' => 'rabbitmq',
            'host' => env('RABBITMQ_HOST', 'localhost'),
            'port' => env('RABBITMQ_PORT', 5672),
            'user' => env('RABBITMQ_USER', 'guest'),
            'password' => env('RABBITMQ_PASSWORD', 'guest'),
            'vhost' => env('RABBITMQ_VHOST', '/'),
        ],

        'nats' => [
            'driver' => 'nats',
            'url' => env('NATS_URL', 'nats://localhost:4222'),
        ],

        'postgres' => [
            'driver' => 'postgres',
            // Uses existing database connection
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Channel Authorization
    |--------------------------------------------------------------------------
    |
    | Define authorization callbacks for channel patterns.
    | Callbacks receive (ConnectionInterface $connection, string $channel)
    |
    | Example:
    | 'private-chat.*' => function($connection, $channel) {
    |     $chatId = str_replace('private-chat.', '', $channel);
    |     return ChatRoom::find($chatId)->hasUser($connection->getUserId());
    | },
    |
    */
    'authorizers' => [
        // Public channels - no auth required
        'public.*' => fn() => true,
        'news' => fn() => true,
        'announcements' => fn() => true,

        // Private channels - require authentication
        'private-*' => function ($connection, $channel) {
            return $connection->isAuthenticated();
        },

        // User-specific channels
        'user.*' => function ($connection, $channel) {
            $userId = str_replace('user.', '', $channel);
            return $connection->getUserId() == $userId;
        },

        // Presence channels - require authentication
        'presence-*' => function ($connection, $channel) {
            return $connection->isAuthenticated();
        },
    ],

    /*
    |--------------------------------------------------------------------------
    | Presence Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for presence channel features.
    |
    */
    'presence' => [
        'enabled' => true,
        'timeout' => 120, // seconds before user marked offline
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Protect against message flooding.
    |
    */
    'rate_limit' => [
        'enabled' => env('REALTIME_RATE_LIMIT', true),
        'messages_per_minute' => env('REALTIME_RATE_LIMIT_MESSAGES', 60),
    ],
];
