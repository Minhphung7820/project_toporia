<?php

declare(strict_types=1);

/**
 * Security Configuration
 *
 * Configure security features including CSRF, XSS protection, and security headers.
 */

return [
    /*
    |--------------------------------------------------------------------------
    | CSRF Protection
    |--------------------------------------------------------------------------
    |
    | Enable/disable CSRF protection globally.
    | When enabled, all state-changing requests must include a valid CSRF token.
    |
    */
    'csrf' => [
        'enabled' => true,
        'token_name' => '_token',
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Headers
    |--------------------------------------------------------------------------
    |
    | Configure HTTP security headers to prevent common vulnerabilities.
    |
    */
    'headers' => [
        'x_content_type_options' => true,
        'x_frame_options' => 'SAMEORIGIN', // DENY, SAMEORIGIN, or false
        'x_xss_protection' => true,
        'hsts' => env('APP_ENV') === 'production',
        'hsts_max_age' => 31536000, // 1 year
        'hsts_include_subdomains' => false,
        'hsts_preload' => false,
        'csp' => "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline';",
        'referrer_policy' => 'strict-origin-when-cross-origin',
        'permissions_policy' => 'geolocation=(), microphone=(), camera=()',
    ],

    /*
    |--------------------------------------------------------------------------
    | Cookie Security
    |--------------------------------------------------------------------------
    |
    | Default security settings for cookies.
    |
    */
    'cookie' => [
        'encryption_key' => env('APP_KEY'),
        'secure' => env('APP_ENV') === 'production', // HTTPS only in production
        'http_only' => true,
        'same_site' => 'Lax', // Lax, Strict, None
        'path' => '/',
        'domain' => env('SESSION_DOMAIN', ''),
    ],
];
