<?php

declare(strict_types=1);

/**
 * XSS Protection Demo
 *
 * Demonstrates how to prevent XSS attacks using XssProtection utility.
 */

require __DIR__ . '/../vendor/autoload.php';

use Toporia\Framework\Security\XssProtection;

echo "=== XSS Protection Demo ===\n\n";

// Dangerous user input (attacker trying XSS)
$dangerousInputs = [
    'script_tag' => '<script>alert("XSS Attack!")</script>Hello',
    'event_handler' => '<img src=x onerror="alert(\'XSS\')">',
    'javascript_url' => '<a href="javascript:alert(\'XSS\')">Click me</a>',
    'html_entities' => '<b>Bold</b> & <i>Italic</i>',
    'nested_tags' => '<div onclick="steal()"><p>Content</p></div>',
    'svg_xss' => '<svg onload="alert(\'XSS\')">',
    'data_uri' => '<a href="data:text/html,<script>alert(\'XSS\')</script>">Link</a>',
];

// 1. escape() - HTML special characters escaping
echo "1. escape() - Basic HTML Escaping:\n";
echo "   Use case: Displaying user-generated content in HTML\n\n";

foreach ($dangerousInputs as $name => $input) {
    $safe = XssProtection::escape($input);
    echo "   {$name}:\n";
    echo "     Input:  " . substr($input, 0, 50) . "...\n";
    echo "     Output: " . substr($safe, 0, 50) . "...\n";
    echo "     Safe:   ✓ (tags converted to entities)\n\n";
}

// Helper function alias
echo "   Using helper function e():\n";
echo "     " . e('<script>alert("XSS")</script>') . "\n\n";

// 2. clean() - Strip all HTML tags
echo "2. clean() - Remove All HTML Tags:\n";
echo "   Use case: Plain text fields (usernames, titles, search queries)\n\n";

$htmlContent = '<p>Hello <b>World</b>!</p><script>alert("XSS")</script>';
$cleaned = XssProtection::clean($htmlContent);
echo "   Input:  {$htmlContent}\n";
echo "   Output: {$cleaned}\n";
echo "   Result: Only text remains, all tags removed ✓\n\n";

// Helper function alias
echo "   Using helper function clean():\n";
echo "     " . clean('<div>Text <script>evil()</script></div>') . "\n\n";

// 3. sanitize() - Allow specific safe tags
echo "3. sanitize() - Allow Specific Safe Tags:\n";
echo "   Use case: User comments, forum posts (allow formatting)\n\n";

$userComment = '<p>Great article!</p><b>Thanks!</b><script>steal()</script><img src=x onerror="alert(1)">';
$sanitized = XssProtection::sanitize($userComment, '<p><b><i><em><strong>');

echo "   Input:  {$userComment}\n";
echo "   Output: {$sanitized}\n";
echo "   Result: Safe tags kept, dangerous ones removed ✓\n\n";

// 4. purify() - Rich text cleaning
echo "4. purify() - Rich Text Content (Blog Posts, Articles):\n";
echo "   Use case: WYSIWYG editors, blog content\n\n";

$richText = '
<h2>My Blog Post</h2>
<p>This is <b>safe</b> content.</p>
<script>alert("This will be removed")</script>
<p onclick="evil()">No inline JS allowed</p>
<a href="javascript:alert(1)">Dangerous link</a>
<a href="https://example.com">Safe link</a>
<img src="image.jpg" alt="Safe image">
<img src=x onerror="alert(1)">
';

$purified = XssProtection::purify($richText);
echo "   Input:  [Rich HTML content with XSS attempts]\n";
echo "   Output:\n" . $purified . "\n";
echo "   Result: Safe HTML preserved, XSS vectors removed ✓\n\n";

// 5. Context-specific escaping
echo "5. Context-Specific Escaping:\n\n";

// JavaScript context
echo "   a) escapeJs() - For embedding in JavaScript:\n";
$userInput = "'; alert('XSS'); var x='";
$jsEscaped = XssProtection::escapeJs($userInput);
echo "      Input:  {$userInput}\n";
echo "      Output: {$jsEscaped}\n";
echo "      Usage:  var username = '{$jsEscaped}';\n\n";

