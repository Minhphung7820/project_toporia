# Form Request Validation - Professional Validation System

Complete validation system inspired by **Laravel Form Requests**.

## Features

✅ **Auto-Validation** - Validates before controller method
✅ **20+ Built-in Rules** - email, required, min, max, numeric, etc.
✅ **Custom Messages** - Customize error messages per field
✅ **Authorization** - Built-in authorization support
✅ **Clean Code** - Separate validation logic from controllers
✅ **Type-Safe** - Full type hints and IDE support
✅ **Performance** - O(N*R) where N = fields, R = rules

---

## Quick Start

### 1. Create FormRequest

```php
<?php

namespace App\Presentation\Http\Requests;

use Toporia\Framework\Http\FormRequest;

final class CreateProductRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'email' => 'required|email',
            'sku' => 'string|max:100',
            'stock' => 'integer|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Product title is required.',
            'price.min' => 'Price cannot be negative.',
        ];
    }

    public function authorize(): bool
    {
        return auth()->check(); // Only authenticated users
    }
}
```

### 2. Use in Controller

```php
<?php

namespace App\Presentation\Http\Controllers;

use App\Presentation\Http\Requests\CreateProductRequest;

final class ProductsController
{
    // FormRequest is auto-validated before this method runs!
    public function store(CreateProductRequest $request)
    {
        // Validation already passed ✅
        $validated = $request->validated();

        $product = Product::create($validated);

        return response()->json($product, 201);
    }
}
```

**That's it!** 🎉 Validation happens automatically!

---

## How It Works

### Auto-Validation Flow

```
1. Request arrives
   ↓
2. Router resolves controller method
   ↓
3. Container sees FormRequest type-hint
   ↓
4. Container creates FormRequest instance
   ↓
5. Container calls validate() automatically ✨
   ↓
6. If validation fails → throw ValidationException (422)
   ↓
7. If validation passes → call controller method
   ↓
8. Controller receives validated request
```

**Zero boilerplate!** Just type-hint and it works.

---

## Available Validation Rules

### Basic Rules

| Rule | Description | Example |
|------|-------------|---------|
| `required` | Field must be present and not empty | `'name' => 'required'` |
| `string` | Must be a string | `'name' => 'string'` |
| `numeric` | Must be numeric | `'age' => 'numeric'` |
| `integer` | Must be an integer | `'count' => 'integer'` |
| `boolean` | Must be boolean (true/false, 0/1) | `'active' => 'boolean'` |
| `array` | Must be an array | `'tags' => 'array'` |

### String Rules

| Rule | Description | Example |
|------|-------------|---------|
| `min:X` | Minimum length/value | `'name' => 'min:3'` |
| `max:X` | Maximum length/value | `'name' => 'max:255'` |
| `alpha` | Only letters (a-z, A-Z) | `'name' => 'alpha'` |
| `alpha_num` | Letters and numbers only | `'username' => 'alpha_num'` |
| `alpha_dash` | Letters, numbers, dashes, underscores | `'slug' => 'alpha_dash'` |

### Format Rules

| Rule | Description | Example |
|------|-------------|---------|
| `email` | Valid email address | `'email' => 'email'` |
| `url` | Valid URL | `'website' => 'url'` |
| `ip` | Valid IP address | `'ip_address' => 'ip'` |
| `regex:pattern` | Matches regex pattern | `'code' => 'regex:/^[A-Z]{3}$/'` |

### Comparison Rules

| Rule | Description | Example |
|------|-------------|---------|
| `in:val1,val2` | Value must be in list | `'status' => 'in:active,pending'` |
| `not_in:val1,val2` | Value must NOT be in list | `'role' => 'not_in:admin,root'` |
| `same:field` | Must match another field | `'password_confirm' => 'same:password'` |
| `different:field` | Must be different from field | `'new_email' => 'different:old_email'` |
| `confirmed` | Requires {field}_confirmation | `'password' => 'confirmed'` |

### Database Rules

| Rule | Description | Example |
|------|-------------|---------|
| `unique:table,column` | Must be unique in database | `'email' => 'unique:users,email'` |
| `unique:table,col,id,key` | Unique with ignore (for updates) | `'email' => 'unique:users,email,123,id'` |
| `exists:table,column` | Must exist in database | `'category_id' => 'exists:categories,id'` |

**Note:** Database rules require calling `Validator::setDatabase($db)` first. See [Custom Rules Guide](VALIDATION_CUSTOM_RULES.md) for details.

