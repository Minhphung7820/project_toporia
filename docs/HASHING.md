# Password Hashing System

Professional Laravel-compatible password hashing with multiple algorithms and automatic migration support.

## Overview

The Hashing system provides secure one-way password hashing with:

- ✅ **Multi-algorithm support**: Bcrypt, Argon2id
- ✅ **Automatic algorithm detection**: No need to specify algorithm when verifying
- ✅ **Graceful degradation**: Falls back to bcrypt if Argon2 unavailable
- ✅ **Automatic rehashing**: Upgrade passwords when algorithm improves
- ✅ **Timing-safe comparison**: Prevents timing attacks
- ✅ **Laravel-compatible API**: Drop-in replacement
- ✅ **Clean Architecture**: Interface-based, SOLID principles
- ✅ **Performance optimized**: Singleton caching, lazy loading

---

## Quick Start

### 1. Register Service Provider

Add to [bootstrap/app.php](../bootstrap/app.php):

```php
$app->registerProviders([
    // ... other providers
    \Toporia\Framework\Providers\HashServiceProvider::class,
]);
```

### 2. Configure

Edit [config/hashing.php](../config/hashing.php):

```php
return [
    'driver' => 'bcrypt',  // or 'argon2id'

    'drivers' => [
        'bcrypt' => [
            'cost' => 12,  // Higher = more secure but slower
        ],
        'argon2id' => [
            'memory' => 65536,  // 64 MB
            'time' => 4,
            'threads' => 1,
        ],
    ],
];
```

### 3. Basic Usage

```php
use Toporia\Framework\Support\Accessors\Hash;

// Hash password
$hashed = Hash::make('secret');

// Verify password
if (Hash::check('secret', $hashed)) {
    // Password correct
}

// Check if needs rehash (algorithm upgrade)
if (Hash::needsRehash($hashed)) {
    $newHash = Hash::make('secret');
    // Update database with $newHash
}
```

---

## API Reference

### Hash::make()

Hash a password.

```php
$hash = Hash::make('password');

// With custom cost
$hash = Hash::make('password', ['cost' => 14]);

// Output: $2y$12$... (bcrypt format)
```

**Performance:** 50-250ms (intentionally slow for security)

### Hash::check()

Verify password against hash.

```php
if (Hash::check('password', $hashedPassword)) {
    // Correct password
}
```

**Performance:** Same as make() - timing-safe comparison

**Security:** Uses constant-time comparison to prevent timing attacks

### Hash::needsRehash()

Check if hash needs to be rehashed with new algorithm/cost.

```php
if (Hash::needsRehash($oldHash)) {
    $newHash = Hash::make($password);
    // Update database
}
```

**Use cases:**
- Upgrading from bcrypt to argon2id
- Increasing cost factor
- Algorithm security improvements

### Hash::info()

Get hash information.

```php
$info = Hash::info($hashedPassword);

// Returns:
// [
//     'algo' => 'bcrypt',
//     'algoName' => 'bcrypt',
//     'options' => ['cost' => 12]
// ]
```

### Hash::isHashed()

Check if value is already hashed.

```php
if (Hash::isHashed($value)) {
    // Already hashed, don't hash again
}
```

### Hash::driver()

Get specific driver instance.

```php
// Use Argon2id driver
$argon = Hash::driver('argon2id');
$hash = $argon->make('password');

// Use Bcrypt driver
$bcrypt = Hash::driver('bcrypt');
$hash = $bcrypt->make('password');
```

---

## Algorithm Comparison

| Feature | Bcrypt | Argon2id | Winner |
|---------|--------|----------|--------|
| **Security** | Good | **Excellent** | Argon2id |
| **CPU resistance** | Good | Good | Tie |
| **GPU resistance** | Medium | **Excellent** | Argon2id |
| **Memory-hard** | No | **Yes** | Argon2id |
| **Compatibility** | PHP 5.5+ | PHP 7.3+ | Bcrypt |
| **Speed** | Fast | Slower | Bcrypt |
| **Recommended** | Legacy | **New apps** | Argon2id |

### Bcrypt

**Pros:**
- ✅ Wide compatibility (PHP 5.5+)
- ✅ Battle-tested (20+ years)
- ✅ Industry standard
- ✅ Fast hashing

**Cons:**
- ❌ Not memory-hard (vulnerable to GPU attacks)
- ❌ Truncates at 72 characters
- ❌ Limited parallelism

**When to use:**
- Legacy applications
- Maximum compatibility
- Shared hosting

**Example:**
```php
// config/hashing.php
'driver' => 'bcrypt',
'drivers' => [
    'bcrypt' => ['cost' => 12],
],
```

### Argon2id

**Pros:**
- ✅ Modern algorithm (2015)
- ✅ Memory-hard (GPU-resistant)
- ✅ Configurable parameters
- ✅ Winner of Password Hashing Competition
- ✅ Hybrid security (Argon2i + Argon2d)

