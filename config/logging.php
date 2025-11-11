<?php

/**
 * Logging Configuration
 *
 * Configure log channels and drivers.
 * Supports: single, daily, stack, syslog, stderr
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Default Log Channel
    |--------------------------------------------------------------------------
    |
    | This option defines the default log channel that gets used when writing
    | messages to the logs. The name specified in this option should match
    | one of the channels defined in the "channels" configuration array.
    |
    */

    'default' => env('LOG_CHANNEL', 'daily'),

    /*
    |--------------------------------------------------------------------------
    | Log Channels
    |--------------------------------------------------------------------------
    |
    | Here you may configure the log channels for your application.
    |
    | Available Drivers: "single", "daily", "stack", "syslog", "stderr"
    |
    */

    'channels' => [
        /**
         * Stack Channel - Write to multiple channels
         *
         * Useful for production: write to both daily file and syslog.
         */
        'stack' => [
            'driver' => 'stack',
            'channels' => ['daily', 'syslog'],
        ],

        /**
         * Single File Channel - One log file for everything
         *
         * Simple approach, good for development.
         * File: storage/logs/app.log
         */
        'single' => [
            'driver' => 'single',
            'path' => __DIR__ . '/../storage/logs/app.log',
            'date_format' => 'Y-m-d H:i:s',
        ],

        /**
         * Daily Rotating Files - New file each day
         *
         * Recommended for production.
         * Creates: storage/logs/2025-01-11.log, 2025-01-12.log, etc.
         *
         * Auto-cleanup after N days.
         */
        'daily' => [
            'driver' => 'daily',
            'path' => __DIR__ . '/../storage/logs',
            'date_format' => 'Y-m-d H:i:s',
            'days' => 14, // Keep logs for 14 days (null = keep all)
        ],

        /**
         * Syslog Channel - System logger
         *
         * Writes to system syslog daemon.
         * Logs appear in /var/log/syslog (Linux) or /var/log/system.log (macOS).
         */
        'syslog' => [
            'driver' => 'syslog',
            'ident' => env('APP_NAME', 'toporia'),
            'facility' => LOG_USER,
        ],

        /**
         * Stderr Channel - Standard error output
         *
         * Writes to STDERR stream.
         * Useful for Docker containers and CLI tools.
         */
        'stderr' => [
            'driver' => 'stderr',
            'date_format' => 'Y-m-d H:i:s',
        ],
    ],
];