// URL context
echo "   b) escapeUrl() - For URL parameters:\n";
$urlParam = "param=value&redirect=javascript:alert(1)";
$urlEscaped = XssProtection::escapeUrl($urlParam);
echo "      Input:  {$urlParam}\n";
echo "      Output: {$urlEscaped}\n";
echo "      Usage:  /search?q={$urlEscaped}\n\n";

// 6. Array cleaning (nested data)
echo "6. cleanArray() - Clean Nested Arrays:\n";
echo "   Use case: Form submissions, API data\n\n";

$formData = [
    'username' => '<b>john_doe</b>',
    'bio' => '<script>alert("XSS")</script>My bio',
    'profile' => [
        'website' => '<a href="javascript:alert(1)">evil.com</a>',
        'interests' => ['<b>coding</b>', '<script>hack()</script>security'],
    ],
];

echo "   Input (nested array with XSS):\n";
print_r($formData);

$cleanedArray = XssProtection::cleanArray($formData);
echo "\n   Output (all values cleaned):\n";
print_r($cleanedArray);

// 7. Practical examples in controllers
echo "\n7. Practical Usage in Controllers:\n\n";

echo "   Example 1 - Display user comment:\n";
echo "   ----------------------------------------\n";
echo "   \$comment = \$request->input('comment');\n";
echo "   \$safeComment = XssProtection::sanitize(\$comment, ['p', 'b', 'i']);\n";
echo "   return \$this->view('comments/show', ['comment' => \$safeComment]);\n\n";

echo "   Example 2 - Display username:\n";
echo "   ----------------------------------------\n";
echo "   \$username = \$request->input('username');\n";
echo "   \$safeUsername = clean(\$username); // Strip all tags\n";
echo "   return \$response->json(['username' => \$safeUsername]);\n\n";

echo "   Example 3 - Blog post content:\n";
echo "   ----------------------------------------\n";
echo "   \$content = \$request->input('content');\n";
echo "   \$safeContent = XssProtection::purify(\$content);\n";
echo "   \$post->content = \$safeContent;\n";
echo "   \$post->save();\n\n";

echo "   Example 4 - In Blade-like templates:\n";
echo "   ----------------------------------------\n";
echo "   <!-- Auto-escape by default -->\n";
echo "   <h1><?= e(\$title) ?></h1>\n";
echo "   <p><?= e(\$description) ?></p>\n";
echo "   \n";
echo "   <!-- Allow safe HTML -->\n";
echo "   <div><?= XssProtection::purify(\$richContent) ?></div>\n\n";

// 8. Security headers middleware
echo "8. Security Headers Middleware (automatic):\n";
echo "   AddSecurityHeaders middleware adds these headers:\n\n";
echo "   X-Content-Type-Options: nosniff\n";
echo "   X-Frame-Options: DENY\n";
echo "   X-XSS-Protection: 1; mode=block\n";
echo "   Strict-Transport-Security: max-age=31536000; includeSubDomains\n";
echo "   Content-Security-Policy: default-src 'self'; script-src 'self'\n";
echo "   Referrer-Policy: strict-origin-when-cross-origin\n\n";

echo "   Configure in config/security.php\n";
echo "   Automatically applied via global middleware\n\n";

// 9. Best practices summary
echo "9. XSS Prevention Best Practices:\n";
echo "   ✓ Always escape output: e(\$variable)\n";
echo "   ✓ Use clean() for plain text fields\n";
echo "   ✓ Use sanitize() for limited HTML formatting\n";
echo "   ✓ Use purify() for rich text content\n";
echo "   ✓ Use context-specific escaping (JS, URL)\n";
echo "   ✓ Enable security headers middleware\n";
echo "   ✓ Never trust user input\n";
echo "   ✓ Validate AND sanitize\n";

echo "\n=== Demo Complete ===\n";
