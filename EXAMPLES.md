# Framework Examples

Các ví dụ sử dụng các tính năng của framework.

## Security Features

### CSRF Protection Example

```php
// In your form view
<form method="POST" action="/products">
    <?= csrf_field() ?>
    <input type="text" name="title" required>
    <button type="submit">Create Product</button>
</form>

// In routes/web.php
$router->post('/products', [ProductsController::class, 'store'])
    ->middleware([CsrfProtection::class]);

// In JavaScript (for AJAX requests)
fetch('/api/products', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
    },
    body: JSON.stringify({title: 'New Product'})
});
```

### XSS Protection Example

```php
// In controllers
use Toporia\Framework\Security\XssProtection;

class ProductController extends BaseController
{
    public function store(Request $request, Response $response)
    {
        // Clean user input
        $title = XssProtection::clean($request->input('title'));

        // Or for rich text
        $description = XssProtection::purify($request->input('description'));

        // Store product...
    }
}

// In views - always escape output
<h1><?= e($product->title) ?></h1>
<div><?= XssProtection::sanitize($product->description) ?></div>
```

### Authorization Example

```php
// In bootstrap/app.php or AppServiceProvider
$gate = app('gate');

// Define abilities
$gate->define('update-product', function ($user, $product) {
    return $user->id === $product->created_by;
});

$gate->define('delete-product', function ($user, $product) {
    return $user->isAdmin();
});

// In controllers
public function update(Request $request, Response $response, int $id)
{
    $product = Product::find($id);

    // Check permission
    app('gate')->authorize('update-product', $product);

    // Or check without exception
    if (app('gate')->denies('update-product', $product)) {
        return $response->json(['error' => 'Unauthorized'], 403);
    }

    // Update product...
}

// Using middleware
$router->put('/products/{id}', [ProductController::class, 'update'])
    ->middleware([
        Authenticate::class,
        Authorize::can(app('gate'), 'update-product')
    ]);
```

## Cache System

### Basic Caching

```php
$cache = app('cache');

// Store data for 1 hour
$cache->set('products.featured', $products, 3600);

// Retrieve data
$products = $cache->get('products.featured');

// Remember pattern (get or generate)
$products = $cache->remember('products.all', 3600, function() {
    return Product::all();
});

// Forever cache
$cache->forever('site.config', $config);
```

### Using Different Drivers

```php
// File cache (default)
$fileCache = $cache->driver('file');
$fileCache->set('key', 'value', 3600);

// Redis cache (high performance)
$redisCache = $cache->driver('redis');
$redisCache->set('session:user:1', $sessionData, 7200);

// Memory cache (for testing)
$memCache = $cache->driver('memory');
```

### Cache for Database Queries

```php
class ProductRepository
{
    public function getFeaturedProducts(): array
    {
        return cache()->remember('products.featured', 3600, function() {
            return Product::where('is_featured', true)
                ->orderBy('created_at', 'DESC')
                ->limit(10)
                ->get();
        });
    }

    public function clearCache(): void
    {
        cache()->delete('products.featured');
    }
}
```

## Rate Limiting

### API Rate Limiting

```php
use Toporia\Framework\RateLimit\CacheRateLimiter;
use Toporia\Framework\Http\Middleware\ThrottleRequests;

// In routes/web.php
$limiter = new CacheRateLimiter(app('cache'));

// Limit API to 60 requests per minute
$router->get('/api/products', [ApiController::class, 'index'])
    ->middleware([
        ThrottleRequests::with($limiter, 60, 1)
    ]);

// Stricter limit for write operations
$router->post('/api/products', [ApiController::class, 'store'])
    ->middleware([
        ThrottleRequests::with($limiter, 10, 1)
    ]);
```

### Custom Rate Limiting

```php
class LoginController extends BaseController
{
    public function login(Request $request, Response $response)
    {
        $limiter = new CacheRateLimiter(app('cache'));
        $key = 'login:' . $request->ip();

        // 5 attempts per 15 minutes
        if (!$limiter->attempt($key, 5, 900)) {
            $retryAfter = $limiter->availableIn($key);
            return $response->json([
                'error' => 'Too many login attempts',
                'retry_after' => $retryAfter
            ], 429);
        }

        // Attempt login...
        if ($this->attemptLogin($request)) {
            // Clear limit on success
            $limiter->clear($key);
            return $response->json(['success' => true]);
        }

        return $response->json(['error' => 'Invalid credentials'], 401);
    }
}
```

## Queue System

### Creating and Dispatching Jobs

```php
// Create a job class
use Toporia\Framework\Queue\Job;

class SendWelcomeEmailJob extends Job
{
    public function __construct(
        private int $userId
    ) {
        parent::__construct();
    }

    public function handle(): void
    {
        $user = User::find($this->userId);

        // Send email
        mail(
            $user->email,
            'Welcome!',
            "Welcome to our platform, {$user->name}!"
        );
    }

    public function failed(\Throwable $exception): void
    {
        // Log failure
        error_log("Failed to send welcome email to user {$this->userId}: " . $exception->getMessage());
    }
}

// Dispatch the job
$queue = app('queue');

// Immediate execution (sync driver)
$job = new SendWelcomeEmailJob($user->id);
$queue->push($job);

// Delayed execution (5 minutes)
$queue->later($job, 300);

// Specific queue
$job->onQueue('emails');
$queue->push($job, 'emails');

// Configure retries
$job->tries(5);
```

### Running Queue Worker

