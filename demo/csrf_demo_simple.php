<?php

declare(strict_types=1);

/**
 * CSRF Protection Demo - Simplified
 */

require __DIR__ . '/../vendor/autoload.php';

use Toporia\Framework\Security\SessionCsrfTokenManager;

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Create CSRF token manager
$csrfManager = new SessionCsrfTokenManager();

echo "=== CSRF Protection Demo ===\n\n";

// 1. Generate CSRF token
echo "1. Generate CSRF Token:\n";
$token = $csrfManager->generate();
echo "   Token: " . substr($token, 0, 40) . "...\n";
echo "   Length: " . strlen($token) . " characters\n\n";

// 2. HTML Form example
echo "2. HTML Form with CSRF Token:\n";
echo "   ------------------------------------------------------------\n";
echo "   <form method=\"POST\" action=\"/submit\">\n";
echo "       <input type=\"hidden\" name=\"_token\" value=\"{$token}\">\n";
echo "       <input type=\"text\" name=\"username\">\n";
echo "       <button type=\"submit\">Submit</button>\n";
echo "   </form>\n\n";

// 3. AJAX example
echo "3. JavaScript AJAX with CSRF Token:\n";
echo "   ------------------------------------------------------------\n";
echo "   fetch('/api/endpoint', {\n";
echo "       method: 'POST',\n";
echo "       headers: {\n";
echo "           'X-CSRF-TOKEN': '{$token}',\n";
echo "           'Content-Type': 'application/json'\n";
echo "       },\n";
echo "       body: JSON.stringify({data: 'value'})\n";
echo "   });\n\n";

// 4. Validate token
echo "4. Token Validation:\n";
echo "   ------------------------------------------------------------\n";

// Valid token
echo "   a) Validate correct token:\n";
$isValid = $csrfManager->validate($token);
echo "      Result: " . ($isValid ? "✓ VALID" : "✗ INVALID") . "\n\n";

// Invalid token
echo "   b) Validate wrong token:\n";
$isValid = $csrfManager->validate('wrong-token-12345');
echo "      Result: " . ($isValid ? "✓ VALID" : "✗ INVALID (Expected)") . "\n\n";

// 5. Get existing token
echo "5. Get Existing Token:\n";
echo "   ------------------------------------------------------------\n";
$existingToken = $csrfManager->getToken();
echo "   Token: " . substr($existingToken, 0, 40) . "...\n";
echo "   Same as generated: " . ($existingToken === $token ? "✓ Yes" : "✗ No") . "\n\n";

// 6. Regenerate token
echo "6. Regenerate Token (after login):\n";
echo "   ------------------------------------------------------------\n";
$oldToken = $csrfManager->getToken();
echo "   Old token: " . substr($oldToken, 0, 40) . "...\n";

$csrfManager->regenerate();
$newToken = $csrfManager->getToken();
echo "   New token: " . substr($newToken, 0, 40) . "...\n";
echo "   Different:  " . ($oldToken !== $newToken ? "✓ Yes" : "✗ No") . "\n\n";

// 7. Remove token
echo "7. Remove Token:\n";
echo "   ------------------------------------------------------------\n";
$csrfManager->remove();
$removed = $csrfManager->getToken();
echo "   Token after remove: " . ($removed === null ? "✓ NULL (removed)" : "✗ Still exists") . "\n\n";

// 8. Usage in routes
echo "8. Usage in Routes (config/middleware.php):\n";
echo "   ------------------------------------------------------------\n";
echo "   'aliases' => [\n";
echo "       'csrf' => CsrfProtection::class,\n";
echo "   ]\n\n";

echo "   // In routes/web.php:\n";
echo "   \$router->post('/login', [AuthController::class, 'login'])\n";
echo "       ->middleware(['csrf']);\n\n";

echo "   \$router->post('/posts', [PostController::class, 'store'])\n";
echo "       ->middleware(['auth', 'csrf']);\n\n";

// 9. How middleware works
echo "9. How CsrfProtection Middleware Works:\n";
echo "   ------------------------------------------------------------\n";
echo "   1. Checks HTTP method (skip GET, HEAD, OPTIONS)\n";
echo "   2. Looks for token in:\n";
echo "      - POST data: _token field\n";
echo "      - Headers: X-CSRF-TOKEN or X-XSRF-TOKEN\n";
echo "   3. Validates token using hash_equals (timing-safe)\n";
echo "   4. Returns 419 if invalid/missing\n";
echo "   5. Continues to next middleware if valid\n\n";

// 10. Security best practices
echo "10. Security Best Practices:\n";
echo "    ------------------------------------------------------------\n";
echo "    ✓ Always use CSRF protection on state-changing requests\n";
echo "    ✓ Include token in all POST/PUT/PATCH/DELETE forms\n";
echo "    ✓ Use X-CSRF-TOKEN header for AJAX requests\n";
echo "    ✓ Regenerate token after login (prevent fixation)\n";
echo "    ✓ Use timing-safe comparison (hash_equals)\n";
echo "    ✓ Store token in session (not cookies)\n";
echo "    ✓ Keep tokens secret (don't log them)\n";
echo "    ✓ Set SameSite=Strict on cookies\n\n";

echo "=== Demo Complete ===\n";
