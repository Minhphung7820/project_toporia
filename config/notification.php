<?php

declare(strict_types=1);

/**
 * Notification Configuration
 *
 * Multi-channel notification system configuration.
 * Supports: Mail, Database, SMS (Twilio/Nexmo), Slack
 *
 * Performance Tips:
 * - Use queue for async delivery (set notification->onQueue())
 * - Enable database channel for in-app notifications
 * - Use SMS sparingly (expensive)
 * - Batch Slack notifications to reduce API calls
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Default Notification Channel
    |--------------------------------------------------------------------------
    |
    | Default channel used when no channels specified.
    | Options: 'mail', 'database', 'sms', 'slack'
    |
    */
    'default' => env('NOTIFICATION_CHANNEL', 'mail'),

    /*
    |--------------------------------------------------------------------------
    | Notification Channels
    |--------------------------------------------------------------------------
    |
    | Configure available notification channels.
    | Each channel requires specific configuration.
    |
    */
    'channels' => [
        'mail' => [
            'driver' => 'mail',
            // Uses MailManager from container
        ],

        'database' => [
            'driver' => 'database',
            'table' => 'notifications',
        ],

        'sms' => [
            'driver' => 'sms',
            'provider' => env('SMS_PROVIDER', 'twilio'), // twilio, nexmo, aws_sns

            // Twilio Configuration
            'account_sid' => env('TWILIO_SID'),
            'auth_token' => env('TWILIO_TOKEN'),
            'from' => env('TWILIO_FROM'),

            // Nexmo Configuration
            // 'api_key' => env('NEXMO_KEY'),
            // 'api_secret' => env('NEXMO_SECRET'),
            // 'from' => env('NEXMO_FROM'),
        ],

        'slack' => [
            'driver' => 'slack',
            'webhook_url' => env('SLACK_WEBHOOK_URL'),
        ],

        'broadcast' => [
            'driver' => 'broadcast',
            // Uses RealtimeManager from container
            // Sends notifications via WebSocket/SSE to connected clients
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Queue
    |--------------------------------------------------------------------------
    |
    | Queue configuration for async notification delivery.
    | Recommended for production to avoid blocking requests.
    |
    */
    'queue' => [
        'enabled' => env('NOTIFICATION_QUEUE_ENABLED', true),
        'connection' => env('NOTIFICATION_QUEUE_CONNECTION', 'redis'),
        'queue' => env('NOTIFICATION_QUEUE_NAME', 'notifications'),
    ],
];
