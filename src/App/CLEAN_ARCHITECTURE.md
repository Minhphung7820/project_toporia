# Clean Architecture Implementation Guide

This document explains how Clean Architecture is implemented in the Application layer.

## Directory Structure

```
src/App/
├── Domain/                      # Domain Layer (Core Business Logic)
│   ├── Product/
│   │   ├── Product.php         # Domain Entity (Pure PHP, immutable)
│   │   └── ProductRepository.php  # Repository Interface
│   └── User/
│       ├── User.php            # Domain Entity
│       └── UserRepository.php  # Repository Interface
│
├── Application/                 # Application Layer (Use Cases)
│   └── Product/
│       └── CreateProduct/
│           ├── CreateProductCommand.php   # DTO (Data Transfer Object)
│           └── CreateProductHandler.php   # Use Case Handler
│
├── Infrastructure/              # Infrastructure Layer (External Dependencies)
│   └── Persistence/
│       ├── Models/             # ORM Models (Active Record)
│       │   ├── ProductModel.php   # Database Model
│       │   └── UserModel.php      # Database Model
│       ├── EloquentProductRepository.php  # Repository Implementation
│       └── InMemoryProductRepository.php  # Alternative Implementation
│
├── Presentation/                # Presentation Layer (UI/API)
│   ├── Http/
│   │   ├── Controllers/       # HTTP Controllers
│   │   ├── Middleware/        # HTTP Middleware
│   │   └── Action/            # ADR Pattern Actions
│   ├── Console/               # CLI Commands
│   └── Views/                 # View Templates
│
├── Providers/                   # Service Providers (Dependency Injection)
│   ├── AppServiceProvider.php
│   ├── RepositoryServiceProvider.php  # Binds Interfaces to Implementations
│   └── RouteServiceProvider.php
│
├── Jobs/                        # Background Jobs
├── Notifications/               # Notification Classes
├── Mails/                       # Email Classes
└── Observers/                   # Model Observers
```

## Layer Responsibilities

### 1. Domain Layer (Core Business Logic)

**Location**: `src/App/Domain/`

**Characteristics**:
- ✅ **Zero Dependencies** - No framework dependencies
- ✅ **Immutable Entities** - Use `readonly` properties
- ✅ **Pure Business Logic** - Only business rules
- ✅ **Repository Interfaces** - Define contracts, not implementations

**Example - Product Entity**:
```php
// src/App/Domain/Product/Product.php
namespace App\Domain\Product;

final class Product {
    public function __construct(
        public readonly ?int $id,
        public string $title,
        public ?string $sku,
    ) {}
}
```

**Example - Repository Interface**:
```php
// src/App/Domain/Product/ProductRepository.php
namespace App\Domain\Product;

interface ProductRepository {
    public function store(Product $product): Product;
    public function findById(int $id): ?Product;
}
```

**❌ WRONG - Do NOT put these in Domain**:
- ❌ ORM Models (ProductModel) - Depends on framework
- ❌ Database details (table names, connections)
- ❌ External services (Elasticsearch, S3)
- ❌ HTTP requests/responses

---

### 2. Application Layer (Use Cases)

**Location**: `src/App/Application/`

**Characteristics**:
- ✅ **Command/Query Pattern** - Separate reads from writes
- ✅ **Handler Pattern** - One handler per use case
- ✅ **DTOs** - Data Transfer Objects for input
- ✅ **Orchestration** - Coordinates between Domain and Infrastructure

**Example - Command (DTO)**:
```php
// src/App/Application/Product/CreateProduct/CreateProductCommand.php
namespace App\Application\Product\CreateProduct;

final class CreateProductCommand {
    public function __construct(
        public readonly string $title,
        public readonly ?string $sku = null
    ) {}
}
```

**Example - Handler (Use Case)**:
```php
// src/App/Application/Product/CreateProduct/CreateProductHandler.php
namespace App\Application\Product\CreateProduct;

use App\Domain\Product\Product;
use App\Domain\Product\ProductRepository;

final class CreateProductHandler {
    public function __construct(
        private readonly ProductRepository $repository
    ) {}

    public function __invoke(CreateProductCommand $cmd): Product {
        $product = new Product(
            id: null,
            title: $cmd->title,
            sku: $cmd->sku
        );

        return $this->repository->store($product);
    }
}
```

**Flow**:
1. Receives Command (DTO)
2. Creates Domain Entity
3. Uses Repository Interface (not implementation)
4. Returns Domain Entity

---

### 3. Infrastructure Layer (External Dependencies)

**Location**: `src/App/Infrastructure/`

**Characteristics**:
- ✅ **Implements Repository Interfaces** - From Domain layer
- ✅ **Database Access** - ORM Models live here
- ✅ **External Services** - Elasticsearch, S3, APIs
- ✅ **Converts** - Between Domain Entities and ORM Models

