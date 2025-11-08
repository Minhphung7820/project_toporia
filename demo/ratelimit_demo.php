<?php

declare(strict_types=1);

/**
 * Rate Limiting Demo
 *
 * Demonstrates how to use rate limiting to prevent abuse.
 */

require __DIR__ . '/../vendor/autoload.php';

use Toporia\Framework\Cache\MemoryCache;
use Toporia\Framework\RateLimit\CacheRateLimiter;
use Toporia\Framework\Http\Middleware\ThrottleRequests;
use Toporia\Framework\Http\Request;
use Toporia\Framework\Http\Response;

echo "=== Rate Limiting Demo ===\n\n";

// Create cache (using MemoryCache for demo, use Redis in production)
$cache = new MemoryCache();

// Create rate limiter
$limiter = new CacheRateLimiter($cache);

echo "1. Basic Rate Limiting:\n";
echo "   Limit: 5 requests per 60 seconds\n\n";

$key = 'user:123';
$maxAttempts = 5;
$decaySeconds = 60;

// Simulate multiple requests
for ($i = 1; $i <= 7; $i++) {
    $allowed = $limiter->attempt($key, $maxAttempts, $decaySeconds);
    $remaining = $limiter->remaining($key, $maxAttempts);
    $tooMany = $limiter->tooManyAttempts($key, $maxAttempts);

    echo "   Request #{$i}: ";

    if ($allowed) {
        echo "✓ ALLOWED (Remaining: {$remaining})\n";
    } else {
        $retryAfter = $limiter->availableIn($key);
        echo "✗ BLOCKED (Too many attempts, retry after {$retryAfter}s)\n";
    }
}

echo "\n";

// 2. Different rate limit keys (per user, per IP, per action)
echo "2. Different Rate Limit Strategies:\n\n";

$strategies = [
    'user:123:login' => 'Login attempts for user 123',
    'ip:192.168.1.1:api' => 'API requests from IP 192.168.1.1',
    'global:contact-form' => 'Global contact form submissions',
];

foreach ($strategies as $key => $description) {
    $allowed = $limiter->attempt($key, 10, 60);
    echo "   {$description}\n";
    echo "     Key: {$key}\n";
    echo "     Status: " . ($allowed ? '✓ Allowed' : '✗ Blocked') . "\n";
    echo "     Remaining: " . $limiter->remaining($key, 10) . "/10\n\n";
}

// 3. Clear rate limits (admin action)
echo "3. Clear Rate Limits:\n";
echo "   Clear rate limit for user 123...\n";
$limiter->clear('user:123');
echo "   ✓ Cleared\n";
echo "   New remaining: " . $limiter->remaining('user:123', 5) . "/5\n\n";

// 4. Middleware usage
echo "4. Rate Limit Middleware:\n";
echo "   Automatic rate limiting for HTTP requests\n\n";

// Create middleware (10 requests per minute)
$throttle = new ThrottleRequests($limiter, 10, 1);

// Simulate multiple requests from same IP
$userIp = '192.168.1.100';
echo "   Simulating 12 requests from IP: {$userIp}\n";
echo "   Limit: 10 requests per minute\n\n";

for ($i = 1; $i <= 12; $i++) {
    $request = new Request(
        method: 'GET',
        uri: '/api/data',
        server: ['REMOTE_ADDR' => $userIp]
    );

    $response = new Response();

    $result = $throttle->handle($request, $response, function($req, $res) {
        return $res->json(['data' => 'success']);
    });

    echo "   Request #{$i}: ";

    if ($response->getStatus() === 429) {
        echo "✗ BLOCKED (429 Too Many Requests)\n";
        echo "     Headers:\n";
        foreach ($response->getHeaders() as $name => $value) {
            if (str_starts_with($name, 'X-RateLimit-') || $name === 'Retry-After') {
                echo "       {$name}: {$value}\n";
            }
        }
    } else {
        echo "✓ ALLOWED\n";
        foreach ($response->getHeaders() as $name => $value) {
            if (str_starts_with($name, 'X-RateLimit-')) {
                echo "       {$name}: {$value}\n";
            }
        }
    }
}

echo "\n";

// 5. Authenticated vs Guest rate limits
echo "5. Different Limits for Authenticated vs Guest Users:\n\n";

// Guest user (lower limit)
echo "   a) Guest User (IP-based, lower limit):\n";
$guestRequest = new Request(
    method: 'GET',
    uri: '/api/data',
    server: ['REMOTE_ADDR' => '192.168.1.200']
);
echo "      IP: 192.168.1.200\n";
echo "      Limit: 10 requests/minute (default)\n";
echo "      Key: ip:192.168.1.200\n\n";

// Authenticated user (higher limit)
echo "   b) Authenticated User (user-based, higher limit):\n";
echo "      User ID: 456\n";
echo "      Limit: 100 requests/minute (configured per route)\n";
echo "      Key: user:456\n\n";

// 6. Custom rate limit configurations
echo "6. Custom Rate Limit Configurations:\n\n";

