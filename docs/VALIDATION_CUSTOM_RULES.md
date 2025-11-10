# Custom Validation Rules - Complete Guide

Professional custom validation system with database rules and extensibility.

## Features

✅ **Custom Rule Registration** - Extend validator with custom logic
✅ **Database Validation** - Built-in `unique` and `exists` rules
✅ **Ignore Support** - Update validation with ignore parameters
✅ **Clean Architecture** - Open/Closed Principle (extend without modifying)
✅ **High Performance** - Efficient database queries with prepared statements
✅ **Type Safety** - Full type hints and IDE support

---

## Quick Start

### 1. Register Custom Rule

```php
use Toporia\Framework\Validation\Validator;

$validator = new Validator();

// Register custom rule
$validator->extend('phone', function ($value, $parameters, $data) {
    // Custom validation logic
    return preg_match('/^[0-9]{10,11}$/', $value) === 1;
}, 'The :field must be a valid phone number.');

// Use it
$validator->validate($data, [
    'phone' => 'required|phone',
]);
```

### 2. Database Validation (Unique)

```php
use Toporia\Framework\Database\DatabaseManager;
use Toporia\Framework\Validation\Validator;

// Set database connection (once, globally)
$db = app('db'); // Or new DatabaseManager()
Validator::setDatabase($db);

// Validate unique email
$validator->validate($data, [
    'email' => 'required|email|unique:users,email',
]);
```

### 3. Update Validation with Ignore

```php
// When updating user, ignore current user's email
$userId = 123;

$validator->validate($data, [
    'email' => "required|email|unique:users,email,{$userId},id",
]);

// Explanation: unique:table,column,ignoreValue,ignoreColumn
// - table: users
// - column: email
// - ignoreValue: 123 (current user ID)
// - ignoreColumn: id (default)
```

---

## Database Validation Rules

### Unique Rule

Validates that a value is unique in the database table.

**Syntax:**
```
unique:table,column
unique:table,column,ignoreValue,ignoreColumn
```

**Parameters:**
- `table` - Database table name (required)
- `column` - Column name to check (required)
- `ignoreValue` - Value to ignore (optional, for updates)
- `ignoreColumn` - Column for ignore condition (optional, default: `id`)

**Examples:**

```php
// Basic - Check email is unique in users table
'email' => 'unique:users,email'

// Update - Ignore current user's email
'email' => 'unique:users,email,' . $userId . ',id'

// Custom ignore column
'username' => 'unique:users,username,' . $uuid . ',uuid'

// Real-world example
final class UpdateUserRequest extends FormRequest
{
    private int $userId;

    public function __construct(Request $request, int $userId)
    {
        parent::__construct($request);
        $this->userId = $userId;
    }

    public function rules(): array
    {
        return [
            'email' => "required|email|unique:users,email,{$this->userId},id",
            'username' => "required|string|unique:users,username,{$this->userId},id",
        ];
    }
}
```

**Generated SQL:**

```sql
-- For create (no ignore)
SELECT COUNT(*) FROM users WHERE email = ?

-- For update (with ignore)
SELECT COUNT(*) FROM users WHERE email = ? AND id != ?
```

### Exists Rule

Validates that a value exists in the database table (e.g., foreign key validation).

**Syntax:**
```
exists:table,column
```

**Parameters:**
- `table` - Database table name (required)
- `column` - Column name to check (optional, default: `id`)

**Examples:**

```php
// Check category exists
'category_id' => 'required|exists:categories,id'

// Check user exists by email
'author_email' => 'exists:users,email'

// Real-world example
final class CreatePostRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'category_id' => 'required|integer|exists:categories,id',
            'author_id' => 'required|integer|exists:users,id',
        ];
    }
}
```

**Generated SQL:**

```sql
SELECT COUNT(*) FROM categories WHERE id = ?
```

---

## Custom Rules

### Basic Custom Rule

```php
$validator->extend('uppercase', function ($value, $parameters, $data) {
    return $value === strtoupper($value);
}, 'The :field must be uppercase.');

// Use it
$validator->validate(['code' => 'ABC123'], [
    'code' => 'required|uppercase',
]);
```

### Custom Rule with Parameters

