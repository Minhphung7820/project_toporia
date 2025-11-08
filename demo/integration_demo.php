<?php

declare(strict_types=1);

/**
 * Integration Demo - CSRF + XSS + Rate Limiting Together
 *
 * Shows how to use all security features in a real application.
 */

echo "=== Security Features Integration Demo ===\n\n";

// ============================================================
// 1. ROUTES CONFIGURATION (routes/web.php)
// ============================================================

echo "1. Routes with Security Middleware:\n";
echo "   " . str_repeat("=", 60) . "\n\n";

echo "   <?php\n";
echo "   // routes/web.php\n\n";

echo "   // Public routes (no auth required)\n";
echo "   \$router->get('/', [HomeController::class, 'index']);\n";
echo "   \$router->get('/login', [AuthController::class, 'showLogin']);\n\n";

echo "   // Login with rate limiting (prevent brute force)\n";
echo "   \$router->post('/login', [AuthController::class, 'login'])\n";
echo "       ->middleware([\n";
echo "           'csrf',              // CSRF protection\n";
echo "           'throttle:5,900'     // 5 attempts per 15 minutes\n";
echo "       ]);\n\n";

echo "   // Contact form (rate limited)\n";
echo "   \$router->post('/contact', [ContactController::class, 'submit'])\n";
echo "       ->middleware([\n";
echo "           'csrf',              // CSRF protection\n";
echo "           'throttle:2,3600'    // 2 submissions per hour\n";
echo "       ]);\n\n";

echo "   // API routes (no CSRF, but rate limited)\n";
echo "   \$router->get('/api/products', [ApiController::class, 'products'])\n";
echo "       ->middleware(['throttle:60,60']); // 60 requests per minute\n\n";

echo "   // Protected routes (require authentication)\n";
echo "   \$router->post('/posts', [PostController::class, 'store'])\n";
echo "       ->middleware([\n";
echo "           'auth',              // Must be logged in\n";
echo "           'csrf',              // CSRF protection\n";
echo "           'throttle:10,60'     // 10 posts per minute\n";
echo "       ]);\n\n";

echo "   // Admin routes (authentication + authorization)\n";
echo "   \$router->delete('/users/{id}', [AdminController::class, 'deleteUser'])\n";
echo "       ->middleware([\n";
echo "           'auth',\n";
echo "           'authorize:delete-user',  // Gate check\n";
echo "           'csrf',\n";
echo "           'throttle:20,60'\n";
echo "       ]);\n\n";

// ============================================================
// 2. CONTROLLER EXAMPLES
// ============================================================

echo "2. Controller Implementation:\n";
echo "   " . str_repeat("=", 60) . "\n\n";

echo "   A) Login Controller with Rate Limiting:\n";
echo "   " . str_repeat("-", 60) . "\n";
echo "   <?php\n\n";
echo "   namespace App\\Presentation\\Controllers;\n\n";
echo "   use Toporia\\Framework\\Security\\XssProtection;\n";
echo "   use Toporia\\Framework\\RateLimit\\RateLimiterInterface;\n\n";
echo "   class AuthController extends BaseController\n";
echo "   {\n";
echo "       public function __construct(\n";
echo "           Request \$request,\n";
echo "           Response \$response,\n";
echo "           private RateLimiterInterface \$limiter\n";
echo "       ) {\n";
echo "           parent::__construct(\$request, \$response);\n";
echo "       }\n\n";

echo "       public function showLogin()\n";
echo "       {\n";
echo "           // No XSS risk - static view\n";
echo "           return \$this->view('auth/login');\n";
echo "       }\n\n";

echo "       public function login()\n";
echo "       {\n";
echo "           // Input sanitization (prevent XSS)\n";
echo "           \$email = clean(\$this->request->input('email'));\n";
echo "           \$password = \$this->request->input('password');\n\n";

echo "           // Additional rate limiting per email\n";
echo "           \$key = \"login:{\$email}\";\n";
echo "           if (\$this->limiter->tooManyAttempts(\$key, 5)) {\n";
echo "               \$seconds = \$this->limiter->availableIn(\$key);\n";
echo "               return \$this->response->json([\n";
echo "                   'error' => \"Too many attempts. Retry in {\$seconds}s\"\n";
echo "               ], 429);\n";
echo "           }\n\n";

echo "           // Attempt login\n";
echo "           if (!auth()->guard()->attempt(['email' => \$email, 'password' => \$password])) {\n";
echo "               \$this->limiter->attempt(\$key, 5, 900);\n";
echo "               return \$this->response->json(['error' => 'Invalid credentials'], 401);\n";
echo "           }\n\n";

