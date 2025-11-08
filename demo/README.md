# Security Features Demo

Các file demo để hiểu cách sử dụng các tính năng bảo mật trong framework.

## Chạy Demo

```bash
# CSRF Protection
php demo/csrf_demo_simple.php

# XSS Protection
php demo/xss_demo.php

# Rate Limiting
php demo/ratelimit_demo_simple.php

# Integration (tất cả features)
php demo/integration_demo.php
```

## Nội dung từng Demo

### 1. CSRF Protection (`csrf_demo_simple.php`)

Demonstrates:
- ✓ Tạo và validate CSRF token
- ✓ Sử dụng trong HTML forms
- ✓ Sử dụng trong AJAX requests
- ✓ Regenerate token (sau khi login)
- ✓ Remove token
- ✓ Middleware usage trong routes
- ✓ Security best practices

**Key Points:**
- Token được lưu trong session (không phải cookies)
- Sử dụng `hash_equals()` cho timing-safe comparison
- Token length: 64 characters
- Middleware tự động validate POST/PUT/PATCH/DELETE requests

### 2. XSS Protection (`xss_demo.php`)

Demonstrates:
- ✓ `escape()` - Basic HTML escaping cho output
- ✓ `clean()` - Remove tất cả HTML tags
- ✓ `sanitize()` - Allow specific safe tags
- ✓ `purify()` - Rich text cleaning
- ✓ Context-specific escaping (JavaScript, URL)
- ✓ `cleanArray()` - Clean nested arrays
- ✓ Security headers middleware
- ✓ Practical usage trong controllers và views

**Key Points:**
- Luôn escape output: `e($variable)`
- Dùng `clean()` cho plain text fields
- Dùng `purify()` cho rich content (blog posts)
- Security headers tự động apply qua global middleware

### 3. Rate Limiting (`ratelimit_demo_simple.php`)

Demonstrates:
- ✓ Basic rate limiting (5 requests per 60 seconds)
- ✓ Different strategies (per user, per IP, per action)
- ✓ Check status without incrementing
- ✓ Clear rate limits (admin action)
- ✓ Middleware usage trong routes
- ✓ Response headers (X-RateLimit-*)
- ✓ Cache drivers comparison
- ✓ Best practices

**Key Points:**
- Dùng Redis trong production cho accuracy
- Lower limits cho sensitive operations (login, password reset)
- Higher limits cho authenticated users
- Clear rate limit khi login thành công

### 4. Integration (`integration_demo.php`)

Shows complete integration:
- ✓ Routes configuration với multiple middleware
- ✓ Controller implementations
- ✓ View templates với XSS protection
- ✓ Middleware configuration
- ✓ Security configuration
- ✓ Complete request flow
- ✓ Testing examples
- ✓ Best practices summary

**Key Points:**
- Combine CSRF + XSS + Rate Limiting
- Defense in depth approach
- Clear separation of concerns
- Production-ready examples

## Quick Reference

### CSRF Protection

```php
// In forms
<?= csrf_field() ?>

// In AJAX
headers: {
    'X-CSRF-TOKEN': '<?= csrf_token() ?>'
}

// In routes
$router->post('/submit', [Controller::class, 'method'])
    ->middleware(['csrf']);
```

### XSS Protection

```php
// Escape output
<?= e($userInput) ?>

// Clean input (plain text)
$username = clean($request->input('username'));

// Sanitize (allow some tags)
$comment = XssProtection::sanitize($input, '<p><b><i>');

// Purify (rich content)
$content = XssProtection::purify($request->input('content'));
```

### Rate Limiting

```php
// In routes
$router->post('/login', [AuthController::class, 'login'])
    ->middleware(['throttle:5,15']); // 5 attempts per 15 min

// In controllers
if ($limiter->tooManyAttempts($key, 5)) {
    return $response->json(['error' => 'Too many attempts'], 429);
}

$limiter->attempt($key, 5, 900);
```

## Configuration Files

- `config/security.php` - CSRF, headers, cookies settings
- `config/cache.php` - Cache drivers (File, Redis, Memory)
- `config/middleware.php` - Middleware aliases

## Documentation

- [CLAUDE.md](../CLAUDE.md) - Framework guide
- [FEATURES.md](../FEATURES.md) - Complete feature list
- [EXAMPLES.md](../EXAMPLES.md) - Code examples
- [INSTALLATION.md](../INSTALLATION.md) - Setup guide

## Production Checklist

Security:
- [ ] Enable CSRF protection cho tất cả state-changing requests
- [ ] Escape tất cả user-generated output
- [ ] Enable security headers (CSP, HSTS, X-Frame-Options)
- [ ] Set secure cookies (HttpOnly, Secure, SameSite)
- [ ] Use HTTPS trong production

Rate Limiting:
- [ ] Use Redis cache (không dùng Memory hoặc File)
- [ ] Set appropriate limits per endpoint
- [ ] Monitor rate limit violations
- [ ] Log suspicious activity

Testing:
- [ ] Test CSRF protection (valid/invalid tokens)
- [ ] Test XSS prevention (script injection attempts)
- [ ] Test rate limiting (rapid requests)
- [ ] Test with security scanner (OWASP ZAP, Burp Suite)

## Common Mistakes to Avoid

❌ **CSRF:**
- Không validate GET requests (GET không cần CSRF)
- Log CSRF tokens (keep them secret)
- Share tokens between users

❌ **XSS:**
- Trust user input
- Double-escape output
- Forget context-specific escaping

❌ **Rate Limiting:**
- Use MemoryCache trong production
- Same limit cho tất cả operations
- Không clear limit khi login success

## Advanced Usage

### Custom Rate Limit Keys

```php
// Per email (login attempts)
$key = "login:{$email}";

// Per user + action
$key = "api:user:{$userId}:endpoint";

// Per IP + endpoint
$key = "api:ip:{$ip}:{$endpoint}";

// Global
$key = "global:contact-form";
```

### Multiple Middleware Layers

```php
$router->post('/admin/delete/{id}', [AdminController::class, 'delete'])
    ->middleware([
        'auth',              // Must be logged in
        'authorize:admin',   // Must be admin
        'csrf',              // CSRF protection
        'throttle:10,60'     // Rate limited
    ]);
```

### Conditional XSS Protection

```php
// For admin (allow more tags)
if ($user->isAdmin()) {
    $content = XssProtection::sanitize($input, null, false); // Rich tags
} else {
    $content = XssProtection::sanitize($input); // Basic tags only
}
```

## Support

Nếu có vấn đề hoặc câu hỏi:
1. Check [CLAUDE.md](../CLAUDE.md) documentation
2. Review [EXAMPLES.md](../EXAMPLES.md) code examples
3. Run demo files để hiểu cách hoạt động
4. Check source code trong `src/Framework/`
