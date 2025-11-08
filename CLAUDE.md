# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a Clean Architecture PHP skeleton with a professional-grade custom framework. The project follows SOLID principles, Clean Architecture patterns, and emphasizes separation of concerns with Framework and Application layers.

## Requirements

- **PHP**: >= 8.1
- **Composer**: For dependency management

### Optional PHP Extensions

- `ext-redis` - Required for Redis cache and queue drivers
- `ext-pdo_mysql` - Required for MySQL database support
- `ext-pdo_pgsql` - Required for PostgreSQL database support
- `ext-pdo_sqlite` - Required for SQLite database support

See [INSTALLATION.md](INSTALLATION.md) for detailed installation instructions.

## Development Commands

### Setup
```bash
composer install
composer dump-autoload
```

### Run Development Server
```bash
php -S localhost:8000 -t public
```

The application entry point is [public/index.php](public/index.php).

### Console Commands
```bash
php console list                    # List all available commands
php console cache:clear             # Clear application cache
php console cache:clear --store=redis  # Clear specific cache store

# Queue commands
php console queue:work              # Start queue worker (default queue)
php console queue:work --queue=emails  # Process specific queue
php console queue:work --max-jobs=100  # Limit number of jobs
php console queue:work --sleep=5    # Sleep duration between jobs
php console queue:work --stop-when-empty  # Stop when queue is empty

# Schedule commands
php console schedule:run            # Run due scheduled tasks (call from cron)
php console schedule:run --verbose  # Verbose output
php console schedule:list           # List all scheduled tasks
```

## Architecture

### Layer Structure

The codebase follows Clean Architecture principles with strict layer separation:

1. **Framework Layer** (`src/Framework/`) - Professional mini-framework with SOLID design:

   **Container** (`Toporia\Framework\Container\`)
   - Interface-based dependency injection container (PSR-11 inspired)
   - Auto-wiring support for automatic dependency resolution
   - Constructor injection with type-hint resolution
   - Method injection via `call()` method
   - Singleton and factory patterns
   - Circular dependency detection
   - Key interfaces: `ContainerInterface`

   **Routing** (`Toporia\Framework\Routing\`)
   - Fully OOP router with route objects and collections
   - Interfaces: `RouterInterface`, `RouteInterface`, `RouteCollectionInterface`
   - Route parameter extraction (`{id}` syntax) with regex compilation
   - Named routes support via `->name('route.name')`
   - Fluent middleware registration
   - RESTful route methods: `get()`, `post()`, `put()`, `patch()`, `delete()`, `any()`

   **HTTP** (`Toporia\Framework\Http\`)
   - Request/Response abstraction with full interfaces
   - Interfaces: `RequestInterface`, `ResponseInterface`
   - Request features: header extraction, AJAX detection, JSON expectation
   - Response features: HTML, JSON, redirects, file downloads
   - Helper methods: `only()`, `except()`, `has()`, `all()` on Request

   **Events** (`Toporia\Framework\Events\`)
   - Event-driven architecture with proper abstractions
   - Interfaces: `EventDispatcherInterface`, `EventInterface`
   - Priority-based listener execution
   - Event propagation control (`stopPropagation()`)
   - Support for both event objects and simple event names
   - Generic event class for simple use cases
   - Event subscriber pattern with `subscribe()`

   **Console** (`Toporia\Framework\Console\`)
   - Professional CLI framework with Command pattern
   - Interfaces: `InputInterface`, `OutputInterface`
   - Command base class with dependency injection
   - Input parsing: arguments, options, flags (--option, -v)
   - Output formatting: colors, tables, interactive prompts
   - Auto-registration via Console Kernel
   - Built-in commands: cache:clear, queue:work, schedule:run
   - Entry point: [console](console) executable

   **Middleware** (`Toporia\Framework\Http\Middleware\`)
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
use Toporia\Framework\Foundation\ServiceProvider;
use Toporia\Framework\Container\ContainerInterface;

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
- `Toporia\Framework\Providers\ConfigServiceProvider` - Loads configuration files
- `Toporia\Framework\Providers\HttpServiceProvider` - Request/Response services
- `Toporia\Framework\Providers\EventServiceProvider` - Event dispatcher
- `Toporia\Framework\Providers\RoutingServiceProvider` - Router
- `Toporia\Framework\Providers\DatabaseServiceProvider` - Database connections and ORM

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
    \Toporia\Framework\Providers\ConfigServiceProvider::class,
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
- Route grouping with shared attributes
- Automatic route loading via `RouteServiceProvider`

**Route Groups:**

Group routes with shared attributes (middleware, prefix, namespace):

```php
// Group with prefix and middleware
$router->group([
    'prefix' => 'admin',
    'middleware' => ['auth', 'admin'],
], function (Router $router) {
    $router->get('/dashboard', [AdminController::class, 'index']);
    $router->get('/users', [AdminController::class, 'users']);
    // All routes will have '/admin' prefix and 'auth', 'admin' middleware
});

// Group with namespace
$router->group([
    'namespace' => 'App\\Presentation\\Http\\Controllers\\Api',
    'prefix' => 'api/v1',
], function (Router $router) {
    $router->get('/users', [UserApiController::class, 'index']);
    // Controller resolves to: App\Presentation\Http\Controllers\Api\UserApiController
});

// Nested groups
$router->group(['prefix' => 'api'], function (Router $router) {
    $router->group(['prefix' => 'v1'], function (Router $router) {
        $router->get('/products', [ProductApiController::class, 'index']);
        // Route path: /api/v1/products
    });
});

// Named route groups
$router->group(['name' => 'admin.'], function (Router $router) {
    $router->get('/dashboard', [AdminController::class, 'index'])->name('dashboard');
    // Route name: admin.dashboard
});
```

**Middleware Configuration:**

Middleware groups and aliases are configured in [config/middleware.php](config/middleware.php):

```php
return [
    'groups' => [
        'web' => [
            AddSecurityHeaders::class,  // Applied to all web routes
        ],
        'api' => [
            ValidateJsonRequest::class,  // Applied to all API routes
        ],
    ],
    'aliases' => [
        'auth' => Authenticate::class,
        'admin' => AdminMiddleware::class,
    ],
];
```

**Multiple Route Files:**

RouteServiceProvider automatically loads routes from multiple files with appropriate middleware groups:

- [routes/web.php](routes/web.php) - Web routes with 'web' middleware group
- [routes/api.php](routes/api.php) - API routes with 'api' middleware group + '/api' prefix

```php
// In RouteServiceProvider
protected function loadWebRoutes(Application $app, Router $router, array $middleware): void
{
    $router->group([
        'middleware' => $middleware,  // 'web' middleware group from config
        'namespace' => 'App\\Presentation\\Http\\Controllers',
    ], function (Router $router) use ($app) {
        require $app->path('routes/web.php');
    });
}
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

**Architecture**: The middleware system follows Clean Architecture and SOLID principles with clear separation of concerns:

1. **MiddlewareInterface** - Contract for all middleware (Interface Segregation Principle)
2. **AbstractMiddleware** - Base class with automatic before/after hooks (Open/Closed Principle)
3. **MiddlewarePipeline** - Dedicated class for building middleware chains (Single Responsibility Principle)
4. **Router** - Delegates middleware execution to MiddlewarePipeline (Dependency Inversion Principle)

**Creating Middleware** - Three approaches:

**Option 1: Implement MiddlewareInterface** (Best for simple middleware)
```php
use Toporia\Framework\Http\Middleware\MiddlewareInterface;

final class Authenticate implements MiddlewareInterface
{
    public function handle(Request $request, Response $response, callable $next): mixed
    {
        if (!auth()->check()) {
            $response->setStatus(401);
            $response->html('<h1>401 Unauthorized</h1>');
            return null; // Short-circuit - don't call $next
        }

        return $next($request, $response); // Continue to next middleware
    }
}
```

**Option 2: Extend AbstractMiddleware with process()** (Best for validation/checks)
```php
use Toporia\Framework\Http\Middleware\AbstractMiddleware;

final class ValidateJsonRequest extends AbstractMiddleware
{
    protected function process(Request $request, Response $response): mixed
    {
        // Return null to continue, return Response to short-circuit
        if (!$request->expectsJson()) {
            return null; // Continue
        }

        // Validate JSON
        $raw = $request->raw();
        json_decode($raw);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $response->json(['error' => 'Invalid JSON'], 400);
            return null; // Short-circuit
        }

        return null; // Continue
    }
}
```

**Option 3: Extend AbstractMiddleware with hooks** (Best for logging/metrics)
```php
use Toporia\Framework\Http\Middleware\AbstractMiddleware;

final class LogRequest extends AbstractMiddleware
{
    private float $startTime;

    protected function before(Request $request, Response $response): void
    {
        $this->startTime = microtime(true);
        error_log("[REQUEST] {$request->method()} {$request->path()}");
    }

    protected function after(Request $request, Response $response, mixed $result): void
    {
        $duration = round((microtime(true) - $this->startTime) * 1000, 2);
        error_log("[RESPONSE] Status: {$response->getStatus()} | Duration: {$duration}ms");
    }
}
```

**Using Middleware in Routes:**
```php
// Single middleware
$router->get('/admin', [AdminController::class, 'index'])
    ->middleware(['auth']);

// Multiple middleware (executed in order)
$router->post('/api/data', [ApiController::class, 'store'])
    ->middleware(['json', 'auth', 'log']);

// Using full class names
$router->get('/dashboard', [HomeController::class, 'dashboard'])
    ->middleware([Authenticate::class, LogRequest::class]);
```

**Middleware Aliases** in [config/middleware.php](config/middleware.php):
```php
return [
    'aliases' => [
        'auth' => Authenticate::class,
        'log' => LogRequest::class,
        'json' => ValidateJsonRequest::class,
        'security' => AddSecurityHeaders::class,
    ],
];
```

**Global Middleware** (runs on every request):
```php
// In config/middleware.php
'global' => [
    AddSecurityHeaders::class,
    LogRequest::class,
],
```

**How AbstractMiddleware Works:**

When you extend `AbstractMiddleware`, the `handle()` method is automatically implemented with this flow:

1. Call `before()` hook (if overridden)
2. Call `process()` (if overridden)
   - If `process()` returns non-null: short-circuit, skip next middleware
   - If `process()` returns null: continue to step 3
3. Call `$next()` to continue pipeline
4. Call `after()` hook (if overridden) with result
5. Return result

This pattern allows you to:
- Override just `before()` for pre-processing (logging, context setup)
- Override just `after()` for post-processing (headers, metrics)
- Override `process()` for validation with optional short-circuit
- Override any combination of the above

**Dependency Injection in Middleware:**
```php
final class RateLimiter implements MiddlewareInterface
{
    public function __construct(
        private CacheInterface $cache,
        private ConfigInterface $config
    ) {} // Dependencies auto-wired from container!

    public function handle(Request $request, Response $response, callable $next): mixed
    {
        $key = "rate_limit:{$request->ip()}";
        $limit = $this->config->get('rate_limit.max_requests', 60);

        if ($this->cache->increment($key) > $limit) {
            $response->json(['error' => 'Too many requests'], 429);
            return null;
        }

        return $next($request, $response);
    }
}
```

**Best Practices:**
- Use `MiddlewareInterface` for simple validation/authentication
- Use `AbstractMiddleware` when you need before/after hooks
- Keep middleware focused on single responsibility
- Use dependency injection for services (auto-wired)
- Return early (short-circuit) for failed validations
- Always call `$next($request, $response)` to continue pipeline
- Use middleware aliases for cleaner route definitions
- Order matters: authentication before authorization, validation before processing

#### Console Commands

Create professional CLI commands following Command pattern with dependency injection.

**Creating Commands:**

```php
use Toporia\Framework\Console\Command;

final class MyCommand extends Command
{
    protected string $signature = 'my:command';
    protected string $description = 'Description of my command';

    public function __construct(
        private readonly SomeService $service
    ) {} // Dependencies auto-wired!

    public function handle(): int
    {
        // Get arguments and options
        $arg = $this->argument(0, 'default');  // Positional argument
        $name = $this->argument('name');        // Named argument
        $force = $this->option('force', false); // Option with default
        $verbose = $this->hasOption('verbose'); // Check if option exists

        // Output
        $this->info('Processing...');
        $this->success('Done!');
        $this->error('Something failed');
        $this->warn('Warning message');

        // Tables
        $this->table(
            ['Name', 'Email'],
            [['John', 'john@example.com'], ['Jane', 'jane@example.com']]
        );

        // Interactive prompts
        $answer = $this->confirm('Are you sure?', false);
        $input = $this->ask('What is your name?', 'default');
        $choice = $this->choice('Choose one:', ['Option 1', 'Option 2']);

        // Use injected dependencies
        $result = $this->service->doSomething();

        return 0; // 0 = success, non-zero = error
    }
}
```

**Registering Commands** in [src/App/Presentation/Console/Kernel.php](src/App/Presentation/Console/Kernel.php):

```php
public function commands(): array
{
    return [
        CacheClearCommand::class,
        QueueWorkCommand::class,
        MyCommand::class,  // Add your command here
    ];
}
```

**Running Commands:**

```bash
php console list                # List all commands
php console my:command          # Run command
php console my:command arg1 arg2  # Positional arguments
php console my:command name=John  # Named argument
php console my:command --force  # Boolean flag
php console my:command --retries=3  # Option with value
php console my:command -v       # Short option (verbosity)
php console my:command --no-interaction  # Disable prompts
```

**Built-in Commands:**

- `cache:clear [--store=driver]` - Clear cache
- `queue:work [--queue=name] [--max-jobs=N] [--sleep=seconds] [--stop-when-empty]` - Process queue jobs
- `schedule:run [--verbose]` - Run scheduled tasks (call from cron every minute)
- `schedule:list` - List all scheduled tasks

**Production Setup:**

```bash
# Crontab for scheduled tasks
* * * * * cd /path/to/project && php console schedule:run >> storage/logs/schedule.log 2>&1

# Supervisor for queue worker
[program:queue-worker]
command=php /path/to/project/console queue:work --sleep=3 --max-jobs=1000
autostart=true
autorestart=true
user=www-data
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

Framework provides a Result monad in `Toporia\Framework\Support\Result` for error handling:
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

## Collections & Functional Programming

The framework provides advanced Collection classes with rich functional operations:

### Collection (Eager)

Eager collection that loads all items into memory immediately. Ideal for small to medium datasets.

```php
use Toporia\Framework\Support\Collection;

// Create collections
$collection = Collection::make([1, 2, 3, 4, 5]);
$collection = Collection::range(1, 100);
$collection = Collection::times(5, fn($i) => $i * 2);

// Map, filter, reduce
$doubled = $collection->map(fn($n) => $n * 2);
$evens = $collection->filter(fn($n) => $n % 2 === 0);
$sum = $collection->reduce(fn($acc, $n) => $acc + $n, 0);

// Chaining operations
$result = Collection::make([1, 2, 3, 4, 5])
    ->filter(fn($n) => $n > 2)
    ->map(fn($n) => $n * 10)
    ->sum(); // 120

// Advanced operations
$products = Collection::make($productsArray)
    ->sortBy('price', descending: true)
    ->groupBy('category')
    ->map(fn($group) => $group->take(5));

// Statistical functions
$prices->avg('price');
$prices->median('price');
$prices->mode('color');
$prices->sum('total');

// Set operations
$a->diff($b);          // Items in A but not in B
$a->intersect($b);     // Items in both A and B
$a->union($b);         // All unique items from A and B
$a->diffBy($b, fn($x) => $x['id']);
$a->intersectBy($b, fn($x) => $x['id']);

// Sliding windows & advanced patterns
$collection->window(3);     // [[1,2,3], [2,3,4], [3,4,5], ...]
$collection->pairs();       // [[1,2], [2,3], [3,4], ...]
$collection->transpose();   // Transpose 2D matrix
$collection->crossJoin($other); // Cartesian product

// Pagination
$paginated = $collection->paginate(perPage: 10, page: 1);
// Returns: ['data' => Collection, 'current_page' => 1, 'total' => 100, ...]
```

### LazyCollection (Memory-Efficient)

Lazy collection using PHP Generators. Ideal for large datasets, streams, or infinite sequences.

```php
use Toporia\Framework\Support\LazyCollection;

// Create lazy collections
$lazy = LazyCollection::make(function () {
    foreach (range(1, 1000000) as $i) {
        yield $i;
    }
});

$infinite = LazyCollection::infinite(fn($i) => $i * 2);
$range = LazyCollection::range(1, 1000000);

// All operations are lazy (deferred execution)
$result = LazyCollection::range(1, 1000000)
    ->filter(fn($n) => $n % 2 === 0)
    ->map(fn($n) => $n * 2)
    ->take(100)         // Only processes first 100 matching items
    ->collect();        // Materialize to eager Collection

// Process large files efficiently
$lazy = LazyCollection::make(function () {
    $handle = fopen('large-file.csv', 'r');
    while ($row = fgetcsv($handle)) {
        yield $row;
    }
    fclose($handle);
});

$results = $lazy
    ->skip(1)              // Skip header
    ->map(fn($row) => ['name' => $row[0], 'email' => $row[1]])
    ->filter(fn($user) => str_contains($user['email'], '@gmail.com'))
    ->chunk(1000)          // Process in chunks of 1000
    ->each(fn($chunk) => $this->processBatch($chunk));

// Cursor operations (take/skip variants)
$lazy->take(10);           // First 10 items
$lazy->takeWhile(fn($n) => $n < 100);
$lazy->takeUntil(fn($n) => $n >= 100);
$lazy->skip(100);          // Skip first 100
$lazy->skipWhile(fn($n) => $n < 100);
$lazy->skipUntil(fn($n) => $n >= 100);

// Multi-pass safe (automatically caches on first pass)
$lazy = LazyCollection::range(1, 100);
foreach ($lazy as $item) { /* pass 1 */ }
foreach ($lazy as $item) { /* pass 2 - works! */ }

// Explicit caching for multiple iterations
$cached = $lazy->remember();
```

### Shared Methods (Both Collections)

Both `Collection` and `LazyCollection` implement `CollectionInterface`:

```php
// Transformations
->map(callable $callback)
->flatMap(callable $callback)
->filter(callable $callback = null)
->reject(callable $callback)
->unique(string|callable|null $key = null)
->flatten(int $depth = INF)
->pluck(string|array $path)
->keyBy(callable|string $key)

// Aggregations (terminal operations)
->reduce(callable $callback, mixed $initial = null)
->sum(callable|string|null $callback = null)
->avg(callable|string|null $callback = null)
->min(callable|string|null $callback = null)
->max(callable|string|null $callback = null)
->count()

// Predicates (terminal)
->some(callable $callback)     // Any item matches
->every(callable $callback)    // All items match
->contains(mixed $key, ...)
->isEmpty()
->isNotEmpty()

// Accessing items (terminal)
->first(callable $callback = null, mixed $default = null)
->all()                        // Materialize to array
->collect()                    // To eager Collection
->toJson(int $options = 0)

// Slicing & limiting
->take(int $limit)
->skip(int $offset)
->nth(int $step, int $offset = 0)
->chunk(int $size)             // Yields Collection chunks

// Combining
->concat(mixed ...$iters)
->zip(mixed ...$arrays)
->merge(mixed ...$arrays)      // LazyCollection only

// Utilities
->tap(callable $callback)      // Side effects without changing collection
->each(callable $callback)     // Terminal iteration
->remember()                   // Cache results for multi-pass
```

### When to Use Which

**Use Collection (Eager)** when:
- Dataset fits comfortably in memory (< 10,000 items typically)
- Need random access or multiple passes
- Need sorting, grouping, or statistical operations
- Working with in-memory data structures

**Use LazyCollection** when:
- Large datasets (millions of rows)
- Streaming data (files, API responses, database cursors)
- Memory-constrained environments
- Infinite sequences
- Only need to process once or sequentially

### Performance Tips

```php
// ✅ Good: Lazy processing of large dataset
LazyCollection::make($hugeDataset)
    ->filter(fn($x) => $x > 100)
    ->take(10)              // Stops after finding 10 items
    ->collect();

// ❌ Bad: Materializes entire dataset
Collection::make($hugeDataset)
    ->filter(fn($x) => $x > 100)  // Processes ALL items
    ->take(10);

// ✅ Good: Chunk processing for memory efficiency
LazyCollection::make($millionRecords)
    ->chunk(1000)
    ->each(fn($chunk) => $this->processBatch($chunk->all()));

// ✅ Good: Convert to lazy when memory is concern
$collection->toLazy()
    ->map(fn($item) => $this->heavyTransform($item))
    ->filter(fn($item) => $item->isValid())
    ->collect();
```

## Database & ORM

The framework includes a complete database abstraction layer with ORM, Query Builder, and Migrations.

### Database Connection

**Configuration:**
```php
use Toporia\Framework\Database\Connection;
use Toporia\Framework\Database\DatabaseManager;

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
use Toporia\Framework\Database\Query\QueryBuilder;

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
use Toporia\Framework\Database\ORM\Model;

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
use Toporia\Framework\Database\Migration\Migration;

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
use Toporia\Framework\Database\Schema\SchemaBuilder;

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

### Model Relationships

The ORM supports Eloquent-style relationships with eager loading.

**Defining Relationships:**
```php
use Toporia\Framework\Database\ORM\Model;

class User extends Model
{
    protected static string $table = 'users';

    // One-to-One: User has one Profile
    public function profile()
    {
        return $this->hasOne(Profile::class);
    }

    // One-to-Many: User has many Posts
    public function posts()
    {
        return $this->hasMany(Post::class);
    }

    // Many-to-Many: User belongs to many Roles
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_roles');
    }
}

class Post extends Model
{
    protected static string $table = 'posts';

    // Inverse: Post belongs to User
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // One-to-Many: Post has many Comments
    public function comments()
    {
        return $this->hasMany(Comment::class);
    }
}

class Comment extends Model
{
    protected static string $table = 'comments';

    public function post()
    {
        return $this->belongsTo(Post::class);
    }
}
```

**Lazy Loading (N+1 Problem):**
```php
// ⚠️ N+1 Problem - queries executed in loop
$users = User::all();
foreach ($users as $user) {
    echo $user->profile->bio; // Separate query for each user
}
```

**Eager Loading (Recommended):**
```php
// ✅ Eager Loading - 2 queries total
$users = User::with(['profile'])->get();
foreach ($users as $user) {
    echo $user->profile->bio; // No additional query
}

// Multiple relationships
$users = User::with(['profile', 'posts', 'roles'])->get();

// Nested relationships
$users = User::with(['posts.comments'])->get();

// Load relationships after fetching
$user = User::find(1);
$user->load(['posts', 'profile']);
```

**Working with Relationships:**
```php
// Access loaded relationship
$user = User::with(['posts'])->find(1);
$posts = $user->posts; // ModelCollection

// Check if relationship is loaded
if ($user->relationLoaded('posts')) {
    // Relationship is already loaded
}

// Lazy load if not already loaded
$profile = $user->profile; // Loads from DB if not eager loaded
```

**Many-to-Many Operations:**
```php
$user = User::find(1);

// Attach role to user
$user->roles()->attach(2); // role_id = 2

// Attach with pivot data
$user->roles()->attach(3, ['expires_at' => '2025-12-31']);

// Detach specific role
$user->roles()->detach(2);

// Detach all roles
$user->roles()->detach();

// Sync roles (remove all, add new)
$user->roles()->sync([1, 2, 3]);
```

**Custom Foreign Keys:**
```php
class User extends Model
{
    // Specify custom foreign key
    public function posts()
    {
        return $this->hasMany(Post::class, 'author_id', 'id');
    }
}

class Post extends Model
{
    public function author()
    {
        return $this->belongsTo(User::class, 'author_id', 'id');
    }
}
```

**Relationship Types:**

- **HasOne**: One-to-one (User → Profile)
- **HasMany**: One-to-many (User → Posts)
- **BelongsTo**: Inverse of HasOne/HasMany (Post → User)
- **BelongsToMany**: Many-to-many with pivot table (User ↔ Roles)

### Database Repository Pattern

For repositories that need database access:

```php
use Toporia\Framework\Data\DatabaseRepository;
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
use Toporia\Framework\Database\DatabaseManager;
use Toporia\Framework\Database\ORM\Model;

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

## Security Features

The framework includes comprehensive security features following industry best practices.

### CSRF Protection

**Components:**
- `CsrfTokenManagerInterface` - Contract for CSRF token management
- `SessionCsrfTokenManager` - Session-based token storage
- `CsrfProtection` middleware - Automatic validation for state-changing requests

**Usage:**

```php
// In views - generate CSRF token field
<?= csrf_field() ?>

// Or get token value
<meta name="csrf-token" content="<?= csrf_token() ?>">

// Middleware automatically validates POST, PUT, PATCH, DELETE requests
$router->post('/products', [ProductsController::class, 'store'])
    ->middleware([CsrfProtection::class]);

// Token can be sent via:
// 1. Request body: _token, _csrf, csrf_token
// 2. Header: X-CSRF-TOKEN or X-XSRF-TOKEN
```

**Configuration** ([config/security.php](config/security.php)):
```php
'csrf' => [
    'enabled' => true,
    'token_name' => '_token',
],
```

### XSS Protection

**Utility Class:** `Toporia\Framework\Security\XssProtection`

**Methods:**
```php
use Toporia\Framework\Security\XssProtection;

// Escape HTML (prevents XSS)
$safe = XssProtection::escape($userInput);
$safe = e($userInput); // Helper function

// Remove all HTML tags
$clean = XssProtection::clean($userInput);
$clean = clean($userInput); // Helper function

// Sanitize HTML (allow specific tags)
$sanitized = XssProtection::sanitize($html, '<p><a><strong>');

// Purify rich text (more permissive)
$purified = XssProtection::purify($richTextHtml);

// Escape for JavaScript
$jsValue = XssProtection::escapeJs($value);

// Escape for URLs
$urlParam = XssProtection::escapeUrl($value);

// Clean arrays recursively
$cleanData = XssProtection::cleanArray($_POST);
```

**Security Headers Middleware:**

```php
use Toporia\Framework\Http\Middleware\AddSecurityHeaders;

// Apply security headers globally
$router->get('/', [HomeController::class, 'index'])
    ->middleware([AddSecurityHeaders::class]);
```

**Default Headers:**
- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: SAMEORIGIN`
- `X-XSS-Protection: 1; mode=block`
- `Content-Security-Policy` (configurable)
- `Strict-Transport-Security` (HSTS for HTTPS)
- `Referrer-Policy`
- `Permissions-Policy`

### Authorization (Gates & Policies)

**Gate System** - Closure-based authorization

```php
use Toporia\Framework\Auth\Gate;

$gate = app('gate');

// Define abilities
$gate->define('update-post', function ($user, $post) {
    return $user->id === $post->author_id;
});

$gate->define('delete-post', fn($user, $post) => $user->isAdmin());

// Check authorization
if ($gate->allows('update-post', $post)) {
    // User can update
}

if ($gate->denies('delete-post', $post)) {
    // User cannot delete
}

// Authorize or throw exception
$gate->authorize('update-post', $post); // Throws AuthorizationException

// Check multiple abilities
$gate->any(['update-post', 'delete-post'], $post); // Any allowed?
$gate->all(['view-post', 'update-post'], $post);   // All allowed?

// Check for specific user
$gate->forUser($otherUser)->allows('update-post', $post);
```

**Policy Classes** - Resource-based authorization

```php
class PostPolicy
{
    public function view($user, $post): bool
    {
        return $post->is_published || $user->id === $post->author_id;
    }

    public function update($user, $post): bool
    {
        return $user->id === $post->author_id;
    }

    public function delete($user, $post): bool
    {
        return $user->isAdmin();
    }
}

// Register policy
$gate->policy(Post::class, PostPolicy::class);

// Use with resource
$gate->check('update', $post); // Calls Post@update
```

**Authorization Middleware:**

```php
use Toporia\Framework\Http\Middleware\Authorize;

// Protect routes
$router->put('/posts/{id}', [PostController::class, 'update'])
    ->middleware([Authorize::can($gate, 'update-post')]);
```

## Cache System

Multi-driver cache system with PSR-16 inspired interface.

**Drivers:**
- **File** - Filesystem-based (no dependencies)
- **Redis** - High-performance (requires phpredis)
- **Memory** - In-memory (for testing, single request)

**Configuration** ([config/cache.php](config/cache.php)):
```php
'default' => env('CACHE_DRIVER', 'file'),
'stores' => [
    'file' => [
        'driver' => 'file',
        'path' => __DIR__ . '/../storage/cache',
    ],
    'redis' => [
        'driver' => 'redis',
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'port' => env('REDIS_PORT', 6379),
    ],
],
```

**Usage:**

```php
$cache = app('cache');

// Basic operations
$cache->set('user:1', $user, 3600); // TTL in seconds
$user = $cache->get('user:1');
$cache->has('user:1');
$cache->delete('user:1');
$cache->clear(); // Clear all

// Forever (no expiration)
$cache->forever('settings', $settings);

// Remember pattern (get or generate)
$users = $cache->remember('users.all', 3600, function() {
    return User::all();
});

$config = $cache->rememberForever('config', fn() => loadConfig());

// Pull (get and delete)
$value = $cache->pull('temp_data');

// Increment/Decrement
$cache->increment('page_views');
$cache->decrement('stock', 5);

// Multiple operations
$cache->setMultiple(['key1' => 'val1', 'key2' => 'val2'], 3600);
$values = $cache->getMultiple(['key1', 'key2']);
$cache->deleteMultiple(['key1', 'key2']);

// Using specific driver
$redis = $cache->driver('redis');
$redis->set('key', 'value');
```

**Helper Functions:**
```php
// Get cache instance
$cache = cache();

// Get cached value
$value = cache('key', 'default');
```

## Cookie Management

Secure cookie handling with encryption support.

**Cookie Value Object:**
```php
use Toporia\Framework\Http\Cookie;

// Create cookies
$cookie = Cookie::make('user_id', '123', 60); // 60 minutes
$cookie = Cookie::forever('remember', 'token');
$cookie = Cookie::forget('session'); // Delete cookie

// With options
$cookie = Cookie::make('secure_data', 'value', 60, [
    'path' => '/admin',
    'domain' => '.example.com',
    'secure' => true,
    'httpOnly' => true,
    'sameSite' => 'Strict'
]);

// Send cookie
$cookie->send();
```

**Cookie Jar** - Encrypted cookie management:

```php
$cookies = app('cookie');

// Set encrypted cookie
$cookies->make('user_pref', 'dark_mode', 60);

// Get encrypted cookie
$pref = $cookies->get('user_pref');

// Queue cookies to send later
$cookies->make('session', $sessionId, 120)
        ->forever('remember', $token)
        ->forget('temp');

// Send all queued
$cookies->sendQueued();
```

**Configuration** ([config/security.php](config/security.php)):
```php
'cookie' => [
    'encryption_key' => env('APP_KEY'),
    'secure' => env('APP_ENV') === 'production',
    'http_only' => true,
    'same_site' => 'Lax',
],
```

## Rate Limiting

Prevent abuse with configurable rate limiting.

**Rate Limiter Interface:**
- **CacheRateLimiter** - Uses cache backend (works with all cache drivers)

**Configuration:**
```php
use Toporia\Framework\RateLimit\CacheRateLimiter;

$limiter = new CacheRateLimiter(app('cache'));

// Check rate limit
if ($limiter->attempt('api:' . $userId, 60, 60)) {
    // Allowed (60 requests per minute)
} else {
    // Rate limited
}

// Get remaining attempts
$remaining = $limiter->remaining('api:' . $userId, 60);

// Time until reset
$seconds = $limiter->availableIn('api:' . $userId);

// Clear limits
$limiter->clear('api:' . $userId);
```

**Throttle Middleware:**

```php
use Toporia\Framework\Http\Middleware\ThrottleRequests;

// Limit to 60 requests per minute
$router->get('/api/data', [ApiController::class, 'index'])
    ->middleware([
        ThrottleRequests::with($limiter, 60, 1) // 60 attempts, 1 minute
    ]);

// The middleware:
// - Automatically identifies users (authenticated ID or IP)
// - Adds rate limit headers to response
// - Returns 429 Too Many Requests when exceeded
```

**Response Headers:**
- `X-RateLimit-Limit` - Maximum requests allowed
- `X-RateLimit-Remaining` - Requests remaining
- `X-RateLimit-Reset` - Unix timestamp when limit resets
- `Retry-After` - Seconds to wait (when rate limited)

## Queue System

Asynchronous job processing with multiple drivers.

**Drivers:**
- **Sync** - Execute immediately (testing/development)
- **Database** - Store in database table
- **Redis** - High-performance queue with delayed jobs support

**Configuration** ([config/queue.php](config/queue.php)):
```php
'default' => env('QUEUE_CONNECTION', 'sync'),
'connections' => [
    'sync' => ['driver' => 'sync'],
    'database' => [
        'driver' => 'database',
        'table' => 'jobs',
    ],
    'redis' => [
        'driver' => 'redis',
        'host' => env('REDIS_HOST', '127.0.0.1'),
    ],
],
```

**Creating Jobs:**

```php
use Toporia\Framework\Queue\Job;

class SendEmailJob extends Job
{
    public function __construct(
        private string $to,
        private string $subject,
        private string $message
    ) {
        parent::__construct();
    }

    public function handle(): void
    {
        // Send email logic
        mail($this->to, $this->subject, $this->message);
    }

    public function failed(\Throwable $exception): void
    {
        // Handle failure (log, notify, etc.)
        error_log("Email failed to {$this->to}: " . $exception->getMessage());
    }
}
```

**Dispatching Jobs:**

```php
$queue = app('queue');

// Push to queue
$job = new SendEmailJob('user@example.com', 'Hello', 'Message');
$queue->push($job);

// Delayed execution
$queue->later($job, 300); // 5 minutes delay

// Specific queue
$job->onQueue('emails');
$queue->push($job, 'emails');

// Configure retries
$job->tries(5); // Max 5 attempts
```

**Queue Worker:**

```php
use Toporia\Framework\Queue\Worker;

$queue = app('queue')->driver('database');
$worker = new Worker($queue, maxJobs: 100, sleep: 3);

// Process jobs
$worker->work('default'); // Process from 'default' queue
```

**Database Schema:**

```php
// jobs table
CREATE TABLE jobs (
    id VARCHAR(255) PRIMARY KEY,
    queue VARCHAR(255) NOT NULL,
    payload TEXT NOT NULL,
    attempts INTEGER NOT NULL DEFAULT 0,
    available_at INTEGER NOT NULL,
    created_at INTEGER NOT NULL
);

// failed_jobs table
CREATE TABLE failed_jobs (
    id VARCHAR(255) PRIMARY KEY,
    queue VARCHAR(255) NOT NULL,
    payload TEXT NOT NULL,
    exception TEXT NOT NULL,
    failed_at INTEGER NOT NULL
);
```

## Task Scheduling

Cron-like task scheduler within the application.

**Configure Scheduled Tasks** in [src/App/Providers/ScheduleServiceProvider.php](src/App/Providers/ScheduleServiceProvider.php):

```php
private function defineSchedule(Scheduler $scheduler, ContainerInterface $container): void
{
    // Run every minute
    $scheduler->call(function () {
        // Your task logic
    })->everyMinute()->description('Task description');

    // Run every 5 minutes
    $scheduler->call(function () {
        // Clean up old logs
        cleanLogs();
    })->everyMinutes(5)->description('Cleanup logs');

    // Run daily at specific time
    $scheduler->call(function () {
        // Send daily reports
        sendReports();
    })->dailyAt('08:00')->description('Send daily reports');

    // Run weekly
    $scheduler->call(function () {
        // Backup database
        backupDatabase();
    })->sundays()->dailyAt('02:00')->description('Weekly backup');

    // Execute shell command
    $scheduler->exec('php console cache:clear')
        ->hourly()
        ->description('Clear cache hourly');

    // With conditions
    $scheduler->call(function () {
        // Health check
    })->everyMinutes(5)
      ->when(fn() => date('H') >= 9 && date('H') < 18)  // Business hours only
      ->description('Health check');
}
```

**List Scheduled Tasks:**

```bash
php console schedule:list
```

**Run Due Tasks:**

```bash
php console schedule:run              # Run all due tasks
php console schedule:run --verbose    # Verbose output
```

**Frequency Methods:**
```php
->everyMinute()           // Every minute
->everyMinutes(5)         // Every 5 minutes
->hourly()                // Every hour
->hourlyAt(15)            // At :15 of every hour
->daily()                 // Daily at midnight
->dailyAt('13:00')        // Daily at 1 PM
->weekly()                // Sundays at midnight
->monthly()               // 1st of month at midnight
->weekdays()              // Mon-Fri at midnight
->weekends()              // Sat-Sun at midnight
->mondays()               // Every Monday
->tuesdays()              // Every Tuesday
->wednesdays()            // Every Wednesday
->thursdays()             // Every Thursday
->fridays()               // Every Friday
->saturdays()             // Every Saturday
->sundays()               // Every Sunday
```

**Advanced Options:**
```php
// Custom cron expression
$schedule->call($callback)->cron('*/15 * * * *');

// Timezone
$schedule->call($callback)->daily()->timezone('America/New_York');

// Conditional execution
$schedule->call($callback)->daily()->when(function () {
    return shouldRun();
});

// Skip conditions
$schedule->call($callback)->daily()->skip(function () {
    return shouldSkip();
});
```

**Setup Cron (Production):**

Add to crontab (`crontab -e`):

```bash
* * * * * cd /path/to/project && php console schedule:run >> storage/logs/schedule.log 2>&1
```

This runs every minute and executes only tasks that are due. All task configuration is centralized in `ScheduleServiceProvider`.

## Security Best Practices

**1. SQL Injection Prevention:**
- Use Query Builder with parameter binding (automatic)
- Use ORM models (automatic escaping)
- Never concatenate user input into SQL

```php
// ✅ Safe - parameterized query
$products = $query->table('products')->where('id', $userId)->get();

// ❌ Unsafe - string concatenation
$products = $query->raw("SELECT * FROM products WHERE id = {$userId}");
```

**2. XSS Prevention:**
- Always escape output in views
- Use `e()` helper or `XssProtection::escape()`
- Sanitize rich text with `XssProtection::purify()`

```php
// ✅ Safe
<h1><?= e($userInput) ?></h1>

// ❌ Unsafe
<h1><?= $userInput ?></h1>
```

**3. CSRF Protection:**
- Enable globally in [config/security.php](config/security.php)
- Include `csrf_field()` in all forms
- Middleware validates automatically

**4. Authentication & Authorization:**
- Always check `auth()->check()` before accessing user data
- Use `Gate::authorize()` for authorization
- Apply `Authenticate` and `Authorize` middleware to protected routes

**5. Rate Limiting:**
- Apply to all public APIs
- Apply to authentication endpoints (prevent brute force)
- Configure appropriate limits per endpoint

**6. Secure Headers:**
- Use `AddSecurityHeaders` middleware globally
- Configure CSP for your application needs
- Enable HSTS in production (HTTPS only)