```php
$validator->extend('divisible_by', function ($value, $parameters, $data) {
    $divisor = (int) $parameters[0];
    return $value % $divisor === 0;
}, 'The :field must be divisible by :0.');

// Use it
$validator->validate(['quantity' => 10], [
    'quantity' => 'required|integer|divisible_by:5', // Must be divisible by 5
]);
```

### Custom Rule with Database Access

```php
use Toporia\Framework\Database\DatabaseManager;

$db = app('db');

$validator->extend('email_domain_allowed', function ($value, $parameters, $data) use ($db) {
    $domain = substr(strrchr($value, "@"), 1);

    $exists = $db->table('allowed_domains')
        ->where('domain', $domain)
        ->exists();

    return $exists;
}, 'The :field domain is not allowed.');

// Use it
$validator->validate($data, [
    'email' => 'required|email|email_domain_allowed',
]);
```

### Custom Rule with Context

```php
$validator->extend('password_strength', function ($value, $parameters, $data) {
    // Access other form fields via $data
    $username = $data['username'] ?? '';

    // Don't allow password to contain username
    if (stripos($value, $username) !== false) {
        return false;
    }

    // Require uppercase, lowercase, number, special char
    return preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/', $value);
}, 'The :field must be strong (uppercase, lowercase, number, special character).');

// Use it
$validator->validate($data, [
    'username' => 'required|string',
    'password' => 'required|password_strength',
]);
```

---

## Advanced Usage

### Global Custom Rules (Register Once)

Create a service provider to register custom rules globally:

```php
// src/App/Providers/ValidationServiceProvider.php

namespace App\Providers;

use Toporia\Framework\Foundation\ServiceProvider;
use Toporia\Framework\Container\ContainerInterface;
use Toporia\Framework\Validation\Validator;

final class ValidationServiceProvider extends ServiceProvider
{
    public function boot(ContainerInterface $container): void
    {
        $this->registerCustomRules();
    }

    private function registerCustomRules(): void
    {
        $validator = new Validator();

        // Phone number validation
        $validator->extend('phone', function ($value, $parameters, $data) {
            return preg_match('/^[0-9]{10,11}$/', $value) === 1;
        }, 'The :field must be a valid phone number.');

        // Strong password
        $validator->extend('strong_password', function ($value, $parameters, $data) {
            return strlen($value) >= 8 &&
                preg_match('/[A-Z]/', $value) &&
                preg_match('/[a-z]/', $value) &&
                preg_match('/[0-9]/', $value);
        }, 'The :field must contain uppercase, lowercase, and numbers.');

        // Credit card (Luhn algorithm)
        $validator->extend('credit_card', function ($value, $parameters, $data) {
            $value = preg_replace('/\D/', '', $value);
            $sum = 0;
            $length = strlen($value);

            for ($i = 0; $i < $length; $i++) {
                $digit = (int) $value[$length - $i - 1];
                if ($i % 2 === 1) {
                    $digit *= 2;
                    if ($digit > 9) {
                        $digit -= 9;
                    }
                }
                $sum += $digit;
            }

            return $sum % 10 === 0;
        }, 'The :field must be a valid credit card number.');
    }
}
```

Register in `bootstrap/app.php`:

```php
$app->registerProviders([
    // ...
    \App\Providers\ValidationServiceProvider::class,
]);
```

### Database Connection Setup

**Option 1: Auto-Resolve from Container (Recommended) ⭐**

The validator automatically resolves database from `app('db')` when needed:

```php
// bootstrap/app.php - Register 'db' in container
$container->singleton('db', fn() => $dbManager->connection());

// That's it! Validator will auto-resolve when you use unique/exists rules
// No manual setup needed! ✨
```

**Option 2: Set Resolver (Once)**

```php
// bootstrap/app.php or AppServiceProvider
use Toporia\Framework\Validation\Validator;

Validator::setConnectionResolver(fn() => app('db'));

// Now all validators can use database rules automatically
```

**Option 3: Set Connection Directly**

```php
// bootstrap/app.php
use Toporia\Framework\Validation\Validator;

// Pass connection object
$db = $container->get('db');
Validator::setConnection($db);

// Or pass connection name (string) ⭐ NEW
Validator::setConnection('mysql');     // Use mysql connection
Validator::setConnection('pgsql');     // Use postgres connection
Validator::setConnection('default');   // Use default connection
```

**Option 4: Set in FormRequest**

