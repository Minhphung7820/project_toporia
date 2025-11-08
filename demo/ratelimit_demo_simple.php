<?php

declare(strict_types=1);

/**
 * Rate Limiting Demo - Simplified
 */

require __DIR__ . '/../vendor/autoload.php';

use Toporia\Framework\Cache\MemoryCache;
use Toporia\Framework\RateLimit\CacheRateLimiter;

echo "=== Rate Limiting Demo ===\n\n";

// Create cache and rate limiter
$cache = new MemoryCache();
$limiter = new CacheRateLimiter($cache);

// ============================================================================
// 1. Basic Rate Limiting
// ============================================================================

echo "1. Basic Rate Limiting (5 requests per 60 seconds):\n";
echo "   " . str_repeat("-", 60) . "\n";

$key = 'user:123';
$maxAttempts = 5;
$decaySeconds = 60;

for ($i = 1; $i <= 7; $i++) {
    $allowed = $limiter->attempt($key, $maxAttempts, $decaySeconds);
    $remaining = $limiter->remaining($key, $maxAttempts);

    echo "   Request #{$i}: ";

    if ($allowed) {
        echo "✓ ALLOWED (Remaining: {$remaining}/{$maxAttempts})\n";
    } else {
        $retryAfter = $limiter->availableIn($key);
        echo "✗ BLOCKED (Retry after {$retryAfter}s)\n";
    }
}

echo "\n";

// ============================================================================
// 2. Different Rate Limit Strategies
// ============================================================================

echo "2. Different Rate Limit Strategies:\n";
echo "   " . str_repeat("-", 60) . "\n\n";

$strategies = [
    [
        'key' => 'user:456:login',
        'description' => 'Login attempts for user 456',
        'limit' => 5,
        'decay' => 900, // 15 minutes
    ],
    [
        'key' => 'ip:192.168.1.1:api',
        'description' => 'API requests from IP 192.168.1.1',
        'limit' => 60,
        'decay' => 60, // 1 minute
    ],
    [
        'key' => 'global:contact-form',
        'description' => 'Contact form submissions',
        'limit' => 2,
        'decay' => 3600, // 1 hour
    ],
];

foreach ($strategies as $strategy) {
    $allowed = $limiter->attempt($strategy['key'], $strategy['limit'], $strategy['decay']);
    $remaining = $limiter->remaining($strategy['key'], $strategy['limit']);

    echo "   {$strategy['description']}:\n";
    echo "     Key: {$strategy['key']}\n";
    echo "     Limit: {$strategy['limit']} per {$strategy['decay']}s\n";
    echo "     Status: " . ($allowed ? '✓ Allowed' : '✗ Blocked') . "\n";
    echo "     Remaining: {$remaining}/{$strategy['limit']}\n\n";
}

// ============================================================================
// 3. Check without incrementing
// ============================================================================

echo "3. Check Rate Limit Status (without incrementing):\n";
echo "   " . str_repeat("-", 60) . "\n";

$testKey = 'user:789';
$limit = 10;

// First make some attempts
for ($i = 1; $i <= 3; $i++) {
    $limiter->attempt($testKey, $limit, 60);
}

// Now just check status
$tooMany = $limiter->tooManyAttempts($testKey, $limit);
$remaining = $limiter->remaining($testKey, $limit);
$availableIn = $limiter->availableIn($testKey);

echo "   Key: {$testKey}\n";
echo "   Limit: {$limit}\n";
echo "   Too many attempts: " . ($tooMany ? '✓ Yes' : '✗ No') . "\n";
echo "   Remaining: {$remaining}/{$limit}\n";
echo "   Available in: {$availableIn}s\n\n";

// ============================================================================
// 4. Clear Rate Limits
// ============================================================================

echo "4. Clear Rate Limits:\n";
echo "   " . str_repeat("-", 60) . "\n";

$clearKey = 'user:999';
$limiter->attempt($clearKey, 5, 60);
$limiter->attempt($clearKey, 5, 60);
$limiter->attempt($clearKey, 5, 60);

echo "   Before clear: {$limiter->remaining($clearKey, 5)}/5 remaining\n";

$limiter->clear($clearKey);

echo "   After clear:  {$limiter->remaining($clearKey, 5)}/5 remaining\n\n";

// ============================================================================
// 5. Practical Examples
// ============================================================================

echo "5. Practical Usage Examples:\n";
echo "   " . str_repeat("=", 60) . "\n\n";

echo "   A) Login Rate Limiting:\n";
echo "   " . str_repeat("-", 60) . "\n";
echo "   \$email = \$request->input('email');\n";
echo "   \$key = \"login:{\$email}\";\n";
echo "   \n";
echo "   if (\$limiter->tooManyAttempts(\$key, 5)) {\n";
echo "       \$seconds = \$limiter->availableIn(\$key);\n";
echo "       return \$response->json([\n";
echo "           'error' => \"Too many attempts. Retry in {\$seconds}s\"\n";
echo "       ], 429);\n";
echo "   }\n";
echo "   \n";
echo "   \$limiter->attempt(\$key, 5, 900); // 5 attempts per 15 min\n\n";

echo "   B) API Rate Limiting by User:\n";
echo "   " . str_repeat("-", 60) . "\n";
echo "   \$userId = auth()->guard()->id();\n";
echo "   \$key = \"api:user:{\$userId}\";\n";
echo "   \n";
echo "   if (!\$limiter->attempt(\$key, 100, 60)) {\n";
echo "       return \$response->json([\n";
echo "           'error' => 'Rate limit exceeded',\n";
echo "           'retry_after' => \$limiter->availableIn(\$key)\n";
echo "       ], 429);\n";
echo "   }\n\n";

