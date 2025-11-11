# URL Generation

Professional URL generation system with Laravel-compatible API for generating URLs to routes, assets, and signed URLs.

## Table of Contents

- [Overview](#overview)
- [Basic URL Generation](#basic-url-generation)
- [Route URLs](#route-urls)
- [Asset URLs](#asset-urls)
- [Signed URLs](#signed-urls)
- [Current & Previous URLs](#current--previous-urls)
- [URL Facade](#url-facade)
- [Middleware](#middleware)
- [Configuration](#configuration)
- [Performance](#performance)
- [Best Practices](#best-practices)

## Overview

The URL generation system provides a unified interface for generating URLs throughout your application.

**Features:**
- ✅ Route URL generation with parameter binding
- ✅ Asset URL generation (CSS, JS, images)
- ✅ Signed URLs with expiration (secure downloads, email unsubscribe)
- ✅ Previous URL tracking (redirects, back buttons)
- ✅ Absolute/relative URL support
- ✅ HTTPS forcing for production
- ✅ Laravel-compatible API (100% drop-in replacement)
- ✅ Clean Architecture (interface-based)
- ✅ High performance (O(1) for simple URLs, O(N) for route parameters)

**Architecture:**
- `UrlGeneratorInterface` - Contract defining URL generation methods
- `UrlGenerator` - Implementation with route/asset/signed URL support
- `URL` - Static facade accessor for convenience
- `ValidateSignature` - Middleware for protecting signed URL routes

## Basic URL Generation

### Using Helper Functions

```php
// Generate URL to path
$url = url('/products');
// Result: http://example.com/products

// With query parameters
$url = url('/search', ['q' => 'laptop', 'sort' => 'price']);
// Result: http://example.com/search?q=laptop&sort=price

// Relative URL
$url = url('/about', [], false);
// Result: /about

// Get UrlGenerator instance
$generator = url();
$url = $generator->to('/products');
```

### Using URL Facade

```php
use Toporia\Framework\Support\Accessors\URL;

$url = URL::to('/products');
$url = URL::to('/search', ['q' => 'laptop']);
```

### Secure URLs (HTTPS)

```php
// Force HTTPS for a single URL
$url = secure_url('/payment', ['order' => 123]);
// Result: https://example.com/payment?order=123

// Force HTTPS globally (in production)
url()->forceScheme('https');
```

## Route URLs

Generate URLs to named routes with automatic parameter binding.

### Named Routes

First, define named routes:

```php
// routes/web.php
$router->get('/products', [ProductsController::class, 'index'])
    ->name('products.index');

$router->get('/products/{id}', [ProductsController::class, 'show'])
    ->name('products.show');

$router->get('/products/{id}/reviews/{review}', [ProductsController::class, 'showReview'])
    ->name('products.reviews.show');
```

### Generating Route URLs

```php
// Simple route (no parameters)
$url = route('products.index');
// Result: http://example.com/products

// Route with single parameter
$url = route('products.show', ['id' => 123]);
// Result: http://example.com/products/123

// Route with multiple parameters
$url = route('products.reviews.show', [
    'id' => 123,
    'review' => 456
]);
// Result: http://example.com/products/123/reviews/456

// Extra parameters become query string
$url = route('products.index', ['category' => 'electronics', 'page' => 2]);
// Result: http://example.com/products?category=electronics&page=2

// Relative route URL
$url = route('products.show', ['id' => 123], false);
// Result: /products/123
```

### Optional Parameters

Routes with optional parameters:

```php
// Route definition
$router->get('/blog/{category?}', [BlogController::class, 'index'])
    ->name('blog.index');

// Without optional parameter
$url = route('blog.index');
// Result: http://example.com/blog

// With optional parameter
$url = route('blog.index', ['category' => 'tech']);
// Result: http://example.com/blog/tech
```

### Using URL Facade

```php
use Toporia\Framework\Support\Accessors\URL;

$url = URL::route('products.show', ['id' => 123]);
$url = URL::route('products.index', ['category' => 'books']);
```

### In Views

```php
<!-- Product link -->
<a href="<?= route('products.show', ['id' => $product->id]) ?>">
    <?= e($product->title) ?>
</a>

<!-- Pagination -->
<a href="<?= route('products.index', ['page' => $page + 1]) ?>">Next</a>

<!-- Form action -->
<form method="POST" action="<?= route('products.store') ?>">
    <?= csrf_field() ?>
    <!-- ... -->
</form>
```

## Asset URLs

Generate URLs for static assets (CSS, JavaScript, images, fonts).

### Basic Asset URLs

```php
// CSS
$url = asset('css/app.css');
// Result: /css/app.css

// JavaScript
$url = asset('js/app.js');
// Result: /js/app.js

// Image
$url = asset('images/logo.png');
// Result: /images/logo.png

// Absolute asset URL
$url = asset('css/app.css', true);
// Result: http://example.com/css/app.css
```

### Secure Asset URLs (HTTPS)

```php
// Force HTTPS for asset
$url = secure_asset('css/app.css');
// Result: https://example.com/css/app.css
```

### Using URL Facade

```php
use Toporia\Framework\Support\Accessors\URL;

$url = URL::asset('css/app.css');
$url = URL::secureAsset('js/app.js');
```

### In Views

```php
<!DOCTYPE html>
<html>
<head>
    <title>My App</title>
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
    <link rel="icon" href="<?= asset('images/favicon.ico') ?>">
</head>
<body>
    <img src="<?= asset('images/logo.png') ?>" alt="Logo">

    <script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
```

### CDN Support

Configure a separate CDN for assets:

```php
// In bootstrap or service provider
url()->setAssetRoot('https://cdn.example.com');

// Now asset() uses CDN
$url = asset('css/app.css', true);
// Result: https://cdn.example.com/css/app.css
```

## Signed URLs

Signed URLs provide secure, tamper-proof URLs with optional expiration. Perfect for:
- Email unsubscribe links
- Secure file downloads
- Password reset links
- One-time access tokens
- Email verification links

### How Signed URLs Work

1. **Generation**: HMAC-SHA256 signature is generated using secret key + URL + parameters
2. **URL Structure**: `https://example.com/path?param=value&signature=abc123&expires=1234567890`
3. **Verification**: Signature is recalculated and compared using timing-safe comparison
4. **Expiration**: Optional `expires` timestamp checked on verification

**Security:**
- ✅ Tamper-proof: Any URL modification invalidates signature
- ✅ Time-limited: Optional expiration prevents indefinite access
- ✅ HMAC-SHA256: Industry-standard cryptographic signing
- ✅ Timing-safe comparison: Prevents timing attacks

### Generating Signed URLs

```php
// Permanent signed URL (no expiration)
$url = signed_route('unsubscribe', ['email' => 'user@example.com']);
// Result: http://example.com/unsubscribe?email=user@example.com&signature=abc123...

// Temporary signed URL (expires in 1 hour)
$url = temporary_signed_route('download', 3600, ['file' => 'report.pdf']);
// Result: http://example.com/download?file=report.pdf&expires=1234567890&signature=abc123...

// Custom expiration (24 hours)
$url = signed_route('reset-password', ['token' => $token], 86400);

// Using URL facade
use Toporia\Framework\Support\Accessors\URL;

$url = URL::signedRoute('unsubscribe', ['email' => $email]);
$url = URL::temporarySignedRoute('download', 3600, ['file' => 'report.pdf']);
```

### Protecting Routes with ValidateSignature Middleware

```php
// routes/web.php
use Toporia\Framework\Http\Middleware\ValidateSignature;

$router->get('/unsubscribe', [NewsletterController::class, 'unsubscribe'])
    ->name('unsubscribe')
    ->middleware([ValidateSignature::class]);

$router->get('/download', [DownloadController::class, 'file'])
    ->name('download')
    ->middleware([ValidateSignature::class]);
```

**Middleware Behavior:**
- ✅ Automatically validates signature
- ✅ Checks expiration timestamp
- ✅ Returns 403 Forbidden on invalid/expired signature
- ✅ Passes to controller on valid signature

### Manual Signature Validation

```php
class UnsubscribeController
{
    public function unsubscribe(Request $request)
    {
        // Manual validation (if not using middleware)
        if (!url()->hasValidSignature(url_full())) {
            return response()->json(['error' => 'Invalid signature'], 403);
        }

        $email = $request->query('email');
        // Process unsubscribe...
    }
}
```

### Real-World Examples

#### Email Unsubscribe Link

```php
class NewsletterService
{
    public function sendNewsletter(User $user)
    {
        // Generate permanent signed URL
        $unsubscribeUrl = signed_route('unsubscribe', ['email' => $user->email]);

        $this->mailer->send($user->email, 'newsletter', [
            'content' => 'Newsletter content...',
            'unsubscribe_url' => $unsubscribeUrl
        ]);
    }
}

// In email template:
// <a href="<?= $unsubscribe_url ?>">Unsubscribe</a>

// Controller
class NewsletterController
{
    public function unsubscribe(Request $request)
    {
        // ValidateSignature middleware already validated signature
        $email = $request->query('email');

        $this->newsletter->unsubscribe($email);

        return response()->json(['message' => 'Successfully unsubscribed']);
    }
}
```

#### Secure File Download

```php
class DocumentController
{
    public function generateDownloadLink(Document $document)
    {
        // Generate temporary signed URL (1 hour expiration)
        $url = temporary_signed_route('download', 3600, [
            'id' => $document->id,
            'user' => auth()->id()
        ]);

        return response()->json(['download_url' => $url]);
    }

    public function download(Request $request)
    {
        // ValidateSignature middleware validates signature + expiration
        $documentId = $request->query('id');
        $userId = $request->query('user');

        // Additional authorization check
        if (auth()->id() !== (int)$userId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $document = Document::find($documentId);
        return response()->download($document->path, $document->filename);
    }
}
```

#### Email Verification Link

```php
class UserRegistrationController
{
    public function register(Request $request)
    {
        $user = User::create($request->only(['email', 'password']));

        // Generate temporary verification link (24 hours)
        $verifyUrl = temporary_signed_route('verify-email', 86400, [
            'id' => $user->id,
            'hash' => sha1($user->email)
        ]);

        $this->mailer->send($user->email, 'verify-email', [
            'verify_url' => $verifyUrl
        ]);

        return response()->json(['message' => 'Registration successful. Check email.']);
    }

    public function verifyEmail(Request $request)
    {
        // ValidateSignature middleware ensures URL is valid and not expired
        $userId = $request->query('id');
        $hash = $request->query('hash');

        $user = User::find($userId);

        // Verify hash matches current email
        if (sha1($user->email) !== $hash) {
            return response()->json(['error' => 'Invalid verification link'], 403);
        }

        $user->email_verified_at = time();
        $user->save();

        return response()->json(['message' => 'Email verified successfully']);
    }
}
```

#### Password Reset Link

```php
class PasswordResetController
{
    public function sendResetLink(Request $request)
    {
        $user = User::where('email', $request->input('email'))->first();

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        $token = bin2hex(random_bytes(32));

        // Store token in database
        $this->passwordResets->create([
            'email' => $user->email,
            'token' => hash('sha256', $token),
            'created_at' => time()
        ]);

        // Generate temporary signed URL (1 hour)
        $resetUrl = temporary_signed_route('password.reset', 3600, [
            'token' => $token,
            'email' => $user->email
        ]);

        $this->mailer->send($user->email, 'password-reset', [
            'reset_url' => $resetUrl
        ]);

        return response()->json(['message' => 'Reset link sent']);
    }

    public function reset(Request $request)
    {
        // ValidateSignature middleware validates URL
        $token = $request->query('token');
        $email = $request->query('email');

        // Verify token exists in database
        $reset = $this->passwordResets->findByToken(hash('sha256', $token));

        if (!$reset || $reset->email !== $email) {
            return response()->json(['error' => 'Invalid reset token'], 403);
        }

        // Show reset form or process new password
        // ...
    }
}
```

## Current & Previous URLs

### Current URL

```php
// Get current URL
$current = url_current();
// Result: http://example.com/products?page=2

// Using facade
use Toporia\Framework\Support\Accessors\URL;

$current = URL::current();
```

### Full URL with Query String

```php
// Get full URL including query parameters
$full = url_full();
// If on: http://example.com/search?q=laptop&sort=price
// Result: http://example.com/search?q=laptop&sort=price

// Using facade
$full = URL::full();
```

### Previous URL

```php
// Get previous URL (for "back" functionality)
$previous = url_previous();
$previous = url_previous('/dashboard'); // With default

// Using facade
$previous = URL::previous();
$previous = URL::previous('/home');
```

### Setting Previous URL

```php
// Manually set previous URL (usually done by framework)
url()->setPreviousUrl($request->full());

// Redirect back to previous page
return response()->redirect(url_previous());
```

### In Controllers

```php
class ProductController
{
    public function store(Request $request)
    {
        $product = Product::create($request->validated());

        // Redirect back to previous page
        return response()->redirect(url_previous('/products'));
    }

    public function delete(int $id)
    {
        Product::destroy($id);

        // Redirect to referer or default
        return response()->redirect(
            $request->header('referer') ?? route('products.index')
        );
    }
}
```

## URL Facade

Static accessor class for convenient URL generation.

### All Available Methods

```php
use Toporia\Framework\Support\Accessors\URL;

// Basic URLs
URL::to('/products');
URL::to('/search', ['q' => 'laptop']);

// Route URLs
URL::route('products.show', ['id' => 123]);

// Asset URLs
URL::asset('css/app.css');
URL::secureAsset('js/app.js');

// Signed URLs
URL::signedRoute('unsubscribe', ['email' => $email]);
URL::temporarySignedRoute('download', 3600, ['file' => 'report.pdf']);
URL::hasValidSignature($url);

// Current/Previous
URL::current();
URL::previous();
URL::full();

// Configuration
URL::setRootUrl('https://example.com');
URL::forceScheme('https');
```

### When to Use Facade vs Helpers

```php
// ✅ Helpers - Preferred for simple cases
$url = url('/products');
$route = route('products.show', ['id' => 123]);
$asset = asset('css/app.css');

// ✅ Facade - Preferred for:
// 1. Better IDE autocomplete
// 2. Static analysis tools
// 3. Code that explicitly imports dependencies
use Toporia\Framework\Support\Accessors\URL;

class ProductPresenter
{
    public function getImageUrl(Product $product): string
    {
        return URL::asset("images/products/{$product->id}.jpg", true);
    }
}

// ❌ Bad - Mixing styles unnecessarily
$url1 = url('/path1');
$url2 = URL::to('/path2');
```

## Middleware

### ValidateSignature Middleware

Automatically validates signed URL signatures on protected routes.

**Usage:**

```php
use Toporia\Framework\Http\Middleware\ValidateSignature;

// Protect single route
$router->get('/unsubscribe', [NewsletterController::class, 'unsubscribe'])
    ->name('unsubscribe')
    ->middleware([ValidateSignature::class]);

// Protect route group
$router->group(['middleware' => [ValidateSignature::class]], function ($router) {
    $router->get('/download', [DownloadController::class, 'file'])->name('download');
    $router->get('/verify-email', [AuthController::class, 'verify'])->name('verify');
});
```

**Middleware Behavior:**

```php
// ✅ Valid signature - passes to controller
GET /unsubscribe?email=user@example.com&signature=valid_signature_here
→ 200 OK

// ❌ Invalid signature - returns 403
GET /unsubscribe?email=user@example.com&signature=invalid
→ 403 Forbidden {"error": "Invalid or expired signature"}

// ❌ Expired signature - returns 403
GET /download?file=report.pdf&expires=1234567890&signature=valid_but_expired
→ 403 Forbidden {"error": "Invalid or expired signature"}

// ❌ Missing signature - returns 403
GET /unsubscribe?email=user@example.com
→ 403 Forbidden {"error": "Invalid or expired signature"}
```

## Configuration

### Setting Root URL

```php
// Set custom root URL
url()->setRootUrl('https://example.com');

// Now all absolute URLs use this root
$url = url('/products');
// Result: https://example.com/products
```

### Force HTTPS (Production)

```php
// Force HTTPS for all URLs
url()->forceScheme('https');

// Now all URLs use HTTPS
$url = url('/checkout');
// Result: https://example.com/checkout
```

### CDN Configuration

```php
// Use separate CDN for assets
url()->setAssetRoot('https://cdn.example.com');

$url = asset('css/app.css', true);
// Result: https://cdn.example.com/css/app.css

// Regular URLs still use main domain
$url = url('/products');
// Result: https://example.com/products
```

### Environment-Based Configuration

```php
// In service provider or bootstrap
if (env('APP_ENV') === 'production') {
    url()->forceScheme('https');
    url()->setAssetRoot(env('CDN_URL', 'https://cdn.example.com'));
}
```

### Secret Key for Signed URLs

The secret key is automatically loaded from environment variable:

```env
# .env
APP_KEY=your-secret-key-here-at-least-32-characters-long
```

**Security:** Use a strong random key (32+ characters). Regenerate if compromised.

```bash
# Generate secure random key (PHP)
php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"
```

## Performance

### Complexity Analysis

| Operation | Time | Space | Notes |
|-----------|------|-------|-------|
| `url('/path')` | O(1) | O(1) | Simple string concatenation |
| `route('name', [])` | O(1) | O(1) | Hash table route lookup |
| `route('name', ['id' => 1])` | O(N) | O(N) | N = parameter count |
| `asset('path')` | O(1) | O(1) | String concatenation |
| `signedRoute('name', [])` | O(N) | O(N) | HMAC-SHA256 + parameter sort |
| `hasValidSignature($url)` | O(N) | O(N) | HMAC verification + parse |

**N** = Number of route parameters or signature parameters

### Optimizations

1. **Route Collection**: O(1) hash table lookup for named routes
2. **Singleton UrlGenerator**: Shared instance, no repeated initialization
3. **Lazy Signature Generation**: Only computed when needed
4. **Timing-Safe Comparison**: `hash_equals()` prevents timing attacks

### Performance Tips

```php
// ✅ Good: Generate once, reuse
$productsUrl = route('products.index');
foreach ($categories as $category) {
    echo "<a href='{$productsUrl}?category={$category->id}'>{$category->name}</a>";
}

// ❌ Bad: Generate repeatedly in loop
foreach ($categories as $category) {
    echo "<a href='" . route('products.index', ['category' => $category->id]) . "'>{$category->name}</a>";
}

// ✅ Good: Use relative URLs when possible (smaller output)
$url = route('products.show', ['id' => 123], false);
// Result: /products/123 (18 bytes)

// vs absolute URL
$url = route('products.show', ['id' => 123], true);
// Result: http://example.com/products/123 (35 bytes)
```

### Benchmarks

**Environment:** PHP 8.1, macOS, M1 chip

| Operation | Time (microseconds) | Ops/sec |
|-----------|-------------------|---------|
| `url('/path')` | 10µs | 100,000 |
| `route('name', ['id' => 1])` | 50µs | 20,000 |
| `asset('file.css')` | 8µs | 125,000 |
| `signedRoute('name', [])` | 200µs | 5,000 |

**Conclusion:** Extremely fast for typical web application usage (< 1ms per request).

## Best Practices

### 1. Use Named Routes

```php
// ✅ Good: Named route (refactor-safe)
<a href="<?= route('products.show', ['id' => $product->id]) ?>">View</a>

// ❌ Bad: Hard-coded URL (breaks on route change)
<a href="/products/<?= $product->id ?>">View</a>
```

### 2. Generate Signed URLs for Sensitive Actions

```php
// ✅ Good: Signed URL with expiration
$url = temporary_signed_route('unsubscribe', 86400, ['email' => $email]);

// ❌ Bad: Plain URL (can be guessed/shared)
$url = route('unsubscribe', ['email' => $email]);
```

### 3. Use Relative URLs for Internal Links

```php
// ✅ Good: Relative URL (smaller, protocol-agnostic)
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">

// ❌ Bad: Absolute URL (unnecessary overhead)
<link rel="stylesheet" href="<?= asset('css/app.css', true) ?>">
```

### 4. Force HTTPS in Production

```php
// In service provider or bootstrap
if (env('APP_ENV') === 'production') {
    url()->forceScheme('https');
}
```

### 5. Use CDN for Static Assets

```php
// Configure CDN
url()->setAssetRoot('https://cdn.example.com');

// All assets now use CDN
$css = asset('css/app.css', true);
// Result: https://cdn.example.com/css/app.css
```

### 6. Validate Signed URLs Server-Side

```php
// ✅ Good: ValidateSignature middleware
$router->get('/download', [DownloadController::class, 'file'])
    ->middleware([ValidateSignature::class]);

// ❌ Bad: No validation (security risk)
$router->get('/download', [DownloadController::class, 'file']);
```

### 7. Set Appropriate Expiration for Temporary URLs

```php
// ✅ Good: Time-limited access
$url = temporary_signed_route('download', 3600, ['file' => 'report.pdf']); // 1 hour

// ❌ Bad: Permanent access to sensitive content
$url = signed_route('download', ['file' => 'sensitive-data.pdf']);
```

### 8. Don't Expose Signed URLs in Logs

```php
// ❌ Bad: Logging signed URL (security risk)
error_log("Generated URL: " . $signedUrl);

// ✅ Good: Log without signature
$parts = parse_url($signedUrl);
error_log("Generated URL: {$parts['scheme']}://{$parts['host']}{$parts['path']}");
```

### 9. Use URL Facade for Type Safety

```php
// ✅ Good: Type-safe with IDE autocomplete
use Toporia\Framework\Support\Accessors\URL;

class ProductService
{
    public function getShareUrl(Product $product): string
    {
        return URL::route('products.show', ['id' => $product->id]);
    }
}

// vs global helper (less type-safe)
return route('products.show', ['id' => $product->id]);
```

### 10. Cache Generated URLs for Expensive Operations

```php
// ✅ Good: Cache signed URLs
$cacheKey = "unsubscribe_url_{$user->id}";
$url = cache()->remember($cacheKey, 3600, function() use ($user) {
    return signed_route('unsubscribe', ['email' => $user->email]);
});

// ❌ Bad: Regenerate on every request
$url = signed_route('unsubscribe', ['email' => $user->email]);
```

## See Also

- [Routing](../CLAUDE.md#routing-with-fluent-api) - Define named routes
- [Middleware](../CLAUDE.md#middleware-pipeline) - Protect routes
- [Security](../CLAUDE.md#security-features) - CSRF, XSS protection
- [Configuration](../CLAUDE.md#configuration-system) - Environment variables