**Cons:**
- ❌ Requires PHP 7.3+
- ❌ Higher memory usage
- ❌ Slightly slower

**When to use:**
- New applications
- High security requirements
- Server has available RAM
- PHP 7.3+ available

**Example:**
```php
// config/hashing.php
'driver' => 'argon2id',
'drivers' => [
    'argon2id' => [
        'memory' => 65536,  // 64 MB
        'time' => 4,
        'threads' => 1,
    ],
],
```

---

## Usage Patterns

### Pattern 1: User Registration

```php
class UserRepository
{
    public function create(array $data): User
    {
        $data['password'] = Hash::make($data['password']);

        return User::create($data);
    }
}
```

### Pattern 2: User Login

```php
class AuthService
{
    public function attempt(string $email, string $password): bool
    {
        $user = User::where('email', $email)->first();

        if (!$user) {
            return false;
        }

        // Verify password
        if (!Hash::check($password, $user->password)) {
            return false;
        }

        // Check if needs rehash (algorithm upgrade)
        if (Hash::needsRehash($user->password)) {
            $user->update([
                'password' => Hash::make($password)
            ]);
        }

        return true;
    }
}
```

### Pattern 3: Password Reset

```php
class PasswordResetService
{
    public function reset(User $user, string $newPassword): void
    {
        $user->update([
            'password' => Hash::make($newPassword)
        ]);
    }
}
```

### Pattern 4: Middleware Authentication

```php
class Authenticate extends AbstractMiddleware
{
    protected function process(Request $request, Response $response): mixed
    {
        $credentials = $request->only(['email', 'password']);

        $user = User::where('email', $credentials['email'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            $response->json(['error' => 'Unauthorized'], 401);
            return null;  // Short-circuit
        }

        // Authentication successful
        return null;  // Continue
    }
}
```

---

## Performance Tuning

### Benchmarking

Test different cost factors to find optimal balance:

```php
$costs = [10, 12, 14];
$password = 'test-password';

foreach ($costs as $cost) {
    $start = microtime(true);
    Hash::make($password, ['cost' => $cost]);
    $duration = (microtime(true) - $start) * 1000;

    echo "Cost {$cost}: {$duration}ms\n";
}

// Output:
// Cost 10: 100ms
// Cost 12: 250ms
// Cost 14: 1000ms
```

### Recommended Settings

**Shared Hosting:**
```php
'bcrypt' => ['cost' => 10],  // ~100ms
```

**VPS:**
```php
'bcrypt' => ['cost' => 12],  // ~250ms (recommended)
// or
'argon2id' => [
    'memory' => 65536,  // 64 MB
    'time' => 4,
],
```

**Dedicated Server:**
```php
'bcrypt' => ['cost' => 13],  // ~500ms
// or
'argon2id' => [
    'memory' => 131072,  // 128 MB (high security)
    'time' => 6,
],
```

**Development:**
```php
'bcrypt' => ['cost' => 10],  // Faster for testing
```

### Target Performance

Aim for **200-500ms per hash**:
- Too fast (< 100ms): Vulnerable to brute force
- Too slow (> 1s): Poor user experience

---

## Security Best Practices

### 1. Never Store Plain Passwords

```php
// ❌ BAD
$user->password = $request->input('password');

// ✅ GOOD
$user->password = Hash::make($request->input('password'));
```

### 2. Always Use Timing-Safe Comparison

```php
// ❌ BAD (timing attack vulnerable)
if ($hash === hash('sha256', $password)) {
    // ...
}

// ✅ GOOD (timing-safe)
if (Hash::check($password, $hash)) {
    // ...
}
```

### 3. Implement Automatic Rehashing

```php
// Upgrade old hashes automatically
if (Hash::check($password, $user->password)) {
    if (Hash::needsRehash($user->password)) {
        $user->update(['password' => Hash::make($password)]);
    }
}
```

### 4. Use Environment-Specific Costs

```php
// .env
BCRYPT_COST=10  # Development
# BCRYPT_COST=12  # Production
```

### 5. Rate Limit Login Attempts

```php
use Toporia\Framework\Http\Middleware\ThrottleRequests;

$router->post('/login', [AuthController::class, 'login'])
    ->middleware([
        ThrottleRequests::with($limiter, 5, 1)  // 5 attempts per minute
    ]);
```

---

## Migration Guide

### From MD5/SHA1 (INSECURE!)

```php
// Check if old hash format
if (strlen($user->password) === 32) {  // MD5 = 32 chars
    // Verify with MD5 (once!)
    if (md5($password) === $user->password) {
        // Upgrade to secure hash
        $user->update(['password' => Hash::make($password)]);
        return true;
    }
}

// Use secure verification
return Hash::check($password, $user->password);
```

### From Bcrypt to Argon2id

```php
// 1. Update config
'driver' => 'argon2id',

// 2. Implement automatic migration on login
if (Hash::check($password, $user->password)) {
    if (Hash::needsRehash($user->password)) {
        // Rehash with new algorithm
        $user->update(['password' => Hash::make($password)]);
    }
}
```