---

## Usage Examples

### Basic Validation

```php
final class RegisterRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|min:3|max:255',
            'email' => 'required|email',
            'password' => 'required|string|min:8',
            'password_confirmation' => 'required|same:password',
        ];
    }
}
```

### Custom Messages

```php
public function messages(): array
{
    return [
        'name.required' => 'Please enter your name.',
        'name.min' => 'Name must be at least 3 characters.',
        'email.email' => 'Please provide a valid email address.',
        'password.min' => 'Password must be at least 8 characters.',
    ];
}
```

### Authorization

```php
public function authorize(): bool
{
    // Only admins can create products
    return auth()->user()?->isAdmin() ?? false;
}
```

### Accessing Validated Data

```php
public function store(CreateProductRequest $request)
{
    // Get all validated data
    $all = $request->validated();

    // Get specific field
    $title = $request->input('title');

    // Get only specific fields
    $data = $request->only(['title', 'price']);

    // Get all except specific fields
    $data = $request->except(['_token']);

    // Check if field exists
    if ($request->has('sku')) {
        // ...
    }
}
```

---

## API Validation Responses

### Success (200/201)

```json
{
  "id": 1,
  "title": "New Product",
  "price": 99.99
}
```

### Validation Error (422)

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "title": [
      "The title field is required."
    ],
    "price": [
      "The price must be a number.",
      "The price must be at least 0."
    ],
    "email": [
      "The email must be a valid email address."
    ]
  }
}
```

---

## Advanced Usage

### Array Validation

```php
public function rules(): array
{
    return [
        'tags' => 'required|array',
        'tags.*' => 'string|max:50', // Each tag
    ];
}
```

### Conditional Validation

```php
public function rules(): array
{
    $rules = [
        'type' => 'required|in:physical,digital',
    ];

    // Add shipping rules only for physical products
    if (request()->input('type') === 'physical') {
        $rules['weight'] = 'required|numeric|min:0';
        $rules['dimensions'] = 'required|string';
    }

    return $rules;
}
```

### Multiple Rule Formats

```php
// String format (pipe-separated)
'email' => 'required|email|max:255'

// Array format
'email' => ['required', 'email', 'max:255']

// Mixed format
'email' => 'required|email', // Simple rules
'age' => ['required', 'integer', 'min:18', 'max:120'] // Complex
```

---

## Manual Validation (Without FormRequest)

If you don't want to use FormRequest, use Validator directly:

```php
use Toporia\Framework\Validation\Validator;

public function store(Request $request)
{
    $validator = new Validator();

    $passes = $validator->validate($request->all(), [
        'email' => 'required|email',
        'password' => 'required|min:8',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'errors' => $validator->errors()
        ], 422);
    }

    $validated = $validator->validated();
    // Process data...
}
```

---

## Performance

### Time Complexity

**Validation:** O(N * R)
- N = number of fields
- R = average rules per field

**Example:**
- 10 fields with 3 rules each = O(30) = O(1) effectively
- Very fast even for large forms

### Memory Usage

- Validator: ~1 KB
- FormRequest: ~2 KB
- Validated data: depends on data size
- **Total overhead: ~3-5 KB** (negligible)

### Optimization Tips

✅ **Good:**
```php
// Validate early
public function store(CreateProductRequest $request)
{
    // Validation already done ✅
    $product = Product::create($request->validated());
}
```

❌ **Bad:**
```php
// Manual validation in controller
public function store(Request $request)
{
    // Repetitive boilerplate ❌
    if (empty($request->input('title'))) {
        return response()->json(['error' => 'Title required'], 422);
    }
    // ... more validation
}
```

---

## Architecture

### Clean Architecture

```
Presentation Layer
    ↓
FormRequest (validates)
    ↓
Controller (uses validated data)
    ↓
Application Layer (Use Cases)
    ↓
Domain Layer (pure business logic)
```

### SOLID Principles

**Single Responsibility:**
- Validator: Only validates
- FormRequest: Only handles request validation
- Controller: Only handles HTTP logic

**Open/Closed:**
- Extend FormRequest for custom validation
- Add custom rules by extending Validator

**Liskov Substitution:**
- All FormRequests are interchangeable
- All Validators implement ValidatorInterface

**Interface Segregation:**
- ValidatorInterface: 5 focused methods
- FormRequest: Minimal abstract methods

**Dependency Inversion:**
- Depends on ValidatorInterface (abstraction)
- Not on concrete Validator implementation

---

## Custom Validation Rules

### Using Database Rules (Built-in)

**Setup Database Connection:**

```php
// Option 1: Auto-resolve (Recommended) ⭐
// Just register 'db' in container - validator auto-resolves!
$container->singleton('db', fn() => $dbManager->connection());

