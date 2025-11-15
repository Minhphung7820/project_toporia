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
    | kafka: Apache Kafka (high-throughput, persistent)
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
            'host' => env('RABBITMQ_HOST', '127.0.0.1'),
            'port' => env('RABBITMQ_PORT', 5672),
            'user' => env('RABBITMQ_USER', 'guest'),
            'password' => env('RABBITMQ_PASSWORD', 'guest'),
            'vhost' => env('RABBITMQ_VHOST', '/'),
            'exchange' => env('RABBITMQ_EXCHANGE', 'realtime'),
            'exchange_type' => env('RABBITMQ_EXCHANGE_TYPE', 'topic'),
            'exchange_durable' => env('RABBITMQ_EXCHANGE_DURABLE', true),
            'exchange_auto_delete' => env('RABBITMQ_EXCHANGE_AUTO_DELETE', false),
            'queue_prefix' => env('RABBITMQ_QUEUE_PREFIX', 'realtime'),
            'queue_durable' => env('RABBITMQ_QUEUE_DURABLE', false),
            'queue_exclusive' => env('RABBITMQ_QUEUE_EXCLUSIVE', true),
            'queue_auto_delete' => env('RABBITMQ_QUEUE_AUTO_DELETE', true),
            'prefetch_count' => env('RABBITMQ_PREFETCH_COUNT', 50),
            'persistent_messages' => env('RABBITMQ_PERSISTENT_MESSAGES', true),
        ],

        'nats' => [
            'driver' => 'nats',
            'url' => env('NATS_URL', 'nats://localhost:4222'),
        ],

        'postgres' => [
            'driver' => 'postgres',
            // Uses existing database connection
        ],

        'kafka' => [
            'driver' => 'kafka',
            'client' => env('KAFKA_CLIENT', 'auto'), // php, rdkafka, auto (auto = prefer rdkafka)
            'brokers' => explode(',', env('KAFKA_BROKERS', 'localhost:9092')),
            'topic_prefix' => env('KAFKA_TOPIC_PREFIX', 'realtime'),
            'consumer_group' => env('KAFKA_CONSUMER_GROUP', 'realtime-servers'),

            // Topic Strategy: 'one-per-channel' (legacy) or 'grouped' (recommended)
            'topic_strategy' => env('KAFKA_TOPIC_STRATEGY', 'grouped'),

            // Topic Mapping (for grouped strategy)
            // Load directly from kafka.php config file
            'topic_mapping' => (function () {
                $kafkaConfig = @include __DIR__ . '/kafka.php';
                return $kafkaConfig['topic_mapping'] ?? [];
            })(),
            'default_topic' => env('KAFKA_DEFAULT_TOPIC', 'realtime'),
            'default_partitions' => (int) env('KAFKA_DEFAULT_PARTITIONS', 10),

            // Manual Commit (recommended for production)
            'manual_commit' => env('KAFKA_MANUAL_COMMIT', false),

            // Performance optimizations
            'buffer_size' => (int) env('KAFKA_BUFFER_SIZE', 100), // Messages per batch
            'flush_interval_ms' => (int) env('KAFKA_FLUSH_INTERVAL_MS', 100), // Flush every 100ms

            // Producer configuration (rdkafka format)
            // Optimized defaults for high performance
            'producer_config' => [
                // Security protocol - must match Kafka listener configuration
                'security.protocol' => env('KAFKA_SECURITY_PROTOCOL', 'plaintext'),
                // Leave compression unset by default for maximal compatibility.
                // Set KAFKA_COMPRESSION to gzip/lz4 if your client supports it.
                'compression.type' => env('KAFKA_COMPRESSION', ''),
                'batch.size' => env('KAFKA_BATCH_SIZE', '16384'), // 16KB
                'linger.ms' => env('KAFKA_LINGER_MS', '10'), // Wait 10ms for batch
                'acks' => env('KAFKA_ACKS', '1'), // 1 = leader ack (balance between speed and durability)
                'max.in.flight.requests.per.connection' => env('KAFKA_MAX_IN_FLIGHT', '5'), // Parallel requests
            ],

            // Consumer configuration (rdkafka format)
            'consumer_config' => [
                'auto.offset.reset' => 'earliest',
                'session.timeout.ms' => '30000',
                'max.poll.interval.ms' => '300000',
                'fetch.min.bytes' => env('KAFKA_FETCH_MIN_BYTES', '1024'), // Min bytes per fetch
                'fetch.wait.max.ms' => env('KAFKA_FETCH_MAX_WAIT_MS', '500'), // Max wait time
                'max.partition.fetch.bytes' => env('KAFKA_MAX_PARTITION_FETCH_BYTES', '1048576'), // 1MB per partition
                // Security protocol - must match Kafka listener configuration
                'security.protocol' => env('KAFKA_SECURITY_PROTOCOL', 'plaintext'),
                // Metadata refresh settings to avoid "Unknown topic or partition" errors
                'metadata.max.age.ms' => env('KAFKA_METADATA_MAX_AGE_MS', '300000'), // 5 minutes
                'topic.metadata.refresh.interval.ms' => env('KAFKA_TOPIC_METADATA_REFRESH_MS', '300000'), // 5 minutes
                // Note: metadata.request.timeout.ms is deprecated in rdkafka and not used
            ],
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