echo "           // Success - clear rate limit\n";
echo "           \$this->limiter->clear(\$key);\n\n";

echo "           // Regenerate CSRF token (security best practice)\n";
echo "           app('csrf')->regenerate();\n\n";

echo "           return \$this->response->json(['success' => true]);\n";
echo "       }\n";
echo "   }\n\n";

echo "   B) Blog Post Controller with XSS Protection:\n";
echo "   " . str_repeat("-", 60) . "\n";
echo "   <?php\n\n";
echo "   class PostController extends BaseController\n";
echo "   {\n";
echo "       public function store()\n";
echo "       {\n";
echo "           // Get user input\n";
echo "           \$title = \$this->request->input('title');\n";
echo "           \$content = \$this->request->input('content');\n\n";

echo "           // Sanitize title (plain text only)\n";
echo "           \$safeTitle = clean(\$title);\n\n";

echo "           // Purify content (allow safe HTML formatting)\n";
echo "           \$safeContent = XssProtection::purify(\$content);\n\n";

echo "           // Save to database\n";
echo "           \$post = Post::create([\n";
echo "               'title' => \$safeTitle,\n";
echo "               'content' => \$safeContent,\n";
echo "               'user_id' => auth()->guard()->id()\n";
echo "           ]);\n\n";

echo "           return \$this->response->json(\$post, 201);\n";
echo "       }\n\n";

echo "       public function show(\$id)\n";
echo "       {\n";
echo "           \$post = Post::find(\$id);\n\n";
echo "           // Already sanitized in database\n";
echo "           // But escape again for safety in view\n";
echo "           return \$this->view('posts/show', [\n";
echo "               'title' => e(\$post->title),\n";
echo "               'content' => \$post->content // Already purified\n";
echo "           ]);\n";
echo "       }\n";
echo "   }\n\n";

echo "   C) API Controller with Rate Limiting:\n";
echo "   " . str_repeat("-", 60) . "\n";
echo "   <?php\n\n";
echo "   class ApiController extends BaseController\n";
echo "   {\n";
echo "       public function search()\n";
echo "       {\n";
echo "           // Sanitize search query (prevent XSS)\n";
echo "           \$query = clean(\$this->request->input('q'));\n\n";

echo "           // Search products\n";
echo "           \$products = Product::where('title', 'LIKE', \"%{\$query}%\")->get();\n\n";

echo "           // Escape output data\n";
echo "           \$safeProducts = \$products->map(function(\$product) {\n";
echo "               return [\n";
echo "                   'id' => \$product->id,\n";
echo "                   'title' => e(\$product->title),\n";
echo "                   'description' => e(\$product->description)\n";
echo "               ];\n";
echo "           });\n\n";

echo "           return \$this->response->json(\$safeProducts);\n";
echo "       }\n";
echo "   }\n\n";

// ============================================================
// 3. VIEW TEMPLATES
// ============================================================

echo "3. View Templates with XSS Protection:\n";
echo "   " . str_repeat("=", 60) . "\n\n";

echo "   A) Login Form (with CSRF token):\n";
echo "   " . str_repeat("-", 60) . "\n";
echo "   <!-- views/auth/login.php -->\n\n";
echo "   <form method=\"POST\" action=\"/login\" id=\"loginForm\">\n";
echo "       <?= csrf_field() ?>\n\n";
echo "       <div>\n";
echo "           <label>Email:</label>\n";
echo "           <input type=\"email\" name=\"email\" required>\n";
echo "       </div>\n\n";
echo "       <div>\n";
echo "           <label>Password:</label>\n";
echo "           <input type=\"password\" name=\"password\" required>\n";
echo "       </div>\n\n";
echo "       <button type=\"submit\">Login</button>\n";
echo "   </form>\n\n";
echo "   <script>\n";
echo "   // AJAX login with CSRF token\n";
echo "   document.getElementById('loginForm').addEventListener('submit', async (e) => {\n";
echo "       e.preventDefault();\n";
echo "       const formData = new FormData(e.target);\n\n";
echo "       const response = await fetch('/login', {\n";
echo "           method: 'POST',\n";
echo "           headers: {\n";
echo "               'X-CSRF-TOKEN': '<?= csrf_token() ?>',\n";
echo "               'Content-Type': 'application/x-www-form-urlencoded'\n";
echo "           },\n";
echo "           body: new URLSearchParams(formData)\n";
echo "       });\n\n";
echo "       if (response.status === 429) {\n";
echo "           alert('Too many login attempts. Please try again later.');\n";
echo "       }\n";
echo "   });\n";
echo "   </script>\n\n";

