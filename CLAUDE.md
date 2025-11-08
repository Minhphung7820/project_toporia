# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a Clean Architecture PHP skeleton with a professional-grade custom framework. The project follows SOLID principles, Clean Architecture patterns, and emphasizes separation of concerns with Framework and Application layers.

## Development Commands

### Setup
```bash
composer dump-autoload
```

### Run Development Server
```bash
php -S localhost:8000 -t public
```

The application entry point is [public/index.php](public/index.php).

## Architecture

### Layer Structure

The codebase follows Clean Architecture principles with strict layer separation:

1. **Framework Layer** (`src/Framework/`) - Professional mini-framework with SOLID design:

   **Container** (`Framework\Container\`)
   - Interface-based dependency injection container (PSR-11 inspired)
   - Auto-wiring support for automatic dependency resolution
   - Constructor injection with type-hint resolution
   - Method injection via `call()` method
   - Singleton and factory patterns
   - Circular dependency detection
   - Key interfaces: `ContainerInterface`

   **Routing** (`Framework\Routing\`)
   - Fully OOP router with route objects and collections
   - Interfaces: `RouterInterface`, `RouteInterface`, `RouteCollectionInterface`
   - Route parameter extraction (`{id}` syntax) with regex compilation
   - Named routes support via `->name('route.name')`
   - Fluent middleware registration
   - RESTful route methods: `get()`, `post()`, `put()`, `patch()`, `delete()`, `any()`

   **HTTP** (`Framework\Http\`)
   - Request/Response abstraction with full interfaces
   - Interfaces: `RequestInterface`, `ResponseInterface`
   - Request features: header extraction, AJAX detection, JSON expectation
   - Response features: HTML, JSON, redirects, file downloads
   - Helper methods: `only()`, `except()`, `has()`, `all()` on Request

   **Events** (`Framework\Events\`)
   - Event-driven architecture with proper abstractions
   - Interfaces: `EventDispatcherInterface`, `EventInterface`
   - Priority-based listener execution
   - Event propagation control (`stopPropagation()`)
   - Support for both event objects and simple event names
   - Generic event class for simple use cases
   - Event subscriber pattern with `subscribe()`

   **Middleware** (`Framework\Http\Middleware\`)
   - Interface: `MiddlewareInterface`
   - Pipeline pattern implementation
   - Before/after hooks in `AbstractMiddleware`

   **Domain/Application base classes**
   - Abstract Entity, ValueObject
   - AbstractCommand, AbstractQuery, AbstractHandler

   **Presentation patterns**
   - AbstractAction (ADR pattern)
   - AbstractResponder
   - AbstractMiddleware

2. **Domain Layer** (`src/App/Domain/`) - Pure business entities and repository interfaces
   - Entities are plain PHP classes with readonly properties (e.g., Product)
   - Repository interfaces define persistence contracts
   - No framework dependencies

3. **Application Layer** (`src/App/Application/`) - Use cases implementing business logic
   - Organized by feature/aggregate (e.g., `Product/CreateProduct/`)
   - Each use case has a Command and Handler
   - Handlers receive dependencies via constructor injection (auto-wired)
   - Example: CreateProductHandler receives ProductRepository and returns domain entity

4. **Infrastructure Layer** (`src/App/Infrastructure/`) - External concerns implementation
   - Repository implementations (InMemoryProductRepository, PDO repositories)
   - Authentication (SessionAuth)
   - External services, APIs, databases

5. **Presentation Layer** (`src/App/Presentation/`) - HTTP interface
   - **Controllers** - Traditional MVC style controllers extending BaseController
     - Receive Request/Response in constructor
     - Use `$this->view()` helper to render views
   - **Actions** - ADR style single-purpose handlers extending AbstractAction
     - Implement `handle()` method
     - Receive dependencies via constructor (auto-wired)
   - **Middleware** - Implement `MiddlewareInterface` or extend `AbstractMiddleware`
   - **Views** - Plain PHP templates in `Views/` directory

### Application Bootstrap

The application uses a Service Provider pattern for organizing service registration:

**File Structure:**
- [bootstrap/app.php](bootstrap/app.php) - Application bootstrapping and provider registration
- [bootstrap/helpers.php](bootstrap/helpers.php) - Global helper functions
- [public/index.php](public/index.php) - Minimal front controller (entry point)
- [config/](config/) - Configuration files loaded automatically

**Bootstrap Flow:**
1. [public/index.php](public/index.php) loads autoloader and requires [bootstrap/app.php](bootstrap/app.php)
2. [bootstrap/app.php](bootstrap/app.php) creates `Application` instance and registers service providers
3. Service providers register services into the container (register phase)
4. All providers are booted (boot phase) - event listeners, routes, etc. are configured
5. Router dispatches the HTTP request

### Key Patterns

#### Service Provider Pattern

Service Providers organize service registration logic into reusable, testable classes:

```php
use Framework\Foundation\ServiceProvider;
use Framework\Container\ContainerInterface;

class MyServiceProvider extends ServiceProvider
{
    /**
     * Register services into the container.
     * Only bind services here, don't resolve them yet.
     */
    public function register(ContainerInterface $container): void
    {
        $container->singleton(MyService::class, fn() => new MyService());
    }

    /**
     * Bootstrap services after all providers are registered.
     * Safe to resolve services from the container here.
     */
    public function boot(ContainerInterface $container): void
    {
        $service = $container->get(MyService::class);
        $service->initialize();
    }
}
```

**Framework Service Providers:**
- `Framework\Providers\ConfigServiceProvider` - Loads configuration files
- `Framework\Providers\HttpServiceProvider` - Request/Response services
- `Framework\Providers\EventServiceProvider` - Event dispatcher
- `Framework\Providers\RoutingServiceProvider` - Router
- `Framework\Providers\DatabaseServiceProvider` - Database connections and ORM

**Application Service Providers:**
- `App\Providers\AppServiceProvider` - Core application services (auth, etc.)
- `App\Providers\RepositoryServiceProvider` - Repository bindings
- `App\Providers\EventServiceProvider` - Event listeners
- `App\Providers\RouteServiceProvider` - Routes loading

**Registering Providers:**

Edit [bootstrap/app.php](bootstrap/app.php):

```php
$app->registerProviders([
    // Framework providers (order matters!)
    \Framework\Providers\ConfigServiceProvider::class,
    HttpServiceProvider::class,

    // Your custom provider
    MyServiceProvider::class,
]);
```

**Best Practices:**
- Use `register()` only for binding services (no resolution)
- Use `boot()` for configuration that requires resolved services
- Provider order matters: dependencies must be registered first
- Keep providers focused on a single concern (SRP)

#### Configuration System

Configuration is centralized in [config/](config/) directory:

```php
// config/app.php
return [
    'name' => env('APP_NAME', 'My App'),
    'env' => env('APP_ENV', 'local'),
];

// Access in code:
$name = container('config')->get('app.name');
$name = config('app.name'); // Using helper (when implemented)
```

**Available Configurations:**
- [config/app.php](config/app.php) - Application settings
- [config/database.php](config/database.php) - Database connections
- [config/middleware.php](config/middleware.php) - Global middleware and aliases

**Environment Variables:**

Create `.env` file from `.env.example` and use `env()` helper:

```php
$dbHost = env('DB_HOST', 'localhost');
```

#### Dependency Injection and Auto-Wiring

The Container provides advanced DI with automatic resolution:

```php
// Binding with factory
$container->bind(ServiceInterface::class, fn($c) => new Service($c->get(Dependency::class)));

// Singleton
$container->singleton(Router::class, fn($c) => new Router($c->get(Request::class), ...));

// Instance registration
$container->instance('config', $configArray);

// Auto-wiring - automatic resolution via type hints
$container->get(ProductsController::class); // Automatically resolves dependencies

// Method invocation with DI
$container->call([Controller::class, 'method'], ['param' => 'value']);
```

**Key capabilities:**
- Automatic constructor injection based on type hints
- Support for both interface and concrete bindings
- Circular dependency detection
- Method injection via `call()`

#### Routing with Fluent API

Routes are automatically loaded by `RouteServiceProvider` from [routes/web.php](routes/web.php):

```php
// The $router variable is injected automatically
/** @var Router $router */

// Basic routes
$router->get('/products', [ProductsController::class, 'index']);
$router->post('/products', [ProductsController::class, 'store']);

// Route parameters
$router->get('/products/{id}', [ProductsController::class, 'show']);

// Named routes with middleware
$router->get('/dashboard', [HomeController::class, 'dashboard'])
    ->name('dashboard')
    ->middleware([Authenticate::class, RoleCheck::class]);

// Middleware can use short aliases from config/middleware.php
$router->get('/admin', [AdminController::class, 'index'])
    ->middleware(['auth', 'admin']); // 'auth' resolves to Authenticate::class

// Multiple HTTP methods
$router->any('/webhook', [WebhookController::class, 'handle']);

// Invokable classes (ADR pattern)
$router->post('/v2/products', CreateProductAction::class);
```

**Features:**
- RESTful verbs: `get()`, `post()`, `put()`, `patch()`, `delete()`, `any()`
- Route parameters with regex compilation (`{id}`, `{slug}`, etc.)
- Fluent middleware registration
- Named routes for URL generation
- Support for controller arrays and invokable classes
- Automatic route loading via `RouteServiceProvider`

**Middleware Configuration:**

Global middleware and aliases are configured in [config/middleware.php](config/middleware.php):

```php
return [
    'global' => [
        // Middleware that run on every request
    ],
    'aliases' => [
        'auth' => Authenticate::class,
        'admin' => AdminMiddleware::class,
    ],
];
```

#### Event System

Enhanced event dispatcher with priority and propagation control:

```php
// Register listeners with priority (higher = earlier execution)
app('events')->listen('product.created', function($event) {
    // Handle event
}, priority: 100);

// Dispatch with event object
$event = new ProductCreatedEvent($product);
event($event);

// Dispatch with event name (creates GenericEvent)
event('product.created', ['product' => $product]);

// Stop propagation in listener
class ImportantListener {
    public function handle(EventInterface $event): void {
        // Do something important
        $event->stopPropagation(); // Stops further listeners
    }
}

// Subscribe multiple listeners
$dispatcher->subscribe([
    'product.created' => [SomeListener::class, 10], // With priority
    'product.updated' => [AnotherListener::class],  // Default priority
]);
```

**Event features:**
- Priority-based execution
- Event propagation control
- Event objects for complex data
- Generic events for simple use cases
- Listener introspection

#### Middleware Pipeline

Middleware execute in order around your handler:

```php
// In routes
$router->get('/admin', [AdminController::class, 'index'])
    ->middleware([Authenticate::class, AdminOnly::class]);

// Creating middleware
class Authenticate implements MiddlewareInterface {
    public function handle(Request $request, Response $response, callable $next): mixed {
        if (!auth()->check()) {
            $response->redirect('/login');
            return;
        }

        return $next($request, $response);
    }
}

// Using AbstractMiddleware with hooks
class LogRequest extends AbstractMiddleware {
    public function handle(Request $request, Response $response, callable $next): mixed {
        $this->before($request, $response);
        $result = $next($request, $response);
        $this->after($request, $response, $result);
        return $result;
    }

    protected function before(Request $request, Response $response): void {
        // Log before request
    }
}
```

#### MVC vs ADR Patterns

**MVC (Controllers)**: Use for traditional multi-action controllers with views
```php
class ProductsController extends BaseController {
    public function index() {
        $products = /* get products */;
        return $this->view('products/index', ['products' => $products]);
    }
}
```

**ADR (Actions)**: Use for API endpoints or single-responsibility handlers
```php
class CreateProductAction extends AbstractAction {
    public function __construct(
        private ProductRepository $repo,
        private EventDispatcherInterface $events
    ) {} // Auto-wired!

    protected function handle(Request $request, Response $response, ...$vars) {
        $product = $this->repo->create($request->input());
        $this->events->dispatch(new ProductCreated($product));
        return $response->json($product, 201);
    }
}
```

#### Use Case Pattern

Application layer follows Command/Handler pattern with auto-wiring:

```php
// Command - Simple DTO
class CreateProductCommand extends AbstractCommand {
    public function __construct(
        public string $title,
        public ?string $sku = null
    ) {}
}

// Handler - Auto-wired dependencies
class CreateProductHandler extends AbstractHandler {
    public function __construct(
        private ProductRepository $repo,
        private EventDispatcherInterface $events
    ) {} // Dependencies auto-resolved!

    public function __invoke(CreateProductCommand $cmd): Product {
        $product = new Product(null, $cmd->title, $cmd->sku);
        $saved = $this->repo->store($product);
        $this->events->dispatch(new ProductCreated($saved));
        return $saved;
    }
}

// Usage in controller/action
$handler = $container->get(CreateProductHandler::class); // Auto-wired!
$product = $handler(new CreateProductCommand('New Product'));
```

#### Result Type

Framework provides a Result monad in `Framework\Support\Result` for error handling:
```php
$result = Result::ok($value);
$result = Result::err(new Exception('Failed'));

$result->match(
    onOk: fn($v) => $response->json($v),
    onErr: fn($e) => $response->json(['error' => $e->getMessage()], 400)
);
```

### HTTP Request/Response

**Request features:**
```php
$request->method();                    // GET, POST, etc.
$request->path();                      // /products/123
$request->query('page', 1);            // Query parameter with default
$request->input('title');              // Body data
$request->header('content-type');      // Headers
$request->isAjax();                    // Detect AJAX
$request->expectsJson();               // Expects JSON response
$request->only(['title', 'sku']);      // Get specific fields
$request->except(['_token']);          // Get all except
$request->has('title');                // Check field exists
$request->raw();                       // Raw body
```

**Response features:**
```php
$response->html('<h1>Hello</h1>', 200);
$response->json(['data' => $data], 201);
$response->redirect('/dashboard', 302);
$response->download('/path/to/file.pdf', 'invoice.pdf');
$response->noContent();                // 204 No Content
$response->header('X-Custom', 'value');
```

## Code Organization Conventions

- All files use `declare(strict_types=1)`
- Namespace structure mirrors directory structure
- One class per file
- Interfaces end with `Interface` suffix
- Abstract classes start with `Abstract` prefix
- All public APIs have docblock comments
- Repository interfaces in Domain, implementations in Infrastructure
- Use cases grouped by aggregate/feature in Application layer
- Keep Framework layer generic and reusable
- Keep App layer specific to the application domain
- Prefer composition over inheritance
- Program to interfaces, not implementations

## Database & ORM

The framework includes a complete database abstraction layer with ORM, Query Builder, and Migrations.

### Database Connection

**Configuration:**
```php
use Framework\Database\Connection;
use Framework\Database\DatabaseManager;

// Single connection
$connection = new Connection([
    'driver' => 'mysql',  // mysql, pgsql, sqlite
    'host' => 'localhost',
    'port' => 3306,
    'database' => 'myapp',
    'username' => 'root',
    'password' => 'secret',
    'charset' => 'utf8mb4'
]);

// Multiple connections via DatabaseManager
$db = new DatabaseManager([
    'default' => [
        'driver' => 'mysql',
        'host' => 'localhost',
        'database' => 'myapp',
        'username' => 'root',
        'password' => 'secret'
    ],
    'analytics' => [
        'driver' => 'pgsql',
        'host' => 'analytics.example.com',
        'database' => 'analytics',
        'username' => 'reader',
        'password' => 'secret'
    ]
]);

$connection = $db->connection(); // default
$analyticsConn = $db->connection('analytics');
```

### Query Builder

Fluent interface for building SQL queries with automatic parameter binding:

```php
use Framework\Database\Query\QueryBuilder;

$builder = new QueryBuilder($connection);

// SELECT queries
$products = $builder->table('products')
    ->select(['id', 'title', 'price'])
    ->where('is_active', true)
    ->where('price', '>', 100)
    ->orderBy('created_at', 'DESC')
    ->limit(10)
    ->get();

// Complex WHERE clauses
$products = $builder->table('products')
    ->where('category', 'electronics')
    ->orWhere('category', 'computers')
    ->whereIn('brand', ['Apple', 'Samsung'])
    ->whereNotNull('sku')
    ->get();

// JOINs
$orders = $builder->table('orders')
    ->leftJoin('users', 'orders.user_id', '=', 'users.id')
    ->select(['orders.*', 'users.name'])
    ->get();

// Aggregations
$count = $builder->table('products')->count();
$exists = $builder->table('products')->where('sku', 'ABC123')->exists();

// INSERT
$id = $builder->table('products')->insert([
    'title' => 'New Product',
    'price' => 99.99,
    'is_active' => true
]);

// UPDATE
$affected = $builder->table('products')
    ->where('id', 1)
    ->update(['price' => 79.99]);

// DELETE
$deleted = $builder->table('products')
    ->where('is_active', false)
    ->delete();

// Find by ID
$product = $builder->table('products')->find(1);

// Get first result
$newest = $builder->table('products')
    ->orderBy('created_at', 'DESC')
    ->first();

// Raw SQL
$sql = $builder->toSql();
$bindings = $builder->getBindings();
```

### ORM Models (Active Record)

**Defining Models:**
```php
use Framework\Database\ORM\Model;

class ProductModel extends Model
{
    protected static string $table = 'products';
    protected static string $primaryKey = 'id';
    protected static bool $timestamps = true;

    // Mass assignment protection
    protected static array $fillable = [
        'title', 'sku', 'description', 'price', 'stock', 'is_active'
    ];

    // Attribute casting
    protected static array $casts = [
        'price' => 'float',
        'stock' => 'int',
        'is_active' => 'bool'
    ];
}
```

**Using Models:**
```php
// Set connection for all models
ProductModel::setConnection($connection);

// Create
$product = new ProductModel([
    'title' => 'New Product',
    'price' => 99.99
]);
$product->save();

// Or use create()
$product = ProductModel::create([
    'title' => 'Another Product',
    'price' => 149.99
]);

// Find by ID
$product = ProductModel::find(1);
$product = ProductModel::findOrFail(1); // Throws exception if not found

// Get all
$products = ProductModel::all();

// Update
$product = ProductModel::find(1);
$product->price = 79.99;
$product->save();

// Delete
$product->delete();

// Refresh from database
$product->refresh();

// Check if exists
if ($product->exists()) {
    // Model exists in database
}

// Get attributes
$data = $product->toArray();
$json = $product->toJson();

// Access attributes
echo $product->title;
echo $product->price;
```

**Model Hooks:**
```php
class ProductModel extends Model
{
    protected function creating(): void
    {
        // Called before creating
        $this->is_active = $this->is_active ?? true;
    }

    protected function created(): void
    {
        // Called after created
        event(new ProductCreated($this));
    }

    protected function updating(): void
    {
        // Called before updating
        if ($this->price < 0) {
            throw new \InvalidArgumentException('Price cannot be negative');
        }
    }

    protected function updated(): void {
        // Called after updated
    }

    protected function deleting(): void {
        // Called before deleting
    }

    protected function deleted(): void {
        // Called after deleted
    }
}
```

**Query Scopes:**
```php
class ProductModel extends Model
{
    public static function active()
    {
        return static::query()->where('is_active', true);
    }

    public static function lowStock(int $threshold = 10)
    {
        return static::query()->where('stock', '<=', $threshold);
    }
}

// Usage
$activeProducts = ProductModel::active()->get();
$lowStockProducts = ProductModel::lowStock(5)->get();
```

**Custom Methods:**
```php
class ProductModel extends Model
{
    public function inStock(): bool
    {
        return $this->stock > 0;
    }

    public function decreaseStock(int $quantity): bool
    {
        if ($this->stock < $quantity) {
            return false;
        }
        $this->stock -= $quantity;
        return $this->save();
    }
}

// Usage
$product = ProductModel::find(1);
if ($product->inStock()) {
    $product->decreaseStock(2);
}
```

### Migrations & Schema Builder

**Creating Migrations:**
```php
use Framework\Database\Migration\Migration;

class CreateProductsTable extends Migration
{
    public function up(): void
    {
        $this->schema->create('products', function ($table) {
            $table->id(); // Auto-increment primary key
            $table->string('title');
            $table->string('sku', 100)->unique()->nullable();
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2)->default(0.00);
            $table->integer('stock')->unsigned()->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps(); // created_at, updated_at

            // Indexes
            $table->index('sku');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        $this->schema->dropIfExists('products');
    }
}
```

**Running Migrations:**
```php
use Framework\Database\Schema\SchemaBuilder;

$schema = new SchemaBuilder($connection);

$migration = new CreateProductsTable();
$migration->setSchema($schema);
$migration->up();    // Create table
// $migration->down(); // Drop table
```

**Schema Builder Column Types:**
- `id()` - Auto-increment primary key
- `string($name, $length)` - VARCHAR
- `text($name)` - TEXT
- `integer($name)` - INT
- `decimal($name, $precision, $scale)` - DECIMAL
- `boolean($name)` - BOOLEAN/TINYINT
- `date($name)` - DATE
- `datetime($name)` - DATETIME/TIMESTAMP
- `timestamp($name)` - TIMESTAMP
- `timestamps()` - created_at, updated_at

**Column Modifiers:**
- `->nullable()` - Allow NULL
- `->default($value)` - Default value
- `->unsigned()` - Unsigned (integers)
- `->unique()` - Unique constraint
- `->comment($text)` - Column comment

**Indexes & Constraints:**
```php
$table->unique(['email']); // Unique index
$table->index(['name']); // Regular index
$table->foreign('user_id', 'id', 'users'); // Foreign key
```

### Database Repository Pattern

For repositories that need database access:

```php
use Framework\Data\DatabaseRepository;
use App\Domain\Product\ProductRepository;

class PdoProductRepository extends DatabaseRepository implements ProductRepository
{
    protected string $table = 'products';

    public function findBySku(string $sku): ?Product
    {
        $data = $this->query()
            ->where('sku', $sku)
            ->first();

        return $data ? $this->hydrate($data) : null;
    }

    public function findActiveProducts(): array
    {
        $results = $this->query()
            ->where('is_active', true)
            ->orderBy('created_at', 'DESC')
            ->get();

        return array_map([$this, 'hydrate'], $results);
    }

    public function store(Product $product): Product
    {
        if ($product->id === null) {
            // Insert
            $id = $this->insert([
                'title' => $product->title,
                'sku' => $product->sku,
                'price' => $product->price
            ]);

            return new Product($id, $product->title, $product->sku);
        }

        // Update
        $this->updateById($product->id, [
            'title' => $product->title,
            'sku' => $product->sku
        ]);

        return $product;
    }

    private function hydrate(array $data): Product
    {
        return new Product(
            $data['id'],
            $data['title'],
            $data['sku']
        );
    }
}
```

**DatabaseRepository Methods:**
- `query()` - Get QueryBuilder
- `findById($id)` - Find by primary key
- `findAll()` - Get all records
- `findBy($criteria)` - Find matching criteria
- `findOneBy($criteria)` - Find first matching
- `insert($data)` - Insert record
- `update($criteria, $data)` - Update records
- `updateById($id, $data)` - Update by ID
- `delete($criteria)` - Delete records
- `deleteById($id)` - Delete by ID
- `count($criteria)` - Count records
- `exists($criteria)` - Check existence
- `paginate($page, $perPage, $criteria)` - Paginated results

### Database in Container

Register database services in [public/index.php](public/index.php):

```php
use Framework\Database\DatabaseManager;
use Framework\Database\ORM\Model;

// Register DatabaseManager
$container->singleton(DatabaseManager::class, fn() => new DatabaseManager([
    'default' => [
        'driver' => 'mysql',
        'host' => $_ENV['DB_HOST'] ?? 'localhost',
        'database' => $_ENV['DB_NAME'] ?? 'myapp',
        'username' => $_ENV['DB_USER'] ?? 'root',
        'password' => $_ENV['DB_PASS'] ?? ''
    ]
]));

$container->bind('db', fn($c) => $c->get(DatabaseManager::class)->connection());

// Set Model connection
$db = $container->get(DatabaseManager::class);
Model::setConnection($db->connection());
```

## SOLID Principles Applied

- **Single Responsibility**: Each class has one reason to change
- **Open/Closed**: Extend via interfaces and abstract classes
- **Liskov Substitution**: All implementations fulfill interface contracts
- **Interface Segregation**: Small, focused interfaces (e.g., `RouteInterface`, `EventInterface`)
- **Dependency Inversion**: Depend on abstractions (interfaces), not concretions
