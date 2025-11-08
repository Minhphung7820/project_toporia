# Service Accessor Pattern

The Service Accessor pattern provides an elegant, static-like interface for accessing services from the IoC container, inspired by Laravel's Facades but designed with Clean Architecture and SOLID principles in mind.

## Table of Contents

- [What is Service Accessor?](#what-is-service-accessor)
- [Why Not Just "Facade"?](#why-not-just-facade)
- [Architecture](#architecture)
- [Available Accessors](#available-accessors)
- [Usage Examples](#usage-examples)
- [Testing](#testing)
- [Creating Custom Accessors](#creating-custom-accessors)
- [Best Practices](#best-practices)
- [SOLID Principles](#solid-principles)

## What is Service Accessor?

A Service Accessor is **NOT** a true static class. It's a convenient wrapper that:
- Provides static-like syntax: `Cache::get('key')`
- Forwards calls to actual instances in the IoC container
- Maintains testability and flexibility
- Offers full IDE autocomplete support

### Before (Traditional):

```php
// Verbose, repetitive
$cache = app('cache');
$cache->set('key', 'value', 3600);

$events = app('events');
$events->dispatch('user.created', ['user' => $user]);

$db = app('db');
$users = $db->table('users')->where('active', true)->get();
```

### After (Service Accessor):

```php
// Clean, expressive, concise
Cache::set('key', 'value', 3600);

Event::dispatch('user.created', ['user' => $user]);

$users = DB::table('users')->where('active', true)->get();
```

## Why Not Just "Facade"?

We use "Service Accessor" instead of "Facade" to avoid confusion:

1. **Facade Pattern** (Gang of Four): Provides simplified interface to complex subsystem
2. **Laravel Facades**: Static proxy to services in IoC container (what we're implementing)
3. **Service Accessor**: Clearer name that describes what it does - accesses services

The term "Service Accessor" is more semantically accurate for Clean Architecture:
- **Service**: Business logic component in application layer
- **Accessor**: Provides access to that service
- Not pretending to be the Facade design pattern

## Architecture

### Class Hierarchy

```
ServiceAccessor (abstract base)
    ├── Cache (concrete accessor)
    ├── Event (concrete accessor)
    ├── DB (concrete accessor)
    ├── Auth (concrete accessor)
    ├── Gate (concrete accessor)
    ├── Queue (concrete accessor)
    └── Schedule (concrete accessor)
```

### How It Works

```php
// 1. Static call
Cache::get('key');

// 2. PHP intercepts via __callStatic()
Cache::__callStatic('get', ['key'])

// 3. Resolves service from container
$container->get('cache')

// 4. Forwards to actual instance
$cacheInstance->get('key')

// 5. Caches instance for performance
// Next call reuses same instance
```

### Key Components

**ServiceAccessor (base class)**
- `setContainer()` - Set IoC container (called during bootstrap)
- `getServiceName()` - Abstract method each accessor implements
- `resolveService()` - Resolves and caches service instance
- `__callStatic()` - Intercepts and forwards static calls
- `getInstance()` - Get underlying service instance
- `swap()` - Replace instance (for testing)
- `clearResolvedInstances()` - Clear cache (for testing)

## Available Accessors

### Cache

```php
use Toporia\Framework\Support\Accessors\Cache;

// Get/Set
Cache::set('key', 'value', 3600);
$value = Cache::get('key', 'default');

// Remember pattern
$users = Cache::remember('users', 3600, function() {
    return User::all();
});

// Increment/Decrement
Cache::increment('counter');
Cache::decrement('counter', 5);

// Forever
Cache::forever('setting', 'value');

// Multiple
Cache::setMultiple(['key1' => 'val1', 'key2' => 'val2'], 3600);
$values = Cache::getMultiple(['key1', 'key2']);

// Specific driver
Cache::driver('redis')->set('key', 'value');
```

### Event

```php
use Toporia\Framework\Support\Accessors\Event;

// Dispatch event
Event::dispatch('user.created', ['user' => $user]);

// Listen to event
Event::listen('user.created', function($event) {
    Mail::send($event->getData()['user']);
});

// With priority (higher = earlier)
Event::listen('user.created', $listener, priority: 100);

// Subscribe multiple
Event::subscribe([
    'user.created' => [SomeListener::class, 10],
    'user.updated' => [AnotherListener::class],
]);

// Check listeners
if (Event::hasListeners('user.created')) {
    $listeners = Event::getListeners('user.created');
}
```

### DB

```php
use Toporia\Framework\Support\Accessors\DB;

// Query builder
$users = DB::table('users')
    ->where('active', true)
    ->orderBy('created_at', 'DESC')
    ->limit(10)
    ->get();

// Raw queries
$results = DB::select('SELECT * FROM users WHERE id = ?', [1]);
DB::insert('INSERT INTO users (name, email) VALUES (?, ?)', ['John', 'john@example.com']);
DB::update('UPDATE users SET active = ? WHERE id = ?', [true, 1]);
DB::delete('DELETE FROM users WHERE id = ?', [1]);

// Transactions
DB::beginTransaction();
try {
    DB::table('users')->insert(['name' => 'John']);
    DB::table('profiles')->insert(['user_id' => 1]);
    DB::commit();
} catch (\Exception $e) {
    DB::rollback();
    throw $e;
}

// Get PDO
$pdo = DB::getPdo();
```

### Auth

```php
use Toporia\Framework\Support\Accessors\Auth;

// Check authentication
if (Auth::check()) {
    $user = Auth::user();
    $userId = Auth::id();
}

// Guest check
if (Auth::guest()) {
    // Redirect to login
}

// Attempt login
if (Auth::attempt(['email' => $email, 'password' => $password])) {
    // Success
}

// Login user directly
Auth::login($user);

// Logout
Auth::logout();

// Specific guard
Auth::guard('api')->user();
Auth::guard('session')->attempt($credentials);
```

### Gate

```php
use Toporia\Framework\Support\Accessors\Gate;

// Define abilities
Gate::define('edit-post', function($user, $post) {
    return $user->id === $post->user_id;
});

Gate::define('delete-user', function($user, $target) {
    return $user->isAdmin() && $user->id !== $target->id;
});

// Check authorization
if (Gate::allows('edit-post', $post)) {
    // User can edit
}

if (Gate::denies('delete-user', $targetUser)) {
    // User cannot delete
}

// Authorize (throws exception if denied)
Gate::authorize('edit-post', $post);

// Multiple checks
if (Gate::any(['edit-post', 'delete-post'], $post)) {
    // User can edit OR delete
}

if (Gate::all(['view-post', 'edit-post'], $post)) {
    // User can view AND edit
}

// For specific user
if (Gate::forUser($admin)->allows('delete-user', $user)) {
    // Admin can delete user
}
```

### Queue

```php
use Toporia\Framework\Support\Accessors\Queue;

// Push job
Queue::push(new SendEmailJob($user));

// Push to specific queue
Queue::push(new SendEmailJob($user), 'emails');

// Delayed job (60 seconds)
Queue::later(new SendEmailJob($user), 60);

// Specific driver
Queue::driver('redis')->push($job);
Queue::driver('database')->later($job, 120);
```

### Schedule

```php
use Toporia\Framework\Support\Accessors\Schedule;

// Schedule callback
Schedule::call(function() {
    // Cleanup old files
})->daily();

// Schedule command
Schedule::exec('php artisan cache:clear')->everyMinute();

// Schedule job
Schedule::job(new SendNewsletterJob())->weekly();

// Conditional execution
Schedule::call($callback)
    ->daily()
    ->when(fn() => date('l') === 'Monday');

// Run due tasks (in cron)
Schedule::runDueTasks();
```

## Usage Examples

### In Controllers

```php
use Toporia\Framework\Support\Accessors\{Cache, Event, Auth, Gate, DB};

class PostController extends BaseController
{
    public function index()
    {
        // Cache query results
        $posts = Cache::remember('posts.all', 3600, function() {
            return DB::table('posts')
                ->where('published', true)
                ->orderBy('created_at', 'DESC')
                ->get();
        });

        return $this->response->json($posts);
    }

    public function store(Request $request)
    {
        // Authorize
        Gate::authorize('create-post');

        // Create post
        $post = Post::create([
            'title' => clean($request->input('title')),
            'content' => XssProtection::purify($request->input('content')),
            'user_id' => Auth::id(),
        ]);

        // Dispatch event
        Event::dispatch('post.created', ['post' => $post]);

        // Clear cache
        Cache::delete('posts.all');

        return $this->response->json($post, 201);
    }

    public function update($id, Request $request)
    {
        $post = Post::find($id);

        // Authorize
        Gate::authorize('edit-post', $post);

        // Update
        $post->update($request->only(['title', 'content']));

        // Dispatch event
        Event::dispatch('post.updated', ['post' => $post]);

        return $this->response->json($post);
    }

    public function destroy($id)
    {
        $post = Post::find($id);

        // Authorize
        Gate::authorize('delete-post', $post);

        // Delete
        $post->delete();

        // Clear cache
        Cache::delete('posts.all');

        return $this->response->noContent();
    }
}
```

### In Route Handlers

```php
// routes/web.php

$router->get('/users', function() {
    $users = DB::table('users')->get();
    return json_encode($users);
});

$router->post('/logout', function(Response $response) {
    Auth::logout();
    return $response->redirect('/');
});

$router->get('/profile', function(Response $response) {
    if (Auth::guest()) {
        return $response->redirect('/login');
    }

    $user = Auth::user();
    return $response->json($user);
});
```

### In Helper Functions

```php
// bootstrap/helpers.php

function cache_user($userId)
{
    return Cache::remember("user:{$userId}", 3600, function() use ($userId) {
        return User::find($userId);
    });
}

function current_user()
{
    return Auth::user();
}

function can($ability, ...$arguments): bool
{
    return Gate::allows($ability, ...$arguments);
}
```

### In View Templates

```php
<!-- views/posts/show.php -->

<?php if (Auth::check()): ?>
    <p>Welcome, <?= e(Auth::user()->name) ?>!</p>

    <?php if (Gate::allows('edit-post', $post)): ?>
        <a href="/posts/<?= $post->id ?>/edit">Edit Post</a>
    <?php endif; ?>
<?php else: ?>
    <a href="/login">Login to comment</a>
<?php endif; ?>
```

## Testing

### Swap Implementation

```php
use Toporia\Framework\Support\Accessors\Cache;
use Toporia\Framework\Cache\MemoryCache;

class PostControllerTest extends TestCase
{
    public function testIndexUsesCaching()
    {
        // Arrange: Swap cache with mock
        $mockCache = new MemoryCache();
        Cache::swap($mockCache);

        // Act: Call controller
        $response = $this->get('/posts');

        // Assert: Check cache was used
        $this->assertTrue($mockCache->has('posts.all'));
        $this->assertEquals(200, $response->getStatus());
    }

    protected function tearDown(): void
    {
        // Clear swapped instances after each test
        Cache::clearResolvedInstances();
    }
}
```

### Get Underlying Instance

```php
public function testCacheUsesRedis()
{
    $cache = Cache::getInstance();

    // Check driver type
    $this->assertInstanceOf(RedisCache::class, $cache);
}
```

### Check Resolution Status

```php
public function testLazyResolution()
{
    // Before first call
    $this->assertFalse(Cache::isResolved());

    // After first call
    Cache::get('key');
    $this->assertTrue(Cache::isResolved());

    // Clear
    Cache::clearResolvedInstances();
    $this->assertFalse(Cache::isResolved());
}
```

## Creating Custom Accessors

### Step 1: Create Accessor Class

```php
<?php

namespace App\Support\Accessors;

use Toporia\Framework\Foundation\ServiceAccessor;

/**
 * @method static string translate(string $key, array $replace = []) Translate key
 * @method static string choice(string $key, int $count) Pluralization
 * @method static void setLocale(string $locale) Set current locale
 * @method static string getLocale() Get current locale
 */
final class Lang extends ServiceAccessor
{
    protected static function getServiceName(): string
    {
        return 'translator';
    }
}
```

### Step 2: Register Service in Container

```php
// In ServiceProvider
$container->singleton('translator', function($c) {
    return new Translator(
        $c->get('config')->get('app.locale'),
        __DIR__ . '/lang'
    );
});
```

### Step 3: Use It

```php
use App\Support\Accessors\Lang;

// Translate
echo Lang::translate('messages.welcome', ['name' => 'John']);

// Pluralization
echo Lang::choice('messages.apples', 5);

// Change locale
Lang::setLocale('vi');
```

## Best Practices

### ✓ DO Use Service Accessors For:

- **Controllers**: Quick access to services
  ```php
  Cache::remember('key', 3600, fn() => expensive());
  ```

- **Route Handlers**: Concise inline code
  ```php
  $router->get('/users', fn() => DB::table('users')->get());
  ```

- **Helper Functions**: Global utilities
  ```php
  function current_user() { return Auth::user(); }
  ```

- **View Templates**: Simple checks
  ```php
  <?php if (Auth::check()): ?>
  ```

### ✗ DON'T Use Service Accessors For:

- **Domain Layer**: Violates Clean Architecture
  ```php
  // BAD: Domain entity using accessor
  class Order {
      public function calculateTotal() {
          $tax = Cache::get('tax_rate'); // ✗ Domain depends on infrastructure
      }
  }

  // GOOD: Inject dependency
  class Order {
      public function __construct(private TaxCalculator $taxCalculator) {}

      public function calculateTotal() {
          $tax = $this->taxCalculator->getRate(); // ✓ Clean dependency
      }
  }
  ```

- **Application Layer (Use Cases)**: Explicit dependencies better
  ```php
  // PREFER: Constructor injection
  class CreatePostHandler {
      public function __construct(
          private EventDispatcherInterface $events
      ) {}

      public function handle(CreatePostCommand $cmd) {
          // ... create post
          $this->events->dispatch(new PostCreated($post)); // ✓ Clear dependency
      }
  }
  ```

- **Unit Tests**: Mock specific dependencies
  ```php
  // PREFER: Inject mocks directly
  $handler = new CreatePostHandler($mockEvents);
  ```

### Mix Both Approaches

```php
// Presentation Layer: Use accessors for convenience
class PostController {
    public function index() {
        return Cache::remember('posts', 3600, fn() => Post::all());
    }
}

// Application Layer: Use injection for testability
class CreatePostHandler {
    public function __construct(
        private PostRepository $posts,
        private EventDispatcherInterface $events
    ) {}

    public function handle(CreatePostCommand $cmd): Post {
        $post = $this->posts->create($cmd);
        $this->events->dispatch(new PostCreated($post));
        return $post;
    }
}
```

## SOLID Principles

### Single Responsibility Principle ✓
Each accessor has one job: forward calls to its service.

```php
class Cache extends ServiceAccessor {
    protected static function getServiceName(): string {
        return 'cache'; // Only cares about cache service
    }
}
```

### Open/Closed Principle ✓
Extend by creating new accessors, don't modify base class.

```php
// Extend with new accessor
class SMS extends ServiceAccessor {
    protected static function getServiceName(): string {
        return 'sms';
    }
}
```

### Liskov Substitution Principle ✓
All accessors behave consistently via base class contract.

```php
// All accessors work the same way
Cache::get('key');
Event::dispatch('event');
DB::table('users')->get();
```

### Interface Segregation Principle ✓
Each accessor provides only methods relevant to its service.

```php
// Cache accessor: only cache methods
Cache::get(), Cache::set(), Cache::remember()

// Event accessor: only event methods
Event::dispatch(), Event::listen()
```

### Dependency Inversion Principle ✓
Depends on `ContainerInterface`, not concrete container.

```php
abstract class ServiceAccessor {
    private static ?ContainerInterface $container = null; // ✓ Abstraction

    public static function setContainer(ContainerInterface $container): void {
        self::$container = $container;
    }
}
```

## Summary

**Service Accessor Pattern = Clean Syntax + SOLID Principles + Testability**

- ✓ Elegant static-like API: `Cache::get('key')`
- ✓ Full IDE autocomplete support
- ✓ Not true static - uses IoC container
- ✓ Fully testable - swap implementations
- ✓ SOLID compliant architecture
- ✓ Perfect for presentation layer
- ✓ Mix with constructor injection in business logic

**Read more:**
- [CLAUDE.md](../CLAUDE.md) - Framework guide
- [FEATURES.md](../FEATURES.md) - All features
- [demo/service_accessor_demo.php](../demo/service_accessor_demo.php) - Live examples
