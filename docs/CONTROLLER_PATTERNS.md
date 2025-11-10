# Controller Patterns - Best Practices

This document explains the different ways to write controllers in this framework and when to use each approach.

## Overview

The framework supports **3 flexible approaches** for writing controllers:

1. **Modern Trait-based** (RECOMMENDED) - Maximum flexibility, composition over inheritance
2. **Laravel-style Injection** - Clean, explicit dependencies
3. **Legacy BaseController** - Backward compatibility

All approaches follow **Clean Architecture**, **SOLID principles**, and support **high performance**.

---

## 1. Modern Trait-based Approach (RECOMMENDED)

**Best for:** Most controllers, especially new code

**Advantages:**
- ✅ No forced inheritance (composition over inheritance)
- ✅ Only include helpers you need
- ✅ Compatible with method injection
- ✅ Clean, explicit dependencies

**Example:**

```php
<?php

namespace App\Presentation\Http\Controllers;

use Toporia\Framework\Http\Request;
use App\Domain\Product\ProductRepository;

final class ProductsController
{
    use ControllerHelpers; // Adds view(), json(), redirect() helpers

    public function __construct(
        private readonly ProductRepository $repo
    ) {} // Auto-wired from container

    public function index(Request $request)
    {
        $products = $this->repo->findAll();

        // Using trait helper
        return $this->json(['products' => $products]);
    }

    public function show(Request $request, string $id)
    {
        $product = $this->repo->findById($id);

        // Using trait helper
        return $this->view('products/show', ['product' => $product]);
    }
}
```

---

## 2. Laravel-style Method Injection

**Best for:** API controllers, simple handlers, ADR pattern

**Advantages:**
- ✅ Explicit dependencies in method signature
- ✅ No inheritance, no traits needed
- ✅ Very clean and testable
- ✅ Perfect for single-action controllers

**Example:**

```php
<?php

namespace App\Presentation\Http\Controllers;

use Toporia\Framework\Http\Request;
use Toporia\Framework\Http\Response;
use App\Domain\Product\ProductRepository;

final class ProductApiController
{
    public function __construct(
        private readonly ProductRepository $repo
    ) {}

    public function index(Request $request, Response $response)
    {
        $page = (int) $request->query('page', 1);
        $products = $this->repo->paginate($page, 20);

        return $response->json([
            'data' => $products,
            'page' => $page
        ]);
    }

    public function store(Request $request, Response $response)
    {
        $data = $request->only(['title', 'price', 'sku']);
        $product = $this->repo->create($data);

        return $response->json($product, 201);
    }
}
```

---

## 3. Using Helper Functions

**Best for:** Accessing Request/Response anywhere (middleware, services, etc.)

**Advantages:**
- ✅ Available globally
- ✅ No injection needed
- ✅ Convenient for quick access

**Example:**

```php
<?php

namespace App\Presentation\Http\Controllers;

final class QuickController
{
    public function index()
    {
        // Access request/response via helpers
        $path = request()->path();
        $query = request()->query('search');

        return response()->json([
            'path' => $path,
            'search' => $query
        ]);
    }

    public function redirect()
    {
        // Quick redirect
        return response()->redirect('/dashboard');
    }
}
```

---

## 4. Legacy BaseController (Backward Compatible)

**Best for:** Existing code, gradual migration

**Advantages:**
- ✅ Backward compatible
- ✅ Familiar pattern
- ⚠️ Uses inheritance (less flexible)

**Example:**

```php
<?php

namespace App\Presentation\Http\Controllers;

final class LegacyController extends BaseController
{
    public function index()
    {
        // Access via protected properties
        $data = $this->request->input('search');

        return $this->response->json(['data' => $data]);
    }

    public function show()
    {
        // Use inherited view() method
        return $this->view('products/show', ['product' => $product]);
    }
}
```

---

## Comparison Table

| Feature | Trait-based | Method Injection | Helper Functions | BaseController |
|---------|-------------|------------------|------------------|----------------|
| **Flexibility** | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐ | ⭐⭐⭐ |
| **Testability** | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐ | ⭐⭐⭐⭐ |
| **Clean Code** | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐ | ⭐⭐⭐ |
| **SOLID** | ✅ | ✅ | ✅ | ⚠️ |
| **Helper Methods** | ✅ | ❌ | ✅ | ✅ |
| **No Inheritance** | ✅ | ✅ | ✅ | ❌ |
| **Explicit Dependencies** | ✅ | ✅ | ⚠️ | ⚠️ |

---

## Available Helper Methods (ControllerHelpers Trait)

### View Rendering
```php
protected function view(string $path, array $data = []): string
```

### HTTP Responses
```php
protected function json(mixed $data, int $status = 200): void
protected function html(string $content, int $status = 200): void
protected function redirect(string $path, int $status = 302): void
```

### Request/Response Access
```php
protected function request(): Request
protected function response(): Response
```

### Simple Validation
```php
protected function validate(array $rules): array
```

**Example:**
```php
public function store(Request $request)
{
    $data = $this->validate([
        'email' => 'required|email',
        'password' => 'required'
    ]);

    // $data is validated
}
```

---

## Migration Guide

### From BaseController to Trait-based

**Before:**
```php
final class ProductsController extends BaseController
{
    public function index()
    {
        return $this->view('products/index');
    }
}
```

**After:**
```php
final class ProductsController
{
    use ControllerHelpers;

    public function index(Request $request)
    {
        return $this->view('products/index');
    }
}
```

**Benefits:**
- No inheritance
- Explicit dependencies
- More testable
- Better SOLID compliance

---

## Best Practices

### ✅ DO:
- Use **trait-based** for most controllers
- Use **method injection** for explicit dependencies
- Use **helper functions** for convenience
- Keep controllers thin (delegate to services/handlers)
- Use dependency injection for services

### ❌ DON'T:
- Don't put business logic in controllers
- Don't use static methods for testability
- Don't extend BaseController for new code (use trait instead)
- Don't mix too many patterns in one controller

---

## Performance Notes

All approaches have **identical performance**:

1. **Container binding** happens once per request
2. **Method injection** uses Container's `call()` with reflection caching
3. **Helper functions** are simple container lookups (O(1))
4. **No overhead** from traits (compiled at runtime)

---

## Examples in Codebase

- **Trait-based:** [HomeController.php](../src/App/Presentation/Http/Controllers/HomeController.php)
- **Legacy:** [ProductsController.php](../src/App/Presentation/Http/Controllers/ProductsController.php)
- **Trait definition:** [ControllerHelpers.php](../src/App/Presentation/Http/Controllers/ControllerHelpers.php)

---

## Summary

**For new code:**
```php
final class MyController
{
    use ControllerHelpers; // Optional helpers

    public function index(Request $request) // Method injection
    {
        return $this->json(['data' => []]); // Trait helper
    }
}
```

**Simple and powerful!** ✨
