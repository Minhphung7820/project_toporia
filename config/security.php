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
    | Replay Attack Protection
    |--------------------------------------------------------------------------
    |
    | Prevent replay attacks by validating nonces (Number Used Once).
    | Each request must include a unique nonce that expires after a set time.
    |
    */
    'replay' => [
        'enabled' => true,
        'nonce_ttl' => 300, // 5 minutes in seconds
        'nonce_field' => '_nonce',
        'cleanup_probability' => 100, // Cleanup 1 in 100 requests (1%)
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

    /*
    |--------------------------------------------------------------------------
    | CORS (Cross-Origin Resource Sharing)
    |--------------------------------------------------------------------------
    |
    | Configure CORS headers to allow cross-origin requests.
    | Useful for API endpoints accessed from different domains.
    |
    | Options:
    | - allowed_origins: Array of allowed origins (use '*' for all)
    | - allowed_methods: HTTP methods allowed (default: GET, POST, PUT, PATCH, DELETE, OPTIONS)
    | - allowed_headers: Headers allowed in requests
    | - exposed_headers: Headers exposed to client
    | - credentials: Allow credentials (cookies, authorization headers)
    | - max_age: Preflight cache duration in seconds (default: 3600 = 1 hour)
    |
    */
    'cors' => [
        'enabled' => true,
        'allowed_origins' => [
            // Add your allowed origins here
            // '*',  // Allow all origins (not recommended for production)
            // 'https://example.com',
            // 'https://*.example.com',  // Pattern matching
        ],
        'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
        'allowed_headers' => [
            'Content-Type',
            'Authorization',
            'X-Requested-With',
            'X-CSRF-TOKEN',
            'X-Replay-Nonce',
            'Accept',
            'Origin',
        ],
        'exposed_headers' => [
            // Headers that client can access via getResponseHeader()
        ],
        'credentials' => false, // Set to true to allow cookies/credentials
        'max_age' => 3600, // 1 hour preflight cache
    ],
];
