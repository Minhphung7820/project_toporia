# Architecture Documentation

## Clean Architecture with Service Provider Pattern

This project follows **Clean Architecture** principles with a **Service Provider Pattern** for dependency injection configuration, inspired by Laravel but designed to be lightweight and SOLID-compliant.

## Directory Structure

```
project_topo/
├── bootstrap/
│   ├── app.php          # Application bootstrap & provider registration
│   └── helpers.php      # Global helper functions
├── config/
│   ├── app.php          # Application configuration
│   ├── database.php     # Database connections
│   └── middleware.php   # Middleware configuration
├── public/
│   └── index.php        # Front controller (entry point)
├── routes/
│   └── web.php          # Route definitions
├── src/
│   ├── Framework/       # Reusable framework layer
│   │   ├── Foundation/  # Application & ServiceProvider infrastructure
│   │   ├── Providers/   # Framework service providers
│   │   ├── Container/   # Dependency injection container
│   │   ├── Routing/     # Router implementation
│   │   ├── Http/        # Request/Response/Kernel
│   │   ├── Events/      # Event dispatcher
│   │   ├── Database/    # Database & ORM
│   │   ├── Config/      # Configuration repository
│   │   └── ...
│   └── App/             # Application-specific code
│       ├── Domain/      # Business entities & repository interfaces
│       ├── Application/ # Use cases (commands & handlers)
│       ├── Infrastructure/ # Repository implementations, external services
│       ├── Presentation/   # Controllers, actions, middleware, views
│       └── Providers/   # Application service providers
└── database/
    └── migrations/      # Database migrations
```

## Bootstrap Flow

### 1. Entry Point: `public/index.php`

```php
// Minimal front controller
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
require __DIR__ . '/../bootstrap/helpers.php';
$app->make(\Framework\Routing\Router::class)->dispatch();
```

**Responsibilities:**
- Load autoloader
- Bootstrap application
- Load helpers
- Dispatch request

### 2. Application Bootstrap: `bootstrap/app.php`

```php
$app = new Application(basePath: dirname(__DIR__));

$app->registerProviders([
    // Framework providers
    ConfigServiceProvider::class,
    HttpServiceProvider::class,
    EventServiceProvider::class,
    RoutingServiceProvider::class,

    // Application providers
    AppServiceProvider::class,
    RepositoryServiceProvider::class,
    EventServiceProvider::class,
    RouteServiceProvider::class,
]);

$app->boot();
return $app;
```

**Responsibilities:**
- Create Application instance
- Register all service providers
- Boot providers (initialize services)
- Return configured application

### 3. Service Providers

Service providers are the **central place** for configuring services. They follow a two-phase lifecycle:

#### Phase 1: **Register** (Binding)
```php
public function register(ContainerInterface $container): void
{
    // Only bind services, DO NOT resolve
    $container->singleton(MyService::class, fn() => new MyService());
    $container->bind(MyInterface::class, MyImplementation::class);
}
```

#### Phase 2: **Boot** (Initialization)
```php
public function boot(ContainerInterface $container): void
{
    // Safe to resolve services here
    $service = $container->get(MyService::class);
    $service->configure();
}
```

### 4. Provider Types

#### Framework Providers (`src/Framework/Providers/`)

**Purpose:** Register framework core services (reusable across projects)

- **ConfigServiceProvider** - Loads configuration from `config/` directory
- **HttpServiceProvider** - Registers Request & Response
- **EventServiceProvider** - Registers Event Dispatcher
- **RoutingServiceProvider** - Registers Router
- **DatabaseServiceProvider** - Registers database connections & ORM

#### Application Providers (`src/App/Providers/`)

**Purpose:** Register application-specific services

- **AppServiceProvider** - Core application services (auth, logging, etc.)
- **RepositoryServiceProvider** - Bind repository interfaces to implementations
- **EventServiceProvider** - Register event listeners
- **RouteServiceProvider** - Load route files

## Key Design Principles

### 1. Separation of Concerns

- **Framework layer** is reusable and has no application-specific code
- **Application layer** contains business logic specific to your project
- **Configuration** is centralized in `config/` directory
- **Bootstrapping** is isolated in `bootstrap/` directory

### 2. Dependency Inversion (SOLID)

```php
// ✅ Good: Depend on abstraction
class ProductService {
    public function __construct(
        private ProductRepository $repo // Interface
    ) {}
}

// Binding in RepositoryServiceProvider
$container->bind(
    ProductRepository::class,
    PdoProductRepository::class
);
```

### 3. Single Responsibility (SOLID)