**Example - ORM Model (Infrastructure)**:
```php
// src/App/Infrastructure/Persistence/Models/ProductModel.php
namespace App\Infrastructure\Persistence\Models;

use Toporia\Framework\Database\ORM\Model;
use Toporia\Framework\Search\Searchable;

class ProductModel extends Model {
    use Searchable;  // ✅ OK - External service integration

    protected static string $table = 'products';  // ✅ OK - Database detail
}
```

**Example - Repository Implementation**:
```php
// src/App/Infrastructure/Persistence/EloquentProductRepository.php
namespace App\Infrastructure\Persistence;

use App\Domain\Product\{Product, ProductRepository};
use App\Infrastructure\Persistence\Models\ProductModel;

final class EloquentProductRepository implements ProductRepository {
    public function store(Product $product): Product {
        // Convert Domain Entity -> ORM Model
        $model = ProductModel::create([
            'title' => $product->title,
            'sku' => $product->sku,
        ]);

        // Convert ORM Model -> Domain Entity
        return new Product(
            id: $model->id,
            title: $model->title,
            sku: $model->sku
        );
    }

    public function findById(int $id): ?Product {
        $model = ProductModel::find($id);

        if (!$model) return null;

        // Convert ORM Model -> Domain Entity
        return new Product(
            id: $model->id,
            title: $model->title,
            sku: $model->sku
        );
    }
}
```