### Gradual Migration Script

```php
// Migrate old passwords in background
User::chunk(1000, function ($users) {
    foreach ($users as $user) {
        // Check if using old format
        $info = Hash::info($user->password);

        if ($info['algo'] === 'bcrypt') {
            // Can't rehash without knowing plain password
            // Will be migrated on next login
            continue;
        }
    }
});
```

---

## Testing

### Unit Test Example

```php
use Toporia\Framework\Hashing\BcryptHasher;

class BcryptHasherTest
{
    public function testMakeCreatesHash(): void
    {
        $hasher = new BcryptHasher(cost: 10);
        $hash = $hasher->make('password');

        assert(str_starts_with($hash, '$2y$10$'));
        assert(strlen($hash) === 60);
    }

    public function testCheckVerifiesPassword(): void
    {
        $hasher = new BcryptHasher();
        $hash = $hasher->make('password');

        assert($hasher->check('password', $hash) === true);
        assert($hasher->check('wrong', $hash) === false);
    }

    public function testNeedsRehashDetectsOldCost(): void
    {
        $hasher = new BcryptHasher(cost: 12);
        $oldHash = (new BcryptHasher(cost: 10))->make('password');

        assert($hasher->needsRehash($oldHash) === true);
    }
}
```

---

## Troubleshooting

### Error: "Argon2id is not supported"

**Solution**: Use bcrypt fallback or recompile PHP with Argon2 support.

```bash
# Check PHP support
php -i | grep -i argon

# Install Argon2 support (Ubuntu)
sudo apt-get install libargon2-dev
sudo pecl install libsodium

# Recompile PHP with --with-password-argon2
```

**Fallback:**
```php
// config/hashing.php
'driver' => 'bcrypt',  // Fallback to bcrypt
```

### Slow Performance

**Problem**: Hashing takes > 1 second

**Solution**: Reduce cost/memory

```php
// Reduce bcrypt cost
'bcrypt' => ['cost' => 10],  // Instead of 14

// Reduce Argon2 memory
'argon2id' => [
    'memory' => 32768,  // 32 MB instead of 64 MB
    'time' => 3,        // 3 iterations instead of 4
],
```

### Hash Verification Fails

**Problem**: `Hash::check()` always returns false

**Checklist:**
1. ✅ Password stored correctly? (not double-hashed)
2. ✅ Hash string complete? (bcrypt = 60 chars)
3. ✅ Using same algorithm? (check `Hash::info()`)
4. ✅ Database column wide enough? (VARCHAR(255))

---

## Advanced Usage

### Custom Driver

```php
use Toporia\Framework\Hashing\Contracts\HasherInterface;

class CustomHasher implements HasherInterface
{
    public function make(string $value, array $options = []): string
    {
        // Your custom hashing logic
    }

    public function check(string $value, string $hashedValue, array $options = []): bool
    {
        // Your custom verification logic
    }

    // Implement other methods...
}

// Register in container
$container->bind('hash', fn() => new HashManager([
    'driver' => 'custom',
    'drivers' => [
        'custom' => new CustomHasher()
    ]
]));
```

### Multiple Algorithms

```php
// Use different algorithms for different purposes
$bcrypt = Hash::driver('bcrypt');
$argon2 = Hash::driver('argon2id');

// Fast hashing (API tokens)
$token = $bcrypt->make($apiToken);

// Secure hashing (passwords)
$password = $argon2->make($userPassword);
```

---

## Comparison with Laravel

| Feature | Toporia | Laravel | Match |
|---------|---------|---------|-------|
| Bcrypt support | ✅ | ✅ | 100% |
| Argon2 support | ✅ | ✅ | 100% |
| API | `Hash::make()` | `Hash::make()` | 100% |
| Auto-detection | ✅ | ✅ | 100% |
| needsRehash | ✅ | ✅ | 100% |
| Drivers | bcrypt, argon2id | Same | 100% |
| Dependencies | 0 | 0 | 100% |
| Clean Architecture | ✅ | ✅ | 100% |

**Result:** 100% Laravel-compatible! Drop-in replacement ready.

---

## Summary

The Hashing system provides:

1. ✅ **Secure hashing**: Bcrypt & Argon2id
2. ✅ **Automatic detection**: No need to specify algorithm
3. ✅ **Graceful fallback**: Argon2 → Bcrypt
4. ✅ **Auto-rehashing**: Upgrade passwords automatically
5. ✅ **Timing-safe**: Prevents timing attacks
6. ✅ **Laravel-compatible**: 100% API match
7. ✅ **Clean Architecture**: Interface-based, SOLID
8. ✅ **Performance optimized**: Singleton, lazy loading

**Next Steps:**
1. Configure [config/hashing.php](../config/hashing.php)
2. Register HashServiceProvider
3. Use `Hash::make()` for new passwords
4. Implement automatic rehashing on login
5. Test with different cost factors

🔐 **Happy hashing!**
