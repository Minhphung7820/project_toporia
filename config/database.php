<?php

declare(strict_types=1);

/**
 * Database Configuration
 *
 * Configure database connections here.
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Default Database Connection
    |--------------------------------------------------------------------------
    */
    'default' => env('DB_CONNECTION', 'mysql'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Configure your database connections here.
    | Supported drivers: mysql, pgsql, sqlite
    |
    */
    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', 3306),
            'database' => env('DB_NAME', 'project_topo'),
            'username' => env('DB_USER', 'root'),
            'password' => env('DB_PASS', ''),
            'charset' => 'utf8mb4',
        ],

        'pgsql' => [
            'driver' => 'pgsql',
            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', 5432),
            'database' => env('DB_NAME', 'project_topo'),
            'username' => env('DB_USER', 'postgres'),
            'password' => env('DB_PASS', ''),
            'charset' => 'utf8',
        ],

        'sqlite' => [
            'driver' => 'sqlite',
            'database' => env('DB_DATABASE', 'database/database.sqlite'),
        ],
    ],
];