Each service provider has ONE responsibility:

- `HttpServiceProvider` - Only HTTP services
- `EventServiceProvider` - Only event dispatcher
- `RouteServiceProvider` - Only route loading

### 4. Open/Closed (SOLID)

**Open for extension** (add new providers) without modifying existing code:

```php
// Just create new provider and add to bootstrap/app.php
class CacheServiceProvider extends ServiceProvider {
    public function register(ContainerInterface $container): void {
        $container->singleton('cache', fn() => new RedisCache());
    }
}
```

### 5. Liskov Substitution (SOLID)

All providers implement `ServiceProviderInterface`:

```php
interface ServiceProviderInterface {
    public function register(ContainerInterface $container): void;
    public function boot(ContainerInterface $container): void;
}
```

## Configuration System

### File-based Configuration

Configuration files in `config/` are **automatically loaded** by `ConfigServiceProvider`:

```php
// config/app.php
return [
    'name' => env('APP_NAME', 'My App'),
    'env' => env('APP_ENV', 'local'),
];

// Access in code
$config = container('config');
$name = $config->get('app.name');
```

### Environment Variables

Use `.env` file for environment-specific values:

```bash
APP_NAME="My Application"
DB_HOST=localhost
DB_NAME=mydb
```

Access via `env()` helper:

```php
$host = env('DB_HOST', 'localhost');
```

## Middleware System

### Configuration: `config/middleware.php`

```php
return [
    'global' => [
        // Run on every request
        TrimStrings::class,
    ],
    'aliases' => [
        'auth' => Authenticate::class,
        'admin' => AdminMiddleware::class,
    ],
];
```

### Usage in Routes

```php
// Using full class name
$router->get('/dashboard', [HomeController::class, 'dashboard'])
    ->middleware([Authenticate::class]);

// Using alias (from config/middleware.php)
$router->get('/admin', [AdminController::class, 'index'])
    ->middleware(['auth', 'admin']);
```

## Routing System

Routes are **automatically loaded** by `RouteServiceProvider` from `routes/web.php`:

```php
/** @var Router $router */

// The $router variable is injected automatically
$router->get('/', [HomeController::class, 'index']);
$router->post('/products', [ProductsController::class, 'store'])
    ->middleware(['auth']);
```

**No need to manually require route files** - `RouteServiceProvider` handles it.

## Adding New Services

### Step 1: Create Service Provider

```php
// src/App/Providers/CacheServiceProvider.php
namespace App\Providers;

use Framework\Foundation\ServiceProvider;
use Framework\Container\ContainerInterface;

class CacheServiceProvider extends ServiceProvider
{
    public function register(ContainerInterface $container): void
    {
        $container->singleton('cache', function() {
            $driver = env('CACHE_DRIVER', 'file');
            return match($driver) {
                'redis' => new RedisCache(),
                'file' => new FileCache(),
            };
        });
    }
}
```

### Step 2: Register Provider

Edit `bootstrap/app.php`:

```php
$app->registerProviders([
    // ... existing providers
    CacheServiceProvider::class,
]);
```

### Step 3: Use Service

```php
$cache = container('cache');
$cache->put('key', 'value', 3600);
```

## Testing

Service providers make testing easier:

```php
// In tests, you can easily swap implementations
$testContainer = new Container();
$testContainer->bind(UserRepository::class, fn() => new InMemoryUserRepository());

// Or use a different set of providers
$testApp = new Application('/path/to/app');
$testApp->registerProviders([
    TestHttpServiceProvider::class, // Different implementations
]);
```

## Benefits of This Architecture

1. **✅ Clean Separation** - Framework vs Application code
2. **✅ Testable** - Easy to swap dependencies
3. **✅ Maintainable** - Clear organization
4. **✅ Reusable** - Framework layer can be used in other projects
5. **✅ SOLID** - Follows all 5 principles
6. **✅ Scalable** - Easy to add new services
7. **✅ Minimal Entry Point** - `public/index.php` is very simple

## Migration from Old Structure

**Before (public/index.php had everything):**
```php
// ❌ Bad: All service registration in front controller
$container = new Container();
$container->bind(RequestInterface::class, ...);
$container->bind(RouterInterface::class, ...);
// ... 100+ lines of bindings
```

**After (Service Provider pattern):**
```php
// ✅ Good: Minimal front controller
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Router::class)->dispatch();
```

All configuration is now organized in:
- **Providers** - Service registration logic
- **Config files** - Application settings
- **Bootstrap** - Application initialization

This makes the codebase more maintainable and follows Clean Architecture principles!
