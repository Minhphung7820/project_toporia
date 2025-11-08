<?php

declare(strict_types=1);

/**
 * Service Accessor Pattern Demo
 *
 * Demonstrates the elegant way to access services from container.
 */

require __DIR__ . '/../vendor/autoload.php';

use Toporia\Framework\Container\Container;
use Toporia\Framework\Foundation\ServiceAccessor;
use Toporia\Framework\Cache\MemoryCache;
use Toporia\Framework\Cache\CacheManager;
use Toporia\Framework\Events\Dispatcher;
use Toporia\Framework\Support\Accessors\Cache;
use Toporia\Framework\Support\Accessors\Event;

echo "=== Service Accessor Pattern Demo ===\n\n";

// Setup container with services
$container = new Container();

// Register cache service
$container->singleton('cache', function() {
    return new CacheManager([
        'default' => 'memory',
        'stores' => [
            'memory' => [
                'driver' => 'memory',
            ],
        ],
    ]);
});

// Register event service
$container->singleton('events', function() {
    return new Dispatcher();
});

// Set container for ServiceAccessor
ServiceAccessor::setContainer($container);

echo "✓ Container configured with services\n\n";

// ============================================================================
// 1. Traditional Way (verbose, less elegant)
// ============================================================================

echo "1. Traditional Way - Verbose:\n";
echo "   " . str_repeat("-", 60) . "\n";

echo "   // Get service from container\n";
echo "   \$cache = \$container->get('cache');\n";
echo "   \$cache->set('key', 'value', 60);\n";
echo "   \$value = \$cache->get('key');\n\n";

// Actually do it
$cache = $container->get('cache');
$cache->set('traditional', 'value1', 60);
$value = $cache->get('traditional');
echo "   Result: {$value}\n\n";

// ============================================================================
// 2. Service Accessor Way (clean, elegant)
// ============================================================================

echo "2. Service Accessor Way - Elegant:\n";
echo "   " . str_repeat("-", 60) . "\n";

echo "   // Static-like interface (but not static!)\n";
echo "   Cache::set('key', 'value', 60);\n";
echo "   \$value = Cache::get('key');\n\n";

// Actually do it
Cache::set('elegant', 'value2', 60);
$value = Cache::get('elegant');
echo "   Result: {$value}\n\n";

echo "   ✓ Shorter, cleaner, more expressive!\n\n";

// ============================================================================
// 3. Comparison: Before vs After
// ============================================================================

echo "3. Before vs After Comparison:\n";
echo "   " . str_repeat("=", 60) . "\n\n";

echo "   BEFORE (Traditional):\n";
echo "   --------------------\n";
echo "   \$cache = app('cache');\n";
echo "   \$users = \$cache->remember('users', 3600, function() {\n";
echo "       return User::all();\n";
echo "   });\n\n";

echo "   AFTER (Service Accessor):\n";
echo "   -------------------------\n";
echo "   \$users = Cache::remember('users', 3600, function() {\n";
echo "       return User::all();\n";
echo "   });\n\n";

echo "   ✓ Removed boilerplate, direct to the point!\n\n";

// ============================================================================
// 4. Multiple Services Examples
// ============================================================================

echo "4. Multiple Services:\n";
echo "   " . str_repeat("=", 60) . "\n\n";

echo "   a) Cache Service:\n";
Cache::set('user:123', ['name' => 'John'], 3600);
$user = Cache::get('user:123');
echo "      Cache::set('user:123', ['name' => 'John'], 3600)\n";
echo "      Cache::get('user:123') => " . json_encode($user) . "\n\n";

echo "   b) Event Service:\n";
echo "      Event::listen('user.created', \$listener)\n";
echo "      Event::dispatch('user.created', ['user' => \$user])\n";

Event::listen('test.event', function($event) {
    echo "      → Event listener triggered!\n";
});
Event::dispatch('test.event');
echo "\n";

// ============================================================================
// 5. Benefits
// ============================================================================