```php
// Create worker.php
<?php

require __DIR__ . '/bootstrap/app.php';

use Toporia\Framework\Queue\Worker;

$queue = app('queue')->driver('database'); // or 'redis'
$worker = new Worker($queue, maxJobs: 100, sleep: 3);

echo "Starting queue worker...\n";
$worker->work('default');
```

Run with: `php worker.php`

### Database Queue Setup

```sql
-- Create jobs table
CREATE TABLE jobs (
    id VARCHAR(255) PRIMARY KEY,
    queue VARCHAR(255) NOT NULL,
    payload TEXT NOT NULL,
    attempts INTEGER NOT NULL DEFAULT 0,
    available_at INTEGER NOT NULL,
    created_at INTEGER NOT NULL,
    INDEX idx_queue_available (queue, available_at)
);

-- Create failed_jobs table
CREATE TABLE failed_jobs (
    id VARCHAR(255) PRIMARY KEY,
    queue VARCHAR(255) NOT NULL,
    payload TEXT NOT NULL,
    exception TEXT NOT NULL,
    failed_at INTEGER NOT NULL
);
```

## Task Scheduling

### Defining Scheduled Tasks

```php
// Create schedule.php
<?php

require __DIR__ . '/bootstrap/app.php';

$schedule = app('schedule');

// Run every minute
$schedule->call(function () {
    // Process pending notifications
    processNotifications();
})->everyMinute()->description('Process notifications');

// Run daily at 2 AM
$schedule->call(function () {
    // Backup database
    exec('mysqldump -u root myapp > backup.sql');
})->dailyAt('02:00')->description('Backup database');

// Run every hour
$schedule->call(function () {
    // Clean up old sessions
    cleanupSessions();
})->hourly();

// Run weekly on Sunday
$schedule->call(function () {
    // Generate weekly report
    generateWeeklyReport();
})->weekly()->sundays()->at('08:00');

// Queue a job every 15 minutes
$schedule->job(ProcessReportsJob::class)
    ->everyMinutes(15)
    ->weekdays()
    ->description('Process reports');

// Execute shell command
$schedule->exec('php cleanup.php')
    ->daily()
    ->description('Cleanup temp files');

// Conditional execution
$schedule->call(function () {
    sendDailyReport();
})->daily()->when(function () {
    return date('N') < 6; // Monday-Friday only
});

// Run due tasks
$schedule->runDueTasks();
```

### Cron Job Setup

Add to crontab (`crontab -e`):

```bash
* * * * * php /path/to/project/schedule.php >> /dev/null 2>&1
```

## Complete Application Example

### Product Management with All Features

```php
// Product Controller
class ProductController extends BaseController
{
    public function __construct(
        Request $request,
        Response $response,
        private ProductRepository $products,
        private GateInterface $gate
    ) {
        parent::__construct($request, $response);
    }

    public function index()
    {
        // Cache product list
        $products = cache()->remember('products.all', 3600, function() {
            return $this->products->findAll();
        });

        return $this->view('products/index', ['products' => $products]);
    }

    public function store()
    {
        // Rate limit product creation
        $limiter = new CacheRateLimiter(app('cache'));
        $key = 'create_product:' . auth()->user()->id;

        if (!$limiter->attempt($key, 5, 60)) {
            return $this->response->json([
                'error' => 'Too many products created'
            ], 429);
        }

        // Validate and sanitize input
        $title = XssProtection::clean($this->request->input('title'));
        $description = XssProtection::purify($this->request->input('description'));

        // Create product
        $product = new Product(
            id: null,
            title: $title,
            description: $description
        );

        $saved = $this->products->store($product);

        // Clear cache
        cache()->delete('products.all');

        // Queue notification
        $job = new NotifyAdminJob($saved->id);
        app('queue')->push($job);

        return $this->response->json($saved, 201);
    }

    public function update(int $id)
    {
        $product = $this->products->findById($id);

        // Check authorization
        $this->gate->authorize('update-product', $product);

        // Update product...

        // Clear cache
        cache()->delete('products.all');

        return $this->response->json($product);
    }
}

// Routes
$router->get('/products', [ProductController::class, 'index'])
    ->middleware([AddSecurityHeaders::class]);

$router->post('/products', [ProductController::class, 'store'])
    ->middleware([
        Authenticate::class,
        CsrfProtection::class,
        ThrottleRequests::with($limiter, 10, 1)
    ]);

$router->put('/products/{id}', [ProductController::class, 'update'])
    ->middleware([
        Authenticate::class,
        CsrfProtection::class,
        Authorize::can($gate, 'update-product')
    ]);
```

## Testing Examples

### Testing with Mock Cache

```php
use Toporia\Framework\Cache\MemoryCache;

class ProductRepositoryTest extends TestCase
{
    public function testCachedProducts()
    {
        // Use memory cache for testing
        $cache = new MemoryCache();
        app()->getContainer()->instance('cache', $cache);

        $repo = new ProductRepository();

        // First call - hits database
        $products1 = $repo->getFeaturedProducts();

        // Second call - from cache
        $products2 = $repo->getFeaturedProducts();

        $this->assertSame($products1, $products2);
    }
}
```

### Testing with Sync Queue

```php
class NotificationTest extends TestCase
{
    public function testNotificationIsSent()
    {
        // Use sync queue for immediate execution
        $queue = new SyncQueue();
        app()->getContainer()->instance('queue', $queue);

        $job = new SendNotificationJob($user->id);
        $queue->push($job);

        // Job executed immediately in tests
        $this->assertTrue($user->hasNotification());
    }
}
```
