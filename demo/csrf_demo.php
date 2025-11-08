<?php

declare(strict_types=1);

/**
 * CSRF Protection Demo
 *
 * Demonstrates how to use CSRF protection in forms and AJAX requests.
 */

require __DIR__ . '/../vendor/autoload.php';

use Toporia\Framework\Security\SessionCsrfTokenManager;
use Toporia\Framework\Http\Middleware\CsrfProtection;
use Toporia\Framework\Http\Request;
use Toporia\Framework\Http\Response;

// Start session (required for CSRF tokens)
session_start();

// Create CSRF token manager
$csrfManager = new SessionCsrfTokenManager();

echo "=== CSRF Protection Demo ===\n\n";

// 1. Generate and display CSRF token
echo "1. Generate CSRF Token:\n";
$token = $csrfManager->generate();
echo "   Token: " . substr($token, 0, 20) . "...\n\n";

// 2. Example: HTML Form with CSRF protection
echo "2. HTML Form with CSRF Token:\n";
echo "   " . htmlspecialchars('<form method="POST" action="/submit">') . "\n";
$fieldHtml = '<input type="hidden" name="_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
echo "       " . $fieldHtml . "\n";
echo "       " . htmlspecialchars('<input type="text" name="username">') . "\n";
echo "       " . htmlspecialchars('<button type="submit">Submit</button>') . "\n";
echo "   " . htmlspecialchars('</form>') . "\n\n";

// 3. Example: AJAX Request with CSRF token
echo "3. JavaScript AJAX with CSRF Token:\n";
echo "   fetch('/api/endpoint', {\n";
echo "       method: 'POST',\n";
echo "       headers: {\n";
echo "           'X-CSRF-TOKEN': '" . $token . "',\n";
echo "           'Content-Type': 'application/json'\n";
echo "       },\n";
echo "       body: JSON.stringify({data: 'value'})\n";
echo "   });\n\n";

// 4. Validate CSRF token (simulate POST request)
echo "4. Validate CSRF Token:\n";

// Simulate valid POST request
$validRequest = new Request(
    method: 'POST',
    uri: '/submit',
    headers: ['X-CSRF-TOKEN' => $token],
    post: ['_token' => $token, 'username' => 'john']
);

echo "   Valid Request: ";
try {
    $middleware = new CsrfProtection($csrfManager);
    $response = new Response();

    $result = $middleware->handle($validRequest, $response, function($req, $res) {
        return $res->json(['status' => 'success']);
    });

    echo "✓ PASSED\n";
} catch (\Exception $e) {
    echo "✗ FAILED: " . $e->getMessage() . "\n";
}

// Simulate invalid POST request (wrong token)
echo "   Invalid Request (wrong token): ";
$invalidRequest = new Request(
    method: 'POST',
    uri: '/submit',
    headers: [],
    post: ['_token' => 'wrong-token', 'username' => 'john']
);

$response = new Response();
$middleware->handle($invalidRequest, $response, function($req, $res) {
    return $res->json(['status' => 'success']);
});

if ($response->getStatus() === 419) {
    echo "✓ BLOCKED (419 CSRF Token Mismatch)\n";
} else {
    echo "✗ Should have been blocked!\n";
}

// 5. Token validation methods
echo "\n5. Token Validation:\n";
echo "   validate(correct_token): " . ($csrfManager->validate($token) ? '✓ Valid' : '✗ Invalid') . "\n";
echo "   validate(wrong_token): " . ($csrfManager->validate('wrong') ? '✓ Valid' : '✗ Invalid') . "\n";

// 6. Token regeneration (after login)
echo "\n6. Token Regeneration (security best practice after login):\n";
$oldToken = $csrfManager->getToken();
$csrfManager->regenerate();
$newToken = $csrfManager->getToken();
echo "   Old Token: " . substr($oldToken ?? '', 0, 20) . "...\n";
echo "   New Token: " . substr($newToken, 0, 20) . "...\n";
echo "   Tokens different: " . ($oldToken !== $newToken ? '✓ Yes' : '✗ No') . "\n";

// 7. Middleware in routes example
echo "\n7. Usage in Routes (see routes/web.php):\n";
echo "   \$router->post('/submit', [FormController::class, 'submit'])\n";
echo "       ->middleware(['csrf']); // Automatic CSRF validation\n";

echo "\n=== Demo Complete ===\n";