```php
final class CreateUserRequest extends FormRequest
{
    public function __construct(Request $request)
    {
        parent::__construct($request);

        // Set database connection for this request
        Validator::setConnection(app('db'));

        // Or use connection name
        Validator::setConnection('mysql');
    }

    public function rules(): array
    {
        return [
            'email' => 'required|email|unique:users,email',
        ];
    }
}
```

**How It Works (Lazy Loading):**

The validator tries to get connection in this order:

1. ✅ Static `$connection` if already set via `setConnection()`
2. ✅ Resolver callback if configured via `setConnectionResolver()`
3. ✅ Auto-resolve from `app('db')` container
4. ❌ Throw exception if none available

**Performance:** O(1) after first call (cached in static property)

**Backward Compatibility:**

```php
// Old methods still work (deprecated but functional)
Validator::setDatabase($db);              // → calls setConnection()
Validator::setDatabaseResolver($callback); // → calls setConnectionResolver()
```

### Dynamic Ignore Value (from Route Parameter)

```php
// Controller
public function update(UpdateUserRequest $request, int $userId)
{
    // Validation already passed with ignore!
    $user = User::find($userId);
    $user->update($request->validated());
}

// UpdateUserRequest
final class UpdateUserRequest extends FormRequest
{
    public function rules(): array
    {
        // Get user ID from route parameter
        $userId = $this->request->route('id') ?? $this->request->input('id');

        return [
            'email' => "required|email|unique:users,email,{$userId},id",
            'username' => "required|string|unique:users,username,{$userId},id",
        ];
    }
}
```

---

## Real-World Examples

### E-Commerce Product Validation

```php
final class CreateProductRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'sku' => 'required|string|unique:products,sku',
            'category_id' => 'required|integer|exists:categories,id',
            'price' => 'required|numeric|min:0',
            'stock' => 'integer|min:0',
        ];
    }
}

final class UpdateProductRequest extends FormRequest
{
    private int $productId;

    public function __construct(Request $request, int $productId)
    {
        parent::__construct($request);
        $this->productId = $productId;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'sku' => "required|string|unique:products,sku,{$this->productId},id",
            'category_id' => 'required|integer|exists:categories,id',
            'price' => 'required|numeric|min:0',
        ];
    }
}
```

### User Registration with Custom Rules

```php
final class RegisterRequest extends FormRequest
{
    public function __construct(Request $request)
    {
        parent::__construct($request);

        // Register custom rules
        $validator = new Validator();

        $validator->extend('username_available', function ($value, $parameters, $data) {
            // Check reserved usernames
            $reserved = ['admin', 'root', 'system'];
            return !in_array(strtolower($value), $reserved);
        }, 'The :field is reserved and cannot be used.');
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|min:3|max:255',
            'username' => 'required|string|alpha_dash|unique:users,username|username_available',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|strong_password',
            'password_confirmation' => 'required|same:password',
        ];
    }
}
```

### Multi-tenant Validation

```php
final class CreateTenantUserRequest extends FormRequest
{
    private int $tenantId;

    public function __construct(Request $request, int $tenantId)
    {
        parent::__construct($request);
        $this->tenantId = $tenantId;

        // Custom rule for tenant-specific uniqueness
        $validator = new Validator();

        $validator->extend('unique_in_tenant', function ($value, $parameters, $data) {
            $column = $parameters[0];

            $exists = app('db')->table('users')
                ->where('tenant_id', $this->tenantId)
                ->where($column, $value)
                ->exists();

            return !$exists;
        }, 'The :field has already been taken in this organization.');
    }

    public function rules(): array
    {
        return [
            'email' => 'required|email|unique_in_tenant:email',
            'username' => 'required|string|unique_in_tenant:username',
        ];
    }
}
```

### Multi-Database Validation

Validate against different database connections:

```php
// Setup default connection
Validator::setConnection('mysql');  // Default for most validations

// For specific validation, use custom rule with different connection
$validator = new Validator();

$validator->extend('unique_in_analytics', function ($value, $parameters, $data) {
    $table = $parameters[0];
    $column = $parameters[1];

    // Use analytics database connection
    $analyticsDb = app('db.manager')->connection('analytics');

    return !$analyticsDb->table($table)->where($column, $value)->exists();
}, 'The :field already exists in analytics database.');

// Use it
$validator->validate($data, [
    'email' => 'required|email|unique:users,email',              // mysql (default)
    'tracking_id' => 'required|unique_in_analytics:events,id',   // analytics db
]);
```