echo "5. Benefits of Service Accessor Pattern:\n";
echo "   " . str_repeat("=", 60) . "\n\n";

echo "   ✓ Clean Syntax:\n";
echo "     - Cache::get('key') vs \$container->get('cache')->get('key')\n";
echo "     - Event::dispatch() vs app('events')->dispatch()\n\n";

echo "   ✓ IDE Support:\n";
echo "     - Full autocomplete (via @method docblocks)\n";
echo "     - Type hints for parameters and return values\n";
echo "     - Jump to definition works\n\n";

echo "   ✓ Testable:\n";
echo "     - Not true static (uses container)\n";
echo "     - Can swap implementations: Cache::swap(\$mockCache)\n";
echo "     - Can inject different instances per test\n\n";

echo "   ✓ SOLID Principles:\n";
echo "     - Single Responsibility: Only forwards to container\n";
echo "     - Open/Closed: Extend via new accessor classes\n";
echo "     - Dependency Inversion: Depends on ContainerInterface\n\n";

// ============================================================================
// 6. Testing Support
// ============================================================================

echo "6. Testing Support:\n";
echo "   " . str_repeat("=", 60) . "\n\n";

echo "   a) Swap implementation:\n";
$mockCache = new MemoryCache();
$mockCache->set('test', 'mocked');

Cache::swap($mockCache);
echo "      Cache::swap(\$mockCache)\n";
echo "      Cache::get('test') => " . Cache::get('test') . "\n\n";

echo "   b) Check if resolved:\n";
echo "      Cache::isResolved() => " . (Cache::isResolved() ? 'true' : 'false') . "\n\n";

echo "   c) Clear instances (force re-resolve):\n";
echo "      Cache::clearResolvedInstances()\n";
Cache::clearResolvedInstances();
echo "      Cache::isResolved() => " . (Cache::isResolved() ? 'true' : 'false') . "\n\n";

echo "   d) Get underlying instance:\n";
Cache::set('test2', 'value'); // Re-resolve
$instance = Cache::getInstance();
echo "      \$cache = Cache::getInstance()\n";
echo "      get_class(\$cache) => " . get_class($instance) . "\n\n";

// ============================================================================
// 7. Available Accessors
// ============================================================================

echo "7. Available Service Accessors:\n";
echo "   " . str_repeat("=", 60) . "\n\n";

$accessors = [
    'Cache' => [
        'Service' => 'CacheManager',
        'Methods' => 'get, set, remember, forever, increment, decrement',
        'Example' => "Cache::remember('key', 3600, fn() => expensive())",
    ],
    'Event' => [
        'Service' => 'EventDispatcher',
        'Methods' => 'dispatch, listen, subscribe',
        'Example' => "Event::dispatch('user.created', ['user' => \$user])",
    ],
    'DB' => [
        'Service' => 'Connection',
        'Methods' => 'table, query, select, insert, update, delete',
        'Example' => "DB::table('users')->where('active', true)->get()",
    ],
    'Auth' => [
        'Service' => 'AuthManager',
        'Methods' => 'check, user, attempt, login, logout',
        'Example' => "Auth::check() ? Auth::user() : null",
    ],
    'Gate' => [
        'Service' => 'Gate',
        'Methods' => 'allows, denies, authorize, define',
        'Example' => "Gate::allows('edit-post', \$post)",
    ],
    'Queue' => [
        'Service' => 'QueueManager',
        'Methods' => 'push, later, pop',
        'Example' => "Queue::push(new SendEmailJob(\$user))",
    ],
    'Schedule' => [
        'Service' => 'Scheduler',
        'Methods' => 'call, exec, job, runDueTasks',
        'Example' => "Schedule::call(fn() => cleanup())->daily()",
    ],
];

foreach ($accessors as $name => $info) {
    echo "   {$name}::\n";
    echo "     Service:  {$info['Service']}\n";
    echo "     Methods:  {$info['Methods']}\n";
    echo "     Example:  {$info['Example']}\n\n";
}

// ============================================================================
// 8. Real-World Usage Examples
// ============================================================================

