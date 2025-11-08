<?php

declare(strict_types=1);

/**
 * Mail Configuration
 *
 * Configure email sending settings.
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Default Mailer
    |--------------------------------------------------------------------------
    |
    | This option controls the default mailer that is used to send any email
    | messages. You may specify which mailer you wish to use as your default.
    |
    */

    'default' => env('MAIL_MAILER', 'smtp'),

    /*
    |--------------------------------------------------------------------------
    | Mailer Configurations
    |--------------------------------------------------------------------------
    |
    | Configure different mailers for different purposes.
    |
    */

    'mailers' => [
        'smtp' => [
            'transport' => 'smtp',
            'host' => env('MAIL_HOST', 'smtp.mailtrap.io'),
            'port' => env('MAIL_PORT', 2525),
            'encryption' => env('MAIL_ENCRYPTION', 'tls'),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => 30,
        ],

        'sendmail' => [
            'transport' => 'sendmail',
            'path' => env('MAIL_SENDMAIL_PATH', '/usr/sbin/sendmail -bs'),
        ],

        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL', 'mail'),
        ],

        'array' => [
            'transport' => 'array',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Global "From" Address
    |--------------------------------------------------------------------------
    |
    | You may wish to have all emails sent from your application to be sent
    | from the same address. Specify it here.
    |
    */

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'noreply@example.com'),
        'name' => env('MAIL_FROM_NAME', 'My Application'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Connection
    |--------------------------------------------------------------------------
    |
    | Queue connection to use for sending emails asynchronously.
    |
    */

    'queue' => [
        'connection' => env('MAIL_QUEUE_CONNECTION', 'default'),
        'queue' => 'emails',
    ],
];