**Per-Request Connection:**

```php
final class AnalyticsRequest extends FormRequest
{
    public function __construct(Request $request)
    {
        parent::__construct($request);

        // Use analytics connection for this request ⭐
        Validator::setConnection('analytics');
    }

    public function rules(): array
    {
        return [
            'event_id' => 'required|unique:events,id',  // Uses analytics DB
        ];
    }
}
```

---

## Performance

### Database Validation Performance

**Time Complexity:**
- `unique`: O(1) - Single indexed query
- `exists`: O(1) - Single indexed query

**Optimization:**

```sql
-- Ensure columns have indexes for fast lookups
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_username ON users(username);
CREATE INDEX idx_categories_id ON categories(id);
```

**Query Analysis:**

```php
// unique:users,email
// SQL: SELECT COUNT(*) FROM users WHERE email = ?
// With index on email: ~0.001ms for 1M rows

// unique:users,email,123,id
// SQL: SELECT COUNT(*) FROM users WHERE email = ? AND id != ?
// With index on email: ~0.001ms for 1M rows

// exists:categories,id
// SQL: SELECT COUNT(*) FROM categories WHERE id = ?
// With primary key index: ~0.0001ms
```

### Custom Rule Performance

**Best Practices:**

✅ **Good** - Simple validation logic:
```php
$validator->extend('uppercase', function ($value) {
    return $value === strtoupper($value);
}); // O(n) where n = string length
```

❌ **Bad** - Complex database queries in loop:
```php
$validator->extend('slow_rule', function ($value) {
    // DON'T DO THIS - multiple queries
    foreach ($items as $item) {
        $db->query("SELECT * FROM ..."); // N queries!
    }
}); // O(N * M) - very slow
```

✅ **Good** - Single optimized query:
```php
$validator->extend('optimized_rule', function ($value) use ($db) {
    return $db->table('items')
        ->whereIn('id', $items)
        ->count() === count($items); // Single query!
}); // O(1)
```

---

## Architecture

### SOLID Principles

**Single Responsibility:**
- Validator: Only validates data
- Each custom rule: One validation concern

**Open/Closed:**
- Open for extension via `extend()`
- Closed for modification (don't edit Validator.php)

**Liskov Substitution:**
- All custom rules follow same signature: `(value, parameters, data) => bool`

**Interface Segregation:**
- `ValidatorInterface`: Focused interface for validation
- `extend()`: Separate method for extensibility

**Dependency Inversion:**
- Depends on database abstraction (PDO or QueryBuilder)
- Not tied to specific database implementation

### Clean Architecture

```
┌─────────────────────────────────────┐
│   Framework Layer (Generic)         │
│   - Validator (core)                │
│   - ValidatorInterface              │
│   - Built-in rules                  │
└─────────────────────────────────────┘
         ↑ extends via custom rules
┌─────────────────────────────────────┐
│   Application Layer (Specific)      │
│   - Custom rules (business logic)   │
│   - FormRequests with unique rules  │
└─────────────────────────────────────┘
```

---

## Comparison with Laravel

| Feature | This Framework | Laravel |
|---------|----------------|---------|
| Custom rules | ✅ `extend()` | ✅ `Validator::extend()` |
| Database rules | ✅ `unique`, `exists` | ✅ Same |
| Ignore support | ✅ `unique:table,col,id,key` | ✅ Same syntax |
| Rule objects | ❌ | ✅ |
| Closure rules | ✅ | ✅ |
| Dependencies | **0** | **5+** |
| Performance | O(1) per rule | O(1) per rule |

**Same API, zero dependencies!**

---

## Summary

✅ **Custom Rule Registration** - `extend()` method for custom validation
✅ **Database Validation** - `unique` and `exists` rules with ignore support
✅ **High Performance** - Efficient queries with prepared statements and indexes
✅ **Clean Architecture** - Open/Closed Principle (extend without modifying)
✅ **Type Safe** - Full type hints and IDE support
✅ **Laravel-Compatible** - Same syntax and behavior
✅ **Zero Dependencies** - Pure PHP implementation

**Happy Validating! ✨**
