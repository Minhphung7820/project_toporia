<?php

declare(strict_types=1);

/**
 * Hashing Configuration
 *
 * Password hashing configuration for secure password storage.
 * Supports multiple algorithms with configurable parameters.
 *
 * Recommended Settings:
 * - Production: argon2id (best security, requires PHP 7.3+)
 * - Legacy: bcrypt (widely supported, PHP 5.5+)
 * - Development: bcrypt with lower cost (faster)
 *
 * Security Notes:
 * - NEVER use MD5, SHA1, or plain text
 * - Higher cost = more secure but slower
 * - Adjust cost based on server performance
 * - Re-hash passwords when upgrading algorithms
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Default Hash Driver
    |--------------------------------------------------------------------------
    |
    | Default hashing algorithm used for new passwords.
    |
    | Supported: "bcrypt", "argon2id"
    |
    | Recommendation:
    | - argon2id: Modern, most secure (PHP 7.3+)
    | - bcrypt: Standard, widely supported
    |
    */
    'driver' => env('HASH_DRIVER', 'bcrypt'),

    /*
    |--------------------------------------------------------------------------
    | Hash Driver Configurations
    |--------------------------------------------------------------------------
    |
    | Configuration options for each hashing driver.
    | Adjust parameters based on your security requirements and server resources.
    |
    */
    'drivers' => [
        /*
        |----------------------------------------------------------------------
        | Bcrypt Configuration
        |----------------------------------------------------------------------
        |
        | Bcrypt uses the Blowfish cipher with configurable cost factor.
        |
        | Options:
        | - cost: Work factor (4-31, default: 12)
        |   - 10 = ~100ms per hash
        |   - 12 = ~250ms per hash (recommended)
        |   - 14 = ~1000ms per hash
        |
        | Higher cost = more secure but slower.
        | Recommended: 12 for production, 10 for development.
        |
        */
        'bcrypt' => [
            'cost' => env('BCRYPT_COST', 12),
        ],

        /*
        |----------------------------------------------------------------------
        | Argon2id Configuration
        |----------------------------------------------------------------------
        |
        | Argon2id is the modern, memory-hard hashing algorithm.
        | Winner of Password Hashing Competition (2015).
        | Requires PHP 7.3+ compiled with Argon2 support.
        |
        | Options:
        | - memory: Memory cost in KB (default: 65536 = 64 MB)
        | - time: Time cost in iterations (default: 4)
        | - threads: Parallel thread count (default: 1)
        |
        | Memory-hard: Resistant to GPU/ASIC attacks
        | Time-cost: Number of iterations
        | Threads: Parallelism (use 1 for most applications)
        |
        | Recommended Production:
        | - memory: 65536 (64 MB)
        | - time: 4 iterations
        | - threads: 1
        |
        | High Security:
        | - memory: 131072 (128 MB)
        | - time: 6 iterations
        | - threads: 1
        |
        */
        'argon2id' => [
            'memory' => env('ARGON2_MEMORY', 65536), // 64 MB
            'time' => env('ARGON2_TIME', 4),
            'threads' => env('ARGON2_THREADS', 1),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Algorithm Migration
    |--------------------------------------------------------------------------
    |
    | Automatically rehash passwords when algorithm or parameters change.
    | Set to true to enable automatic migration.
    |
    | When enabled:
    | - Old hashes are detected on login
    | - Password is rehashed with new algorithm
    | - Database is updated automatically
    |
    | Recommended: true (allows security upgrades)
    |
    */
    'auto_rehash' => env('HASH_AUTO_REHASH', true),

    /*
    |--------------------------------------------------------------------------
    | Performance Tuning
    |--------------------------------------------------------------------------
    |
    | Adjust these settings based on your server capabilities.
    |
    | Benchmarking:
    | Run `php console hash:benchmark` to test different settings.
    | Target: 200-500ms per hash (good balance)
    |
    | Production Guidelines:
    | - Shared hosting: bcrypt cost 10-11
    | - VPS: bcrypt cost 12, argon2id default
    | - Dedicated: bcrypt cost 13+, argon2id with high memory
    |
    */
];
