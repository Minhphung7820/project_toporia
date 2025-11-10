<?php

declare(strict_types=1);

/**
 * Filesystem Configuration
 *
 * Configure storage disks for file uploads and storage.
 * Laravel-compatible configuration.
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Configure as many filesystem "disks" as you wish, and you may even
    | configure multiple disks of the same driver. Defaults have been set
    | up for each driver as an example of the required values.
    |
    */

    'disks' => [
        'local' => [
            'driver' => 'local',
            'root' => __DIR__ . '/../storage/app',
            'url' => env('APP_URL', 'http://localhost') . '/storage',
        ],

        'public' => [
            'driver' => 'local',
            'root' => __DIR__ . '/../storage/app/public',
            'url' => env('APP_URL', 'http://localhost') . '/storage',
        ],

        'uploads' => [
            'driver' => 'local',
            'root' => __DIR__ . '/../storage/app/uploads',
            'url' => env('APP_URL', 'http://localhost') . '/storage/uploads',
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID', ''),
            'secret' => env('AWS_SECRET_ACCESS_KEY', ''),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'bucket' => env('AWS_BUCKET', ''),
            'url' => env('AWS_URL', ''),
            'endpoint' => env('AWS_ENDPOINT', ''),
        ],

        // DigitalOcean Spaces (S3-compatible)
        'spaces' => [
            'driver' => 's3',
            'key' => env('DO_SPACES_KEY', ''),
            'secret' => env('DO_SPACES_SECRET', ''),
            'region' => env('DO_SPACES_REGION', 'nyc3'),
            'bucket' => env('DO_SPACES_BUCKET', ''),
            'endpoint' => env('DO_SPACES_ENDPOINT', 'https://nyc3.digitaloceanspaces.com'),
            'url' => env('DO_SPACES_URL', ''),
        ],

        // Minio (S3-compatible)
        'minio' => [
            'driver' => 's3',
            'key' => env('MINIO_KEY', 'minioadmin'),
            'secret' => env('MINIO_SECRET', 'minioadmin'),
            'region' => env('MINIO_REGION', 'us-east-1'),
            'bucket' => env('MINIO_BUCKET', 'default'),
            'endpoint' => env('MINIO_ENDPOINT', 'http://localhost:9000'),
            'url' => env('MINIO_URL', 'http://localhost:9000'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` command is executed. The array keys should be the
    | locations of the links and the values should be their targets.
    |
    */

    'links' => [
        __DIR__ . '/../public/storage' => __DIR__ . '/../storage/app/public',
    ],
];
