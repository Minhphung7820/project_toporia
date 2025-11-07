<?php

namespace App\Providers;

use Framework\Events\Dispatcher;

final class EventServiceProvider
{
    public static function register(Dispatcher $events): void
    {
        $events->listen('UserLoggedIn', function (array $payload) {
            error_log('[Login] ' . ($payload['email'] ?? 'unknown'));
        });
        $events->listen('ProductCreated', function (array $payload) {
            error_log('[ProductCreated] ' . json_encode($payload));
        });
    }
}