echo "   B) Blog Post Display (XSS-safe):\n";
echo "   " . str_repeat("-", 60) . "\n";
echo "   <!-- views/posts/show.php -->\n\n";
echo "   <article>\n";
echo "       <!-- Escape plain text -->\n";
echo "       <h1><?= e(\$title) ?></h1>\n\n";
echo "       <!-- Content already purified -->\n";
echo "       <div class=\"content\">\n";
echo "           <?= \$content ?>\n";
echo "       </div>\n\n";
echo "       <!-- User-generated comment -->\n";
echo "       <div class=\"comments\">\n";
echo "           <?php foreach (\$comments as \$comment): ?>\n";
echo "               <div class=\"comment\">\n";
echo "                   <strong><?= e(\$comment->author) ?>:</strong>\n";
echo "                   <?= XssProtection::sanitize(\$comment->text, ['b', 'i', 'em']) ?>\n";
echo "               </div>\n";
echo "           <?php endforeach; ?>\n";
echo "       </div>\n";
echo "   </article>\n\n";

echo "   C) Comment Form (CSRF + Rate Limited):\n";
echo "   " . str_repeat("-", 60) . "\n";
echo "   <form method=\"POST\" action=\"/posts/<?= \$postId ?>/comments\">\n";
echo "       <?= csrf_field() ?>\n\n";
echo "       <textarea name=\"comment\" required></textarea>\n";
echo "       <button type=\"submit\">Post Comment</button>\n";
echo "   </form>\n\n";

// ============================================================
// 4. MIDDLEWARE CONFIGURATION
// ============================================================

echo "4. Middleware Configuration:\n";
echo "   " . str_repeat("=", 60) . "\n\n";

echo "   // config/middleware.php\n\n";
echo "   return [\n";
echo "       'global' => [\n";
echo "           AddSecurityHeaders::class,  // XSS headers on every response\n";
echo "       ],\n\n";
echo "       'aliases' => [\n";
echo "           'auth' => Authenticate::class,\n";
echo "           'csrf' => CsrfProtection::class,\n";
echo "           'authorize' => Authorize::class,\n";
echo "           'throttle' => ThrottleRequests::class,\n";
echo "       ],\n";
echo "   ];\n\n";

// ============================================================
// 5. SECURITY CONFIGURATION
// ============================================================

echo "5. Security Configuration:\n";
echo "   " . str_repeat("=", 60) . "\n\n";

echo "   // config/security.php\n\n";
echo "   return [\n";
echo "       'csrf' => [\n";
echo "           'enabled' => true,\n";
echo "           'token_name' => '_token',\n";
echo "       ],\n\n";
echo "       'headers' => [\n";
echo "           'X-Frame-Options' => 'DENY',\n";
echo "           'X-Content-Type-Options' => 'nosniff',\n";
echo "           'X-XSS-Protection' => '1; mode=block',\n";
echo "           'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',\n";
echo "           'Content-Security-Policy' => \"default-src 'self'; script-src 'self'\",\n";
echo "       ],\n\n";
echo "       'cookies' => [\n";
echo "           'encrypt' => true,\n";
echo "           'http_only' => true,\n";
echo "           'secure' => true,  // HTTPS only\n";
echo "           'same_site' => 'Strict',\n";
echo "       ],\n";
echo "   ];\n\n";

// ============================================================
// 6. COMPLETE REQUEST FLOW
// ============================================================

echo "6. Complete Request Flow Example:\n";
echo "   " . str_repeat("=", 60) . "\n\n";