$configs = [
    'login' => ['attempts' => 5, 'decay' => 900, 'description' => 'Login attempts (5 per 15 min)'],
    'api' => ['attempts' => 60, 'decay' => 60, 'description' => 'API requests (60 per minute)'],
    'password_reset' => ['attempts' => 3, 'decay' => 3600, 'description' => 'Password reset (3 per hour)'],
    'contact_form' => ['attempts' => 2, 'decay' => 86400, 'description' => 'Contact form (2 per day)'],
];

foreach ($configs as $name => $config) {
    echo "   {$config['description']}:\n";
    echo "     \$router->post('/{$name}', [Controller::class, 'method'])\n";
    echo "         ->middleware(['throttle:{$config['attempts']},{$config['decay']}']);\n\n";
}

// 7. Practical examples
echo "7. Practical Examples in Routes:\n\n";

echo "   Example 1 - API endpoints:\n";
echo "   ----------------------------------------\n";
echo "   \$router->get('/api/users', [ApiController::class, 'users'])\n";
echo "       ->middleware(['throttle:60,60']); // 60 requests per minute\n\n";

echo "   Example 2 - Login endpoint:\n";
echo "   ----------------------------------------\n";
echo "   \$router->post('/login', [AuthController::class, 'login'])\n";
echo "       ->middleware(['throttle:5,900']); // 5 attempts per 15 minutes\n\n";

echo "   Example 3 - Contact form:\n";
echo "   ----------------------------------------\n";
echo "   \$router->post('/contact', [ContactController::class, 'submit'])\n";
echo "       ->middleware(['throttle:2,86400']); // 2 submissions per day\n\n";

echo "   Example 4 - Different limits for auth/guest:\n";
echo "   ----------------------------------------\n";
echo "   // In ThrottleRequests middleware:\n";
echo "   // - Authenticated users: user:{id} key\n";
echo "   // - Guest users: ip:{ip} key\n";
echo "   // Configure different limits per route\n\n";

// 8. Manual rate limiting in controller
echo "8. Manual Rate Limiting in Controllers:\n\n";

echo "   <?php\n";
echo "   class LoginController extends BaseController\n";
echo "   {\n";
echo "       public function __construct(\n";
echo "           private RateLimiterInterface \$limiter\n";
echo "       ) {}\n";
echo "   \n";
echo "       public function login(Request \$request, Response \$response)\n";
echo "       {\n";
echo "           \$email = \$request->input('email');\n";
echo "           \$key = \"login:{\$email}\";\n";
echo "   \n";
echo "           // Check rate limit\n";
echo "           if (\$this->limiter->tooManyAttempts(\$key, 5)) {\n";
echo "               \$seconds = \$this->limiter->availableIn(\$key);\n";
echo "               return \$response->json([\n";
echo "                   'error' => \"Too many login attempts. Try again in {\$seconds}s\"\n";
echo "               ], 429);\n";
echo "           }\n";
echo "   \n";
echo "           // Attempt login\n";
echo "           \$this->limiter->attempt(\$key, 5, 900);\n";
echo "   \n";
echo "           // ... login logic ...\n";
echo "   \n";
echo "           // Clear on successful login\n";
echo "           \$this->limiter->clear(\$key);\n";
echo "       }\n";
echo "   }\n\n";

// 9. Cache driver comparison
echo "9. Cache Driver for Rate Limiting:\n\n";

echo "   a) MemoryCache:\n";
echo "      - Good for: Testing, single-process apps\n";
echo "      - Limitation: Not shared between requests\n\n";

echo "   b) FileCache:\n";
echo "      - Good for: Simple apps, shared hosting\n";
echo "      - Limitation: Slower than Redis, file I/O overhead\n\n";

echo "   c) RedisCache (RECOMMENDED for production):\n";
echo "      - Good for: High-traffic apps, multiple servers\n";
echo "      - Benefits: Fast, atomic operations, shared state\n";
echo "      - Setup:\n";
echo "        \$cache = CacheManager::driver('redis');\n";
echo "        \$limiter = new CacheRateLimiter(\$cache);\n\n";

// 10. Response headers
echo "10. Rate Limit Response Headers:\n";
echo "    The middleware automatically adds these headers:\n\n";
echo "    X-RateLimit-Limit: 60        (max requests allowed)\n";
echo "    X-RateLimit-Remaining: 45    (requests left in window)\n";
echo "    Retry-After: 30              (seconds until retry, when blocked)\n\n";

// 11. Best practices
echo "11. Rate Limiting Best Practices:\n";
echo "    ✓ Use Redis cache in production for accuracy\n";
echo "    ✓ Set appropriate limits per endpoint type\n";
echo "    ✓ Lower limits for sensitive operations (login, password reset)\n";
echo "    ✓ Higher limits for authenticated users\n";
echo "    ✓ Clear rate limits on successful auth\n";
echo "    ✓ Return clear error messages with retry time\n";
echo "    ✓ Log rate limit violations for security monitoring\n";
echo "    ✓ Consider different keys: per-user, per-IP, per-action\n";
echo "    ✓ Use global middleware for API routes\n";
echo "    ✓ Test rate limits in staging environment\n";

echo "\n=== Demo Complete ===\n";