// Option 2: Set resolver (once)
Validator::setConnectionResolver(fn() => app('db'));

// Option 3: Set connection directly (object or name)
Validator::setConnection(app('db'));
Validator::setConnection('mysql');    // Connection name ⭐ NEW
```

**Create with Unique:**

```php
final class CreateUserRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email' => 'required|email|unique:users,email',
            'username' => 'required|string|unique:users,username',
        ];
    }
}
```

**Update with Ignore:**

```php
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
            // Ignore current user's email
            'email' => "required|email|unique:users,email,{$this->userId},id",
            'username' => "required|string|unique:users,username,{$this->userId},id",
        ];
    }
}
```

**Foreign Key Validation:**

```php
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

### Custom Rules with extend()

```php
use Toporia\Framework\Validation\Validator;

$validator = new Validator();

// Register custom rule
$validator->extend('phone', function ($value, $parameters, $data) {
    return preg_match('/^[0-9]{10,11}$/', $value) === 1;
}, 'The :field must be a valid phone number.');

// Use it
$validator->validate($data, [
    'phone' => 'required|phone',
]);
```

**See [Custom Rules Guide](VALIDATION_CUSTOM_RULES.md) for complete documentation.**

### Custom FormRequest Validation

```php
final class CreateProductRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'sku' => 'required|string',
        ];
    }

    public function validate(): void
    {
        parent::validate();

        // Custom validation
        if ($this->skuExists($this->input('sku'))) {
            throw new ValidationException([
                'sku' => ['SKU already exists']
            ]);
        }
    }

    private function skuExists(string $sku): bool
    {
        return Product::where('sku', $sku)->exists();
    }
}
```

---

## Error Handling

### In Development (APP_DEBUG=true)

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "email": ["The email must be a valid email address."],
    "password": ["The password must be at least 8 characters."]
  }
}
```

### In Production (APP_DEBUG=false)

Same format! Validation errors are **always shown** (they're user errors, not system errors).

---

## Testing

### Test Form Request

```php
public function testValidationPasses()
{
    $request = new CreateProductRequest(
        new Request([
            'title' => 'Test Product',
            'price' => 99.99,
            'email' => 'test@example.com',
        ])
    );

    $request->validate(); // Should not throw

    $this->assertEquals('Test Product', $request->input('title'));
}

public function testValidationFails()
{
    $request = new CreateProductRequest(
        new Request([
            'title' => '', // Empty - should fail
        ])
    );

    $this->expectException(ValidationException::class);
    $request->validate();
}
```

---

## Comparison with Laravel

| Feature | This Framework | Laravel |
|---------|---------------|---------|
| Auto-validation | ✅ | ✅ |
| 20+ rules | ✅ | ✅ (60+) |
| Custom messages | ✅ | ✅ |
| Authorization | ✅ | ✅ |
| FormRequest | ✅ | ✅ |
| Array validation | ✅ | ✅ |
| **Dependencies** | **0** | **10+** |
| **File size** | **~20 KB** | **~500 KB** |
| Performance | O(N*R) | O(N*R) |

**Same API, zero dependencies!**

---

## Files

**Framework:**
- [src/Framework/Validation/ValidatorInterface.php](../src/Framework/Validation/ValidatorInterface.php)
- [src/Framework/Validation/Validator.php](../src/Framework/Validation/Validator.php)
- [src/Framework/Http/FormRequest.php](../src/Framework/Http/FormRequest.php)
- [src/Framework/Http/ValidationException.php](../src/Framework/Http/ValidationException.php)

**Examples:**
- [src/App/Presentation/Http/Requests/CreateProductRequest.php](../src/App/Presentation/Http/Requests/CreateProductRequest.php)

**Integration:**
- [src/Framework/Container/Container.php](../src/Framework/Container/Container.php:238-241) - Auto-validation

---

## Summary

✅ **Professional validation** like Laravel
✅ **Auto-validation** before controller
✅ **20+ built-in rules**
✅ **Custom messages** per field
✅ **Authorization** built-in
✅ **Clean Architecture** + SOLID
✅ **High Performance** - O(N*R)
✅ **Zero dependencies**
✅ **Type-safe** with full IDE support

**Happy Validating! ✨**