echo "8. Real-World Usage in Controllers:\n";
echo "   " . str_repeat("=", 60) . "\n\n";

echo "   A) Cache user data:\n";
echo "   -------------------\n";
echo "   public function show(\$id)\n";
echo "   {\n";
echo "       \$user = Cache::remember(\"user:{\$id}\", 3600, function() use (\$id) {\n";
echo "           return User::find(\$id);\n";
echo "       });\n";
echo "       return \$this->response->json(\$user);\n";
echo "   }\n\n";

echo "   B) Dispatch events:\n";
echo "   -------------------\n";
echo "   public function store(Request \$request)\n";
echo "   {\n";
echo "       \$post = Post::create(\$request->all());\n";
echo "       Event::dispatch('post.created', ['post' => \$post]);\n";
echo "       return \$this->response->json(\$post, 201);\n";
echo "   }\n\n";

echo "   C) Authorization:\n";
echo "   -----------------\n";
echo "   public function delete(\$id)\n";
echo "   {\n";
echo "       \$post = Post::find(\$id);\n";
echo "       Gate::authorize('delete-post', \$post);\n";
echo "       \$post->delete();\n";
echo "   }\n\n";

echo "   D) Database queries:\n";
echo "   --------------------\n";
echo "   public function index()\n";
echo "   {\n";
echo "       \$users = DB::table('users')\n";
echo "           ->where('active', true)\n";
echo "           ->orderBy('created_at', 'DESC')\n";
echo "           ->limit(10)\n";
echo "           ->get();\n";
echo "       return \$this->response->json(\$users);\n";
echo "   }\n\n";

echo "   E) Queue jobs:\n";
echo "   --------------\n";
echo "   public function sendNotification(\$userId)\n";
echo "   {\n";
echo "       \$user = User::find(\$userId);\n";
echo "       Queue::push(new SendEmailJob(\$user));\n";
echo "       return \$this->response->json(['queued' => true]);\n";
echo "   }\n\n";

// ============================================================================
// 9. How It Works (Behind the Scenes)
// ============================================================================

echo "9. How It Works Behind the Scenes:\n";
echo "   " . str_repeat("=", 60) . "\n\n";

echo "   When you call: Cache::get('key')\n\n";

echo "   1. PHP intercepts static call via __callStatic()\n";
echo "      → Cache::__callStatic('get', ['key'])\n\n";

echo "   2. ServiceAccessor resolves service from container:\n";
echo "      → \$container->get('cache')\n\n";

echo "   3. Forwards method call to resolved instance:\n";
echo "      → \$cacheInstance->get('key')\n\n";

echo "   4. Caches instance for subsequent calls (performance)\n\n";

echo "   ✓ Not true static - uses container dependency injection!\n";
echo "   ✓ Fully testable - can swap implementations!\n";
echo "   ✓ Clean syntax - no boilerplate!\n\n";

// ============================================================================
// 10. Best Practices
// ============================================================================

echo "10. Best Practices:\n";
echo "    " . str_repeat("=", 60) . "\n\n";

echo "    ✓ Use Service Accessors in:\n";
echo "      - Controllers (quick access to services)\n";
echo "      - Route handlers (concise code)\n";
echo "      - Helper functions (global utilities)\n";
echo "      - View templates (e.g., Auth::check())\n\n";

echo "    ✓ Use Constructor Injection in:\n";
echo "      - Domain/Application layer (testability)\n";
echo "      - Complex services (explicit dependencies)\n";
echo "      - Unit tests (clear dependencies)\n\n";

echo "    ✓ Mix both approaches:\n";
echo "      - Accessors for convenience in presentation layer\n";
echo "      - Injection for clarity in business logic\n\n";

echo "    ✗ Avoid:\n";
echo "      - Overusing in domain layer (breaks Clean Architecture)\n";
echo "      - Using for non-registered services (runtime error)\n";
echo "      - Thinking it's true static (it's not!)\n\n";

echo "=== Demo Complete ===\n";