echo "   C) API Rate Limiting by IP (guest users):\n";
echo "   " . str_repeat("-", 60) . "\n";
echo "   \$ip = \$request->ip();\n";
echo "   \$key = \"api:ip:{\$ip}\";\n";
echo "   \n";
echo "   if (!\$limiter->attempt(\$key, 20, 60)) {\n";
echo "       return \$response->json([\n";
echo "           'error' => 'Rate limit exceeded for guest users'\n";
echo "       ], 429);\n";
echo "   }\n\n";

// ============================================================================
// 6. Middleware Usage
// ============================================================================

echo "6. Middleware Usage in Routes:\n";
echo "   " . str_repeat("=", 60) . "\n\n";

echo "   // config/middleware.php\n";
echo "   'aliases' => [\n";
echo "       'throttle' => ThrottleRequests::class,\n";
echo "   ]\n\n";

echo "   // routes/web.php\n\n";

echo "   a) Login endpoint (5 attempts per 15 minutes):\n";
echo "      \$router->post('/login', [AuthController::class, 'login'])\n";
echo "          ->middleware(['throttle:5,15']);\n\n";

echo "   b) API endpoint (60 requests per minute):\n";
echo "      \$router->get('/api/users', [ApiController::class, 'users'])\n";
echo "          ->middleware(['throttle:60,1']);\n\n";

echo "   c) Contact form (2 submissions per hour):\n";
echo "      \$router->post('/contact', [ContactController::class, 'submit'])\n";
echo "          ->middleware(['throttle:2,60']);\n\n";

echo "   d) Download endpoint (10 downloads per day):\n";
echo "      \$router->get('/download/{file}', [DownloadController::class, 'file'])\n";
echo "          ->middleware(['throttle:10,1440']);\n\n";

// ============================================================================
// 7. Response Headers
// ============================================================================

echo "7. Response Headers (added by ThrottleRequests middleware):\n";
echo "   " . str_repeat("=", 60) . "\n\n";

echo "   X-RateLimit-Limit: 60          (max requests allowed)\n";
echo "   X-RateLimit-Remaining: 45      (requests left)\n";
echo "   Retry-After: 30                (seconds until retry, when blocked)\n\n";

// ============================================================================
// 8. Cache Drivers Comparison
// ============================================================================

echo "8. Cache Drivers for Rate Limiting:\n";
echo "   " . str_repeat("=", 60) . "\n\n";

echo "   a) MemoryCache (current demo):\n";
echo "      ✓ Good for: Testing, single-process apps\n";
echo "      ✗ Limitation: Not shared between requests\n\n";

echo "   b) FileCache:\n";
echo "      ✓ Good for: Simple apps, shared hosting\n";
echo "      ✗ Limitation: Slower than Redis, file I/O overhead\n";
echo "      Usage:\n";
echo "        \$cache = new FileCache(__DIR__ . '/storage/cache');\n";
echo "        \$limiter = new CacheRateLimiter(\$cache);\n\n";

echo "   c) RedisCache (RECOMMENDED for production):\n";
echo "      ✓ Good for: High-traffic apps, multiple servers\n";
echo "      ✓ Benefits: Fast, atomic operations, shared state\n";
echo "      Usage:\n";
echo "        \$cache = RedisCache::fromConfig([\n";
echo "            'host' => '127.0.0.1',\n";
echo "            'port' => 6379\n";
echo "        ]);\n";
echo "        \$limiter = new CacheRateLimiter(\$cache);\n\n";

// ============================================================================
// 9. Best Practices
// ============================================================================

echo "9. Best Practices:\n";
echo "   " . str_repeat("=", 60) . "\n\n";

echo "   ✓ Use Redis cache in production for accuracy\n";
echo "   ✓ Set different limits based on user type:\n";
echo "     - Guests: Lower limits (e.g., 20 req/min)\n";
echo "     - Authenticated: Higher limits (e.g., 100 req/min)\n";
echo "     - Premium users: Even higher (e.g., 1000 req/min)\n";
echo "   ✓ Lower limits for sensitive operations:\n";
echo "     - Login: 5 attempts per 15 min\n";
echo "     - Password reset: 3 attempts per hour\n";
echo "     - Contact form: 2 submissions per day\n";
echo "   ✓ Clear rate limits on successful authentication\n";
echo "   ✓ Return clear error messages with retry time\n";
echo "   ✓ Log rate limit violations for security monitoring\n";
echo "   ✓ Use different keys for different actions:\n";
echo "     - 'login:email' for login attempts\n";
echo "     - 'api:user:id' for authenticated API\n";
echo "     - 'api:ip:address' for guest API\n";
echo "   ✓ Test rate limits in staging environment\n";
echo "   ✓ Monitor and adjust limits based on usage patterns\n\n";

// ============================================================================
// 10. Testing
// ============================================================================

echo "10. Testing Rate Limits:\n";
echo "    " . str_repeat("=", 60) . "\n\n";

echo "    # Test with curl:\n";
echo "    for i in {1..10}; do\n";
echo "      curl -X POST http://localhost:8000/login \\\n";
echo "        -H 'Content-Type: application/json' \\\n";
echo "        -d '{\"email\":\"test@example.com\",\"password\":\"wrong\"}'\n";
echo "      echo \"Request \$i\"\n";
echo "      sleep 1\n";
echo "    done\n\n";

echo "    Expected: First 5 succeed (or fail auth), rest return 429\n\n";

echo "=== Demo Complete ===\n";
