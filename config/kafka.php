<?php

declare(strict_types=1);

/**
 * Kafka Configuration
 *
 * Configuration for Kafka consumers and producers.
 * Supports JSON and Avro message formats with batch processing and DLQ.
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Default Offset Reset
    |--------------------------------------------------------------------------
    |
    | Default offset reset strategy for consumers.
    | Options: 'earliest', 'latest', 'none'
    |
    */
    'offset_reset' => env('KAFKA_OFFSET_RESET', 'earliest'),

    /*
    |--------------------------------------------------------------------------
    | Schema Registry
    |--------------------------------------------------------------------------
    |
    | Configuration for Avro Schema Registry.
    |
    */
    'schema_registry' => [
        'uri' => env('KAFKA_SCHEMA_REGISTRY_URI', 'http://localhost:8000'),
        'cache_enabled' => env('KAFKA_SCHEMA_CACHE_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Batch Processing
    |--------------------------------------------------------------------------
    |
    | Default batch processing configuration.
    |
    */
    'batch_release_interval' => (int) env('KAFKA_BATCH_RELEASE_INTERVAL', 1500), // milliseconds

    /*
    |--------------------------------------------------------------------------
    | Dead Letter Queue (DLQ)
    |--------------------------------------------------------------------------
    |
    | Configuration for Dead Letter Queue handling.
    |
    */
    'dlq_topic_prefix' => env('KAFKA_DLQ_TOPIC_PREFIX', 'dlq'),
    'dlq_max_retries' => (int) env('KAFKA_DLQ_MAX_RETRIES', 3),
    'dlq_enabled' => env('KAFKA_DLQ_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Consumer Topics
    |--------------------------------------------------------------------------
    |
    | Topic names for different consumer types.
    | Can be overridden in individual consumer classes.
    |
    */
    'topics' => [
        'json' => env('KAFKA_TOPIC_JSON', 'realtime-json'),
        'avro' => env('KAFKA_TOPIC_AVRO', 'realtime-avro'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Consumer Group IDs
    |--------------------------------------------------------------------------
    |
    | Consumer group IDs for different consumer types.
    |
    */
    'consumer_group_id' => [
        'json' => env('KAFKA_CONSUMER_GROUP_JSON', 'realtime-json-consumers'),
        'avro' => env('KAFKA_CONSUMER_GROUP_AVRO', 'realtime-avro-consumers'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Offset Reset by Consumer Type
    |--------------------------------------------------------------------------
    |
    | Offset reset strategy for different consumer types.
    |
    */
    'offset_reset_by_type' => [
        'json' => env('KAFKA_OFFSET_RESET_JSON', 'earliest'),
        'avro' => env('KAFKA_OFFSET_RESET_AVRO', 'earliest'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Avro Schema Names
    |--------------------------------------------------------------------------
    |
    | Avro schema names for different topics.
    |
    */
    'schema_avro' => [
        'default' => env('KAFKA_SCHEMA_AVRO_DEFAULT', 'com.toporia.realtime.Message'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Topic Strategy
    |--------------------------------------------------------------------------
    |
    | Strategy for mapping channels to Kafka topics and partitions.
    | Options: 'one-per-channel', 'grouped'
    |
    | - 'one-per-channel': Each channel = 1 topic (legacy, for small scale)
    | - 'grouped': Groups channels into fewer topics with partitioning (recommended)
    |
    */
    'topic_strategy' => env('KAFKA_TOPIC_STRATEGY', 'grouped'),

    /*
    |--------------------------------------------------------------------------
    | Topic Mapping (for grouped strategy)
    |--------------------------------------------------------------------------
    |
    | Channel pattern → topic configuration.
    | Used when topic_strategy = 'grouped'
    |
    | Example:
    |   'user.*' => ['topic' => 'realtime.user', 'partitions' => 10]
    |   'public.*' => ['topic' => 'realtime.public', 'partitions' => 3]
    |
    */
    'topic_mapping' => [
        'orders.*' => [
            'topic' => env('KAFKA_TOPIC_ORDERS', 'orders.events'),
            'partitions' => (int) env('KAFKA_TOPIC_ORDERS_PARTITIONS', 10),
        ],
        'user.*' => [
            'topic' => env('KAFKA_TOPIC_USER', 'realtime.user'),
            'partitions' => (int) env('KAFKA_TOPIC_USER_PARTITIONS', 10),
        ],
        'public.*' => [
            'topic' => env('KAFKA_TOPIC_PUBLIC', 'realtime.public'),
            'partitions' => (int) env('KAFKA_TOPIC_PUBLIC_PARTITIONS', 3),
        ],
        'presence-*' => [
            'topic' => env('KAFKA_TOPIC_PRESENCE', 'realtime.presence'),
            'partitions' => (int) env('KAFKA_TOPIC_PRESENCE_PARTITIONS', 5),
        ],
        'chat.*' => [
            'topic' => env('KAFKA_TOPIC_CHAT', 'realtime.chat'),
            'partitions' => (int) env('KAFKA_TOPIC_CHAT_PARTITIONS', 10),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Topic (for grouped strategy)
    |--------------------------------------------------------------------------
    |
    | Default topic name for channels that don't match any pattern.
    |
    */
    'default_topic' => env('KAFKA_DEFAULT_TOPIC', 'realtime'),
    'default_partitions' => (int) env('KAFKA_DEFAULT_PARTITIONS', 10),

    /*
    |--------------------------------------------------------------------------
    | Manual Commit
    |--------------------------------------------------------------------------
    |
    | Whether to use manual commit for better reliability.
    | When enabled, messages are only committed after successful processing.
    |
    | Recommended: true for production (prevents message loss)
    |
    */
    'manual_commit' => env('KAFKA_MANUAL_COMMIT', false),
];