echo "   User submits login form:\n";
echo "   \n";
echo "   1. Browser sends POST /login with:\n";
echo "      - _token: abc123...       (CSRF token)\n";
echo "      - email: user@example.com\n";
echo "      - password: secret123\n";
echo "   \n";
echo "   2. AddSecurityHeaders middleware:\n";
echo "      ✓ Adds security headers to response\n";
echo "   \n";
echo "   3. CsrfProtection middleware:\n";
echo "      ✓ Validates CSRF token\n";
echo "      ✗ Returns 419 if invalid/missing\n";
echo "   \n";
echo "   4. ThrottleRequests middleware:\n";
echo "      ✓ Checks rate limit (5 attempts per 15 min)\n";
echo "      ✓ Adds X-RateLimit-* headers\n";
echo "      ✗ Returns 429 if exceeded\n";
echo "   \n";
echo "   5. Router dispatches to AuthController::login()\n";
echo "      ✓ Sanitizes email input (XSS prevention)\n";
echo "      ✓ Checks additional per-email rate limit\n";
echo "      ✓ Attempts authentication\n";
echo "      ✓ Regenerates CSRF token on success\n";
echo "      ✓ Clears rate limit on success\n";
echo "   \n";
echo "   6. Response sent to browser:\n";
echo "      Status: 200 OK\n";
echo "      Headers:\n";
echo "        X-Frame-Options: DENY\n";
echo "        X-Content-Type-Options: nosniff\n";
echo "        X-RateLimit-Limit: 5\n";
echo "        X-RateLimit-Remaining: 4\n";
echo "      Body: {\"success\": true}\n\n";

// ============================================================
// 7. TESTING
// ============================================================

echo "7. Testing Security Features:\n";
echo "   " . str_repeat("=", 60) . "\n\n";

echo "   A) Test CSRF Protection:\n";
echo "   -------------------------\n";
echo "   # Missing token\n";
echo "   curl -X POST http://localhost:8000/login \\\n";
echo "     -d 'email=test@example.com&password=secret'\n";
echo "   # Expected: 419 CSRF Token Mismatch\n\n";

echo "   # Valid token\n";
echo "   curl -X POST http://localhost:8000/login \\\n";
echo "     -H 'X-CSRF-TOKEN: {valid_token}' \\\n";
echo "     -d 'email=test@example.com&password=secret'\n";
echo "   # Expected: 200 OK or 401 Invalid Credentials\n\n";

echo "   B) Test Rate Limiting:\n";
echo "   ----------------------\n";
echo "   # Rapid requests\n";
echo "   for i in {1..10}; do\n";
echo "     curl -X POST http://localhost:8000/login \\\n";
echo "       -H 'X-CSRF-TOKEN: {token}' \\\n";
echo "       -d 'email=test@example.com&password=wrong'\n";
echo "     echo \"Request \$i\"\n";
echo "   done\n";
echo "   # Expected: First 5 succeed, rest return 429\n\n";

echo "   C) Test XSS Protection:\n";
echo "   -----------------------\n";
echo "   # Try to inject script\n";
echo "   curl -X POST http://localhost:8000/posts \\\n";
echo "     -H 'X-CSRF-TOKEN: {token}' \\\n";
echo "     -d 'title=<script>alert(1)</script>Test' \\\n";
echo "     -d 'content=<p>Safe</p><script>evil()</script>'\n";
echo "   # Expected: Script tags stripped/escaped\n\n";

// ============================================================
// 8. BEST PRACTICES SUMMARY
// ============================================================

echo "8. Security Best Practices Summary:\n";
echo "   " . str_repeat("=", 60) . "\n\n";

echo "   ✓ CSRF Protection:\n";
echo "     - Enable on all POST/PUT/PATCH/DELETE routes\n";
echo "     - Use csrf_field() in forms\n";
echo "     - Include X-CSRF-TOKEN header in AJAX\n";
echo "     - Regenerate token after login\n\n";

echo "   ✓ XSS Prevention:\n";
echo "     - Always escape output: e(\$variable)\n";
echo "     - Use clean() for plain text\n";
echo "     - Use purify() for rich content\n";
echo "     - Enable security headers globally\n";
echo "     - Never trust user input\n\n";

echo "   ✓ Rate Limiting:\n";
echo "     - Lower limits for sensitive operations\n";
echo "     - Higher limits for authenticated users\n";
echo "     - Use Redis cache in production\n";
echo "     - Clear limits on successful auth\n";
echo "     - Return clear error messages\n\n";

echo "   ✓ Defense in Depth:\n";
echo "     - Combine all three protections\n";
echo "     - Use HTTPS in production\n";
echo "     - Enable secure cookies\n";
echo "     - Set strict CSP headers\n";
echo "     - Validate AND sanitize input\n";
echo "     - Escape AND purify output\n\n";

echo "=== Integration Demo Complete ===\n\n";

echo "Run individual demos:\n";
echo "  php demo/csrf_demo.php\n";
echo "  php demo/xss_demo.php\n";
echo "  php demo/ratelimit_demo.php\n\n";

echo "Start development server:\n";
echo "  php -S localhost:8000 -t public\n";