**Key Points**:
- ✅ **Models/** subfolder - Contains all ORM Models
- ✅ **Conversion Layer** - Converts between Entity and Model
- ✅ **Implements Interface** - From Domain layer
- ✅ **Multiple Implementations** - Can have InMemory, Eloquent, PDO, etc.

---

### 4. Presentation Layer (UI/Controllers)

**Location**: `src/App/Presentation/`

**Characteristics**:
- ✅ **Thin Controllers** - Only handle HTTP concerns
- ✅ **Dependency Injection** - Inject Handlers, not Repositories
- ✅ **Use Handlers** - Don't access Models directly
- ✅ **HTTP Concerns Only** - Request/Response formatting

**Example - Controller (Clean Architecture)**:
```php
// src/App/Presentation/Http/Controllers/ProductsController.php
namespace App\Presentation\Http\Controllers;

use App\Application\Product\CreateProduct\{CreateProductCommand, CreateProductHandler};
use App\Domain\Product\ProductRepository;

final class ProductsController extends BaseController {
    public function __construct(
        private readonly CreateProductHandler $createHandler,
        private readonly ProductRepository $productRepository
    ) {
        parent::__construct();
    }

    public function store(): void {
        $payload = $this->request->input();

        // Create Command (Application Layer DTO)
        $cmd = new CreateProductCommand(
            title: $payload['title'] ?? '',
            sku: $payload['sku'] ?? null
        );

        // Execute via Handler (Application Layer)
        $product = ($this->createHandler)($cmd);

        // Return response (Presentation Layer)
        $this->response->json([
            'message' => 'created',
            'data' => ['id' => $product->id, 'title' => $product->title]
        ], 201);
    }
}
```

**❌ WRONG - Violations**:
```php
// ❌ BAD: Direct Model access
$product = ProductModel::create($data);

// ❌ BAD: Manual Handler instantiation
$handler = new CreateProductHandler(new InMemoryProductRepository());

// ✅ GOOD: Dependency Injection
public function __construct(private readonly CreateProductHandler $handler) {}
```

---

## Dependency Flow

```
┌─────────────────────────────────────────────────────────────┐
│                    Presentation Layer                       │
│               (Controllers, Actions, Views)                 │
│                                                             │
│  ❌ Should NOT access Models directly                       │
│  ✅ Should use Handlers (Application Layer)                 │
└─────────────────────────────────────────────────────────────┘
                           ↓
┌─────────────────────────────────────────────────────────────┐
│                    Application Layer                        │
│              (Commands, Handlers, Use Cases)                │
│                                                             │
│  ❌ Should NOT know about ORM Models                        │
│  ✅ Should use Repository Interfaces (Domain Layer)         │
└─────────────────────────────────────────────────────────────┘
                           ↓
┌─────────────────────────────────────────────────────────────┐
│                      Domain Layer                           │
│            (Entities, Value Objects, Interfaces)            │
│                                                             │
│  ❌ Should have ZERO framework dependencies                 │
│  ✅ Should only contain business logic                      │
└─────────────────────────────────────────────────────────────┘
                           ↑
┌─────────────────────────────────────────────────────────────┐
│                  Infrastructure Layer                       │
│        (Repository Implementations, ORM Models)             │
│                                                             │
│  ✅ Can depend on Framework (ORM, Elasticsearch, etc.)      │
│  ✅ Implements Domain Interfaces                            │
│  ✅ Converts between Entities and Models                    │
└─────────────────────────────────────────────────────────────┘
```

**Key Rule**: Dependencies flow INWARD
- Infrastructure depends on Domain (via interfaces)
- Application depends on Domain (via interfaces)
- Presentation depends on Application
- **Domain depends on NOTHING**

---

## Dependency Injection Setup

**Location**: `src/App/Providers/RepositoryServiceProvider.php`

This is where you wire Interface → Implementation:

```php
namespace App\Providers;

use App\Domain\Product\ProductRepository;
use App\Infrastructure\Persistence\EloquentProductRepository;

class RepositoryServiceProvider extends ServiceProvider {
    public function register(ContainerInterface $container): void {
        // Bind Interface (Domain) to Implementation (Infrastructure)
        $container->bind(
            ProductRepository::class,
            fn() => new EloquentProductRepository()
        );
    }
}
```

**This is Dependency Inversion Principle in action**:
- Domain defines the interface (ProductRepository)
- Infrastructure provides the implementation (EloquentProductRepository)
- Application/Presentation only knows about the interface

---

## Common Mistakes to Avoid

### ❌ Mistake 1: Models in Domain Layer
```php
// ❌ WRONG: ProductModel in src/App/Domain/Product/
namespace App\Domain\Product;
use Toporia\Framework\Database\ORM\Model;  // ← Framework dependency!

class ProductModel extends Model { }  // ← Violates Clean Architecture
```

**✅ CORRECT**: Models in Infrastructure Layer
```php
// ✅ CORRECT: ProductModel in src/App/Infrastructure/Persistence/Models/
namespace App\Infrastructure\Persistence\Models;
use Toporia\Framework\Database\ORM\Model;

class ProductModel extends Model { }
```

---

### ❌ Mistake 2: Controller Accessing Models Directly
```php
// ❌ WRONG: Controller directly using Model
public function index() {
    $products = ProductModel::all();  // ← Skips Application layer
    return response()->json($products);
}
```

**✅ CORRECT**: Controller uses Handler
```php
// ✅ CORRECT: Controller uses Handler
public function __construct(
    private readonly GetProductsHandler $handler
) {}

public function index() {
    $products = ($this->handler)(new GetProductsQuery());
    return response()->json($products);
}
```

---

### ❌ Mistake 3: Domain Entity with Framework Dependencies
```php
// ❌ WRONG: Domain entity using framework
namespace App\Domain\Product;
use Toporia\Framework\Support\Collection;  // ← Framework dependency!

class Product {
    public function getRelated(): Collection { }  // ← Violates independence
}
```

**✅ CORRECT**: Domain entity is pure PHP
```php
// ✅ CORRECT: Pure PHP, no framework
namespace App\Domain\Product;

class Product {
    public function __construct(
        public readonly ?int $id,
        public string $title,
        public ?string $sku
    ) {}

    // Only pure business logic methods
}
```

---

## Testing Benefits

Clean Architecture makes testing easier:

### Unit Testing (Domain Layer)
```php
// No mocking needed - pure business logic
$product = new Product(id: 1, title: 'Test', sku: 'SKU-1');
$this->assertEquals('Test', $product->title);
```

### Use Case Testing (Application Layer)
```php
// Mock only the interface
$mockRepo = $this->createMock(ProductRepository::class);
$handler = new CreateProductHandler($mockRepo);
$result = $handler(new CreateProductCommand('Test', 'SKU-1'));
```

### Integration Testing (Infrastructure Layer)
```php
// Test actual database operations
$repo = new EloquentProductRepository();
$product = $repo->store(new Product(null, 'Test', 'SKU-1'));
$this->assertNotNull($product->id);
```

---

## Summary

| Layer | Location | Dependencies | Purpose |
|-------|----------|--------------|---------|
| **Domain** | `src/App/Domain/` | **ZERO** | Business logic, entities, interfaces |
| **Application** | `src/App/Application/` | Domain only | Use cases, commands, handlers |
| **Infrastructure** | `src/App/Infrastructure/` | Domain + Framework | ORM models, repositories, external services |
| **Presentation** | `src/App/Presentation/` | Application + Domain | Controllers, views, HTTP concerns |

**Golden Rules**:
1. ✅ Domain has ZERO dependencies
2. ✅ Models belong in Infrastructure, NOT Domain
3. ✅ Controllers use Handlers, NOT Models
4. ✅ Use Dependency Injection for everything
5. ✅ Dependencies flow INWARD (toward Domain)

---

## References

- [Clean Architecture by Uncle Bob](https://blog.cleancoder.com/uncle-bob/2012/08/13/the-clean-architecture.html)
- [Domain-Driven Design by Eric Evans](https://www.domainlanguage.com/ddd/)
- [SOLID Principles](https://en.wikipedia.org/wiki/SOLID)
