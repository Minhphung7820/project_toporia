<?php

declare(strict_types=1);

/**
 * Search / Elasticsearch Configuration
 */

return [
    'default' => env('SEARCH_DRIVER', 'elasticsearch'),

    'connections' => [
        'elasticsearch' => [
            'driver' => 'elasticsearch',
            'hosts' => explode(',', env('ELASTICSEARCH_HOSTS', 'http://localhost:9200')),
            'username' => env('ELASTICSEARCH_USERNAME'),
            'password' => env('ELASTICSEARCH_PASSWORD'),
            'api_key' => env('ELASTICSEARCH_API_KEY'),
            'retries' => (int) env('ELASTICSEARCH_RETRIES', 2),
            'ssl_verification' => env('ELASTICSEARCH_SSL_VERIFY', true),
            'request_timeout' => (float) env('ELASTICSEARCH_REQUEST_TIMEOUT', 2.0),
        ],
    ],

    'bulk' => [
        'batch_size' => (int) env('SEARCH_BULK_BATCH_SIZE', 500),
        'flush_interval_ms' => (int) env('SEARCH_BULK_FLUSH_INTERVAL_MS', 1000),
    ],

    'queue' => [
        'enabled' => env('SEARCH_QUEUE_ENABLED', false),
        'connection' => env('SEARCH_QUEUE_CONNECTION', env('QUEUE_CONNECTION', 'redis')),
        'queue' => env('SEARCH_QUEUE', 'search-sync'),
        'tries' => (int) env('SEARCH_QUEUE_TRIES', 5),
        'backoff' => (int) env('SEARCH_QUEUE_BACKOFF', 5),
    ],

    'indices' => [
        'products' => [
            'name' => env('SEARCH_INDEX_PRODUCTS', 'products'),
            'settings' => [
                'number_of_shards' => (int) env('SEARCH_PRODUCTS_SHARDS', 1),
                'number_of_replicas' => (int) env('SEARCH_PRODUCTS_REPLICAS', 0),
                'analysis' => [
                    'analyzer' => [
                        'default' => [
                            'type' => 'custom',
                            'tokenizer' => 'standard',
                            'filter' => ['lowercase', 'asciifolding'],
                        ],
                    ],
                ],
            ],
            'mappings' => [
                'properties' => [
                    'id' => ['type' => 'keyword'],
                    'title' => ['type' => 'text', 'analyzer' => 'standard'],
                    'description' => ['type' => 'text'],
                    'price' => ['type' => 'scaled_float', 'scaling_factor' => 100],
                    'status' => ['type' => 'keyword'],
                    'created_at' => ['type' => 'date'],
                    'updated_at' => ['type' => 'date'],
                ],
            ],
        ],
    ],
];

