# Queue Advanced Features

This document covers advanced queue features including retry strategies, backoff mechanisms, and middleware system.

## Table of Contents

- [Overview](#overview)
- [Retry Strategies](#retry-strategies)
- [Backoff Strategies](#backoff-strategies)
- [Job Middleware](#job-middleware)
- [Complete Examples](#complete-examples)
- [Performance Considerations](#performance-considerations)
- [Best Practices](#best-practices)

## Overview

The queue system provides Laravel-compatible features for professional job processing:

- **Automatic Retry** - Configurable retry attempts with intelligent failure handling
- **Backoff Strategies** - Control delay between retry attempts (constant, exponential, custom)
- **Job Middleware** - Wrap job execution with rate limiting, overlap prevention, etc.
- **Dependency Injection** - Auto-wired dependencies in job `handle()` method
- **Failed Job Handling** - Track and manage permanently failed jobs

**Architecture:**
- Clean Architecture principles with clear separation of concerns
- SOLID design with interface-based contracts
- O(1) backoff calculation for optimal performance
- O(M) middleware pipeline where M = middleware count
- Zero overhead when features are not used

## Retry Strategies

### Basic Retry Configuration

Configure max attempts on any job:

```php
use Toporia\Framework\Queue\Job;

class MyJob extends Job
{
    protected int $maxAttempts = 5; // Retry up to 5 times

    public function handle(): void
    {
        // Job logic that might fail
    }
}
```

### Retry Priority System

The worker uses a three-tier priority system for retry delays:

1. **Priority 1: `$retryAfter` property** - Simple constant delay (seconds)
2. **Priority 2: `$backoff` strategy** - Flexible calculation via strategy pattern
3. **Priority 3: Immediate retry** - No delay (0 seconds)

```php
// Priority 1: Simple constant delay
class SendEmailJob extends Job
{
    protected int $maxAttempts = 3;
    protected int $retryAfter = 30; // Wait 30 seconds between retries
}

// Priority 2: Backoff strategy (overrides retryAfter if both set)
class ProcessApiJob extends Job
{
    protected int $maxAttempts = 5;

    public function __construct()
    {
        parent::__construct();
        $this->backoff = new ExponentialBackoff(base: 2, max: 60);
    }
}

// Priority 3: Immediate retry (no backoff configured)
class QuickJob extends Job
{
    protected int $maxAttempts = 3;
    // Retries immediately on failure
}
```

### Failed Job Handling

Override the `failed()` method to handle permanent failures:

```php
class ImportDataJob extends Job
{
    protected int $maxAttempts = 3;

    public function handle(): void
    {
        // Import logic
    }

    public function failed(\Throwable $exception): void
    {
        // Log failure
        error_log("Import failed after {$this->attempts} attempts: {$exception->getMessage()}");

        // Send notification
        // Notification::send(new ImportFailedNotification($exception));

        // Clean up resources
        $this->cleanup();
    }
}
```

## Backoff Strategies

### BackoffStrategy Interface

All backoff strategies implement this contract:

```php
namespace Toporia\Framework\Queue\Backoff;

interface BackoffStrategy
{
    /**
     * Calculate delay in seconds for given attempt number.
     *
     * @param int $attempts Current attempt number (1-indexed)
     * @return int Delay in seconds
     */
    public function calculate(int $attempts): int;
}
```

### 1. ConstantBackoff

**Use Case:** Predictable, fixed delay between retries.

**Performance:** O(1)

**Example:**

```php
use Toporia\Framework\Queue\Backoff\ConstantBackoff;

class MyJob extends Job
{
    public function __construct()
    {
        parent::__construct();

        // Wait 60 seconds between each retry
        $this->backoff = new ConstantBackoff(delay: 60);
    }
}

// Retry timeline:
// Attempt 1: Fail → Wait 60s
// Attempt 2: Fail → Wait 60s
// Attempt 3: Fail → Wait 60s
// ...
```

### 2. ExponentialBackoff

**Use Case:** Industry-standard for distributed systems. Gives failing external services time to recover.

**Performance:** O(1) - uses `pow()` function

**Formula:** `min(base^attempt, max)`

**Example:**

```php
use Toporia\Framework\Queue\Backoff\ExponentialBackoff;

class ApiRequestJob extends Job
{
    public function __construct()
    {
        parent::__construct();

        // Exponential growth: 2s, 4s, 8s, 16s, 32s, 60s (capped)
        $this->backoff = new ExponentialBackoff(
            base: 2,    // Multiplier base
            max: 60     // Maximum delay cap
        );
    }
}

// Retry timeline:
// Attempt 1: Fail → Wait 2s   (2^1)
// Attempt 2: Fail → Wait 4s   (2^2)
// Attempt 3: Fail → Wait 8s   (2^3)
// Attempt 4: Fail → Wait 16s  (2^4)
// Attempt 5: Fail → Wait 32s  (2^5)
// Attempt 6: Fail → Wait 60s  (capped at max)
```

**Tuning Parameters:**

```php
// Aggressive retry (good for transient network issues)
new ExponentialBackoff(base: 2, max: 30);
// Timeline: 2s, 4s, 8s, 16s, 30s, 30s, ...

// Conservative retry (good for rate-limited APIs)
new ExponentialBackoff(base: 3, max: 300);
// Timeline: 3s, 9s, 27s, 81s, 243s, 300s, ...

// Slow growth
new ExponentialBackoff(base: 1.5, max: 120);
// Timeline: 1s, 2s, 3s, 5s, 7s, 11s, 17s, 25s, 38s, 57s, 85s, 120s, ...
```

### 3. CustomBackoff

**Use Case:** Complex domain-specific retry logic.

**Performance:**
- Array-based: O(1)
- Callable-based: O(N) where N = callback complexity

**Array-Based Example:**

```php
use Toporia\Framework\Queue\Backoff\CustomBackoff;

class MyJob extends Job
{
    public function __construct()
    {
        parent::__construct();

        // Custom progression: aggressive start, then back off
        $this->backoff = new CustomBackoff([5, 10, 30, 60, 120, 300]);
    }
}

// Retry timeline:
// Attempt 1: Fail → Wait 5s
// Attempt 2: Fail → Wait 10s
// Attempt 3: Fail → Wait 30s
// Attempt 4: Fail → Wait 60s
// Attempt 5: Fail → Wait 120s
// Attempt 6+: Fail → Wait 300s (uses last value)
```

**Callable-Based Example:**

```php
class TimeAwareJob extends Job
{
    public function __construct()
    {
        parent::__construct();

        // Business hours awareness: longer delays during peak hours
        $this->backoff = new CustomBackoff(function(int $attempt): int {
            $hour = (int)date('H');

            // During business hours (9 AM - 5 PM), wait longer
            if ($hour >= 9 && $hour < 17) {
                return $attempt * 30; // 30s, 60s, 90s, ...
            }

            // Off hours: retry faster
            return $attempt * 5; // 5s, 10s, 15s, ...
        });
    }
}
```

**Advanced Custom Logic:**

```php
// API budget-aware backoff
$this->backoff = new CustomBackoff(function(int $attempt) use ($apiClient): int {
    $remaining = $apiClient->getRemainingQuota();

    if ($remaining < 100) {
        return 300; // Low quota: wait 5 minutes
    } elseif ($remaining < 1000) {
        return 60;  // Medium quota: wait 1 minute
    } else {
        return $attempt * 5; // High quota: retry normally
    }
});

// Database load-aware backoff
$this->backoff = new CustomBackoff(function(int $attempt) use ($db): int {
    $load = $db->getCurrentLoad();

    return $load > 80
        ? $attempt * 30  // High load: back off aggressively
        : $attempt * 10; // Normal load: standard backoff
});
```

## Job Middleware

Middleware wraps job execution with before/after logic using a pipeline pattern.

### JobMiddleware Interface

```php
namespace Toporia\Framework\Queue\Middleware;

interface JobMiddleware
{
    /**
     * Handle the job through middleware.
     *
     * @param JobInterface $job
     * @param callable $next Next middleware in pipeline
     * @return mixed
     */
    public function handle(JobInterface $job, callable $next): mixed;
}
```

### Registering Middleware

Override the `middleware()` method in your job:

```php
class MyJob extends Job
{
    public function middleware(): array
    {
        return [
            new RateLimited(app('limiter'), maxAttempts: 60, decayMinutes: 1),
            new WithoutOverlapping(app('cache')),
            new CustomMiddleware(),
        ];
    }
}
```

**Execution Order:** Middleware executes in array order (first to last).

### 1. RateLimited Middleware

**Use Case:** Prevent job from executing too frequently. Ideal for API rate limits, external service quotas.

**Features:**
- Cache-based rate limiting
- Configurable max attempts and decay period
- Custom rate limit keys
- Automatic retry with appropriate delay

**Basic Usage:**

```php
use Toporia\Framework\Queue\Middleware\RateLimited;

class SendEmailJob extends Job
{
    public function middleware(): array
    {
        return [
            // Max 100 emails per minute
            new RateLimited(
                limiter: app('limiter'),
                maxAttempts: 100,
                decayMinutes: 1
            ),
        ];
    }
}
```

**Custom Rate Limit Key:**

```php
class ApiRequestJob extends Job
{
    public function __construct(private string $apiEndpoint) {
        parent::__construct();
    }

    public function middleware(): array
    {
        return [
            // Rate limit per endpoint (not per job class)
            new RateLimited(
                limiter: app('limiter'),
                maxAttempts: 10,
                decayMinutes: 1,
                key: "api-request:{$this->apiEndpoint}"
            ),
        ];
    }
}
```

**Different Limits for Different Scenarios:**

```php
class ProcessPaymentJob extends Job
{
    public function middleware(): array
    {
        if ($this->isHighRisk()) {
            // Strict rate limit for high-risk payments
            return [
                new RateLimited(app('limiter'), maxAttempts: 10, decayMinutes: 1),
            ];
        }

        // Relaxed limit for normal payments
        return [
            new RateLimited(app('limiter'), maxAttempts: 100, decayMinutes: 1),
        ];
    }
}
```

**How It Works:**

When rate limit is exceeded:
1. Middleware throws `RateLimitExceededException` with retry delay
2. Worker catches exception and re-queues job with appropriate delay
3. Job doesn't count as failed attempt
4. Original exception is preserved for logging

### 2. WithoutOverlapping Middleware

**Use Case:** Ensure only one instance of a job runs at a time. Prevents race conditions and duplicate processing.

**Features:**
- Cache-based distributed locking
- Configurable lock expiration
- Custom lock keys
- Automatic lock cleanup
- Guaranteed lock release even on exception

**Basic Usage:**

```php
use Toporia\Framework\Queue\Middleware\WithoutOverlapping;

class ProcessReportJob extends Job
{
    public function middleware(): array
    {
        return [
            // Prevent concurrent execution
            new WithoutOverlapping(
                cache: app('cache'),
                expireAfter: 3600  // Lock expires after 1 hour
            ),
        ];
    }
}
```

**Custom Lock Key (Partial Overlapping Prevention):**

```php
class ProcessUserDataJob extends Job
{
    public function __construct(private int $userId) {
        parent::__construct();
    }

    public function middleware(): array
    {
        return [
            // Prevent overlapping per user (not globally)
            (new WithoutOverlapping(app('cache')))
                ->by("process-user-{$this->userId}")
                ->expireAfter(600),  // 10 minutes
        ];
    }
}

// Result:
// - Multiple jobs for different users can run concurrently
// - Only one job per user runs at a time
```

**Advanced Lock Configuration:**

```php
class ImportFileJob extends Job
{
    public function __construct(private string $filename) {
        parent::__construct();
    }

    public function middleware(): array
    {
        return [
            // Lock per file, with safety timeout
            (new WithoutOverlapping(app('cache')))
                ->by("import-file:{$this->filename}")
                ->expireAfter(7200)  // 2 hours max (safety timeout)
        ];
    }
}
```

**How It Works:**

When lock is already held:
1. Middleware throws `JobAlreadyRunningException`
2. Worker catches exception and re-queues job after 60 seconds
3. Job doesn't count as failed attempt
4. Lock is automatically released when job completes or fails

**Lock Guarantees:**

- Lock is **always released** even if job throws exception
- Uses try-finally pattern for guaranteed cleanup
- Lock automatically expires after `expireAfter` seconds (safety mechanism)
- Works across multiple workers (distributed locking via cache)

### Creating Custom Middleware

```php
use Toporia\Framework\Queue\Middleware\JobMiddleware;
use Toporia\Framework\Queue\Contracts\JobInterface;

class LogJobExecution implements JobMiddleware
{
    public function handle(JobInterface $job, callable $next): mixed
    {
        $startTime = microtime(true);

        try {
            // Before job execution
            error_log("Starting job: " . get_class($job));

            // Execute job (and remaining middleware)
            $result = $next($job);

            // After successful execution
            $duration = microtime(true) - $startTime;
            error_log("Job completed in {$duration}s: " . get_class($job));

            return $result;
        } catch (\Throwable $e) {
            // After failed execution
            $duration = microtime(true) - $startTime;
            error_log("Job failed in {$duration}s: " . get_class($job) . " - {$e->getMessage()}");

            throw $e; // Re-throw to continue error handling
        }
    }
}
```

**Custom Middleware Examples:**

```php
// Circuit breaker pattern
class CircuitBreaker implements JobMiddleware
{
    public function __construct(
        private CacheInterface $cache,
        private int $failureThreshold = 5,
        private int $timeout = 60
    ) {}

    public function handle(JobInterface $job, callable $next): mixed
    {
        $key = "circuit:" . get_class($job);
        $failures = (int) $this->cache->get($key, 0);

        if ($failures >= $this->failureThreshold) {
            throw new \RuntimeException("Circuit breaker open for " . get_class($job));
        }

        try {
            $result = $next($job);
            $this->cache->delete($key); // Reset on success
            return $result;
        } catch (\Throwable $e) {
            $this->cache->set($key, $failures + 1, $this->timeout);
            throw $e;
        }
    }
}

// Performance monitoring
class MonitorPerformance implements JobMiddleware
{
    public function __construct(private MetricsService $metrics) {}

    public function handle(JobInterface $job, callable $next): mixed
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage();

        try {
            $result = $next($job);

            $this->metrics->record([
                'job' => get_class($job),
                'duration' => microtime(true) - $startTime,
                'memory' => memory_get_usage() - $startMemory,
                'status' => 'success',
            ]);

            return $result;
        } catch (\Throwable $e) {
            $this->metrics->record([
                'job' => get_class($job),
                'duration' => microtime(true) - $startTime,
                'status' => 'failed',
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
```

## Complete Examples

### Example 1: API Request with Full Features

```php
use Toporia\Framework\Queue\Job;
use Toporia\Framework\Queue\Backoff\ExponentialBackoff;
use Toporia\Framework\Queue\Middleware\{RateLimited, WithoutOverlapping};

class ProcessApiRequestJob extends Job
{
    protected int $maxAttempts = 5;

    public function __construct(
        private string $apiEndpoint,
        private array $data
    ) {
        parent::__construct();

        // Exponential backoff: 2s, 4s, 8s, 16s, 32s (max 60s)
        $this->backoff = new ExponentialBackoff(base: 2, max: 60);
    }

    public function middleware(): array
    {
        return [
            // Rate limit: max 10 API calls per minute
            new RateLimited(
                limiter: app('limiter'),
                maxAttempts: 10,
                decayMinutes: 1
            ),

            // Prevent overlapping requests to same endpoint
            (new WithoutOverlapping(app('cache')))
                ->by("api-request-{$this->apiEndpoint}")
                ->expireAfter(300), // 5 minutes max execution time
        ];
    }

    public function handle(): void
    {
        // Simulate API call
        $response = $this->callApi($this->apiEndpoint, $this->data);
        $this->processResponse($response);
    }

    public function failed(\Throwable $exception): void
    {
        error_log(sprintf(
            'API request failed after %d attempts: %s',
            $this->attempts,
            $exception->getMessage()
        ));

        // Send alert
        // Notification::send(new ApiRequestFailedNotification($this->apiEndpoint));
    }

    private function callApi(string $endpoint, array $data): array
    {
        // Actual API call logic
        // ...
        return ['status' => 'success'];
    }

    private function processResponse(array $response): void
    {
        // Process API response
        // ...
    }
}
```

### Example 2: Email with Simple Retry

```php
use Toporia\Framework\Queue\Job;
use Toporia\Framework\Queue\Middleware\RateLimited;

class SendEmailJob extends Job
{
    protected int $maxAttempts = 3;
    protected int $retryAfter = 30; // Simple 30-second delay

    public function __construct(
        private string $to,
        private string $subject,
        private string $body
    ) {
        parent::__construct();
    }

    public function middleware(): array
    {
        return [
            // Rate limit: max 100 emails per minute
            new RateLimited(
                limiter: app('limiter'),
                maxAttempts: 100,
                decayMinutes: 1
            ),
        ];
    }

    public function handle(): void
    {
        mail($this->to, $this->subject, $this->body);
    }

    public function failed(\Throwable $exception): void
    {
        error_log("Failed to send email to {$this->to}: {$exception->getMessage()}");
    }
}
```

### Example 3: Database Import with Custom Backoff

```php
use Toporia\Framework\Queue\Job;
use Toporia\Framework\Queue\Backoff\CustomBackoff;
use Toporia\Framework\Queue\Middleware\WithoutOverlapping;

class ImportLargeDatasetJob extends Job
{
    protected int $maxAttempts = 10;

    public function __construct(private string $filename) {
        parent::__construct();

        // Custom backoff: quick retries first, then back off
        $this->backoff = new CustomBackoff([10, 30, 60, 120, 300, 600]);
    }

    public function middleware(): array
    {
        return [
            // Ensure only one import runs at a time
            (new WithoutOverlapping(app('cache')))
                ->by("import-{$this->filename}")
                ->expireAfter(7200), // 2 hours
        ];
    }

    public function handle(): void
    {
        $handle = fopen($this->filename, 'r');

        try {
            while (($row = fgetcsv($handle)) !== false) {
                $this->importRow($row);
            }
        } finally {
            fclose($handle);
        }
    }

    public function failed(\Throwable $exception): void
    {
        error_log("Import failed: {$this->filename} - {$exception->getMessage()}");
        // Notify administrators
    }

    private function importRow(array $row): void
    {
        // Import logic
    }
}
```

### Example 4: Time-Aware Processing

```php
use Toporia\Framework\Queue\Job;
use Toporia\Framework\Queue\Backoff\CustomBackoff;

class ProcessAnalyticsJob extends Job
{
    protected int $maxAttempts = 5;

    public function __construct() {
        parent::__construct();

        // Business hours awareness
        $this->backoff = new CustomBackoff(function(int $attempt): int {
            $hour = (int)date('H');

            // During business hours (9 AM - 5 PM): longer delays
            // to not impact user-facing services
            if ($hour >= 9 && $hour < 17) {
                return $attempt * 60; // 1min, 2min, 3min, ...
            }

            // Off hours: retry aggressively
            return $attempt * 10; // 10s, 20s, 30s, ...
        });
    }

    public function handle(): void
    {
        // Process analytics
        $this->generateReports();
        $this->updateDashboards();
    }

    private function generateReports(): void
    {
        // Heavy computation
    }

    private function updateDashboards(): void
    {
        // Update views
    }
}
```

## Performance Considerations

### Backoff Strategy Performance

| Strategy | Time Complexity | Space Complexity | Use Case |
|----------|----------------|------------------|----------|
| ConstantBackoff | O(1) | O(1) | Simple fixed delays |
| ExponentialBackoff | O(1) | O(1) | Industry standard for distributed systems |
| CustomBackoff (array) | O(1) | O(N) | Predefined delay sequence |
| CustomBackoff (callable) | O(N) | O(1) | Complex business logic |

**N** = Complexity of custom callable logic

### Middleware Pipeline Performance

- **Zero Overhead**: When `middleware()` returns empty array, no pipeline is built
- **O(M) Execution**: Where M = number of middleware
- **O(M) Memory**: Pipeline builds M closures using `array_reduce`
- **Early Exit**: Short-circuit exceptions prevent remaining middleware execution

### Queue Driver Performance

| Operation | Sync | Database | Redis |
|-----------|------|----------|-------|
| Push | O(1) instant | O(1) + disk I/O | O(1) memory |
| Pop | O(1) | O(log N) + disk | O(1) memory |
| Later | O(1) | O(log N) + disk | O(log N) memory |

**N** = Number of delayed jobs in queue

### Optimization Tips

1. **Use Sync Driver in Development**: Instant execution, easier debugging
2. **Use Redis in Production**: Lowest latency, highest throughput
3. **Minimize Middleware**: Each middleware adds overhead
4. **Prefer ConstantBackoff**: When exponential growth is not needed
5. **Cache Service Resolution**: Inject dependencies in constructor, not in middleware
6. **Batch Similar Jobs**: Use job chaining to reduce queue overhead
7. **Monitor Failed Jobs Table**: Archive old failures to maintain performance

## Best Practices

### 1. Choose the Right Backoff Strategy

```php
// ✅ Good: Exponential for external APIs
class ApiJob extends Job {
    public function __construct() {
        parent::__construct();
        $this->backoff = new ExponentialBackoff(base: 2, max: 60);
    }
}

// ✅ Good: Constant for internal services
class CacheWarmupJob extends Job {
    protected int $retryAfter = 10;
}

// ❌ Bad: No backoff for external services
class ApiJob extends Job {
    protected int $maxAttempts = 5;
    // Missing backoff - will hammer failing service
}
```

### 2. Set Appropriate Max Attempts

```php
// ✅ Good: Higher attempts for important jobs with backoff
class PaymentProcessingJob extends Job {
    protected int $maxAttempts = 10;
    // Backoff: new ExponentialBackoff(base: 2, max: 300)
}

// ✅ Good: Lower attempts for less critical jobs
class SendNotificationJob extends Job {
    protected int $maxAttempts = 3;
}

// ❌ Bad: Too many attempts without backoff
class AnyJob extends Job {
    protected int $maxAttempts = 100; // Will flood logs and waste resources
}
```

### 3. Use Middleware Appropriately

```php
// ✅ Good: Rate limit for external APIs
class ApiJob extends Job {
    public function middleware(): array {
        return [
            new RateLimited(app('limiter'), maxAttempts: 60, decayMinutes: 1),
        ];
    }
}

// ✅ Good: Prevent overlap for non-idempotent operations
class ProcessInvoiceJob extends Job {
    public function middleware(): array {
        return [
            (new WithoutOverlapping(app('cache')))
                ->by("invoice-{$this->invoiceId}"),
        ];
    }
}

// ❌ Bad: Unnecessary middleware for simple jobs
class LogJob extends Job {
    public function middleware(): array {
        return [
            new WithoutOverlapping(app('cache')), // Not needed for logging
        ];
    }
}
```

### 4. Always Implement failed() Method

```php
// ✅ Good: Proper failure handling
class ImportJob extends Job {
    public function failed(\Throwable $exception): void {
        error_log("Import failed: {$exception->getMessage()}");
        Notification::send(new ImportFailedNotification($exception));
        $this->cleanup();
    }
}

// ❌ Bad: Silent failure
class ImportJob extends Job {
    // No failed() method - failures go unnoticed
}
```

### 5. Use Dependency Injection

```php
// ✅ Good: DI in constructor + handle()
class ProcessOrderJob extends Job {
    public function __construct(
        private int $orderId,
        private OrderRepository $orders // Injected by container
    ) {
        parent::__construct();
    }

    public function handle(MailerInterface $mailer): void { // DI in handle()
        $order = $this->orders->find($this->orderId);
        $mailer->send($order->customer->email, ...);
    }
}

// ❌ Bad: Manual service resolution
class ProcessOrderJob extends Job {
    public function handle(): void {
        $orders = app('orders'); // Avoid global app() calls
        $mailer = app('mailer');
    }
}
```

### 6. Keep Jobs Idempotent

```php
// ✅ Good: Idempotent job
class ProcessPaymentJob extends Job {
    public function handle(): void {
        $payment = $this->payments->find($this->paymentId);

        // Check if already processed
        if ($payment->status === 'completed') {
            return; // Safe to retry
        }

        $this->processPayment($payment);
    }
}

// ❌ Bad: Non-idempotent without protection
class ProcessPaymentJob extends Job {
    public function handle(): void {
        $this->processPayment($this->paymentId); // Could charge twice!
    }
}
```

### 7. Monitor and Alert

```php
class CriticalJob extends Job {
    public function failed(\Throwable $exception): void {
        // Log to monitoring service
        app('logger')->critical('Critical job failed', [
            'job' => get_class($this),
            'attempts' => $this->attempts,
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        // Send immediate alert
        app('alerting')->sendAlert([
            'severity' => 'critical',
            'message' => 'CriticalJob failed after all retries',
            'context' => $this->getContext(),
        ]);
    }
}
```

### 8. Test Retry Logic

```php
// Unit test example
public function test_job_retries_with_exponential_backoff(): void
{
    $job = new ApiRequestJob('https://api.example.com', ['data' => 'test']);

    // First attempt
    $this->assertEquals(2, $job->getBackoffDelay()); // 2^1 = 2
    $job->incrementAttempts();

    // Second attempt
    $this->assertEquals(4, $job->getBackoffDelay()); // 2^2 = 4
    $job->incrementAttempts();

    // Third attempt
    $this->assertEquals(8, $job->getBackoffDelay()); // 2^3 = 8
}

// Integration test example
public function test_job_respects_rate_limit(): void
{
    $limiter = app('limiter');
    $job = new RateLimitedJob();

    // Process jobs up to rate limit
    for ($i = 0; $i < 60; $i++) {
        $this->queue->push(clone $job);
    }

    // Next job should be rate limited
    $this->expectException(RateLimitExceededException::class);
    $this->queue->push(clone $job);
}
```

### 9. Configure Lock Expiration Appropriately

```php
// ✅ Good: Lock expires after reasonable safety timeout
class LongRunningJob extends Job {
    public function middleware(): array {
        return [
            (new WithoutOverlapping(app('cache')))
                ->expireAfter(3600), // 1 hour - job normally takes 10-20 minutes
        ];
    }
}

// ❌ Bad: Lock expires too quickly
class LongRunningJob extends Job {
    public function middleware(): array {
        return [
            (new WithoutOverlapping(app('cache')))
                ->expireAfter(60), // 1 minute - job takes 10-20 minutes!
        ];
    }
}
```

### 10. Use Queues for Different Priorities

```php
// High priority - payment processing
class ProcessPaymentJob extends Job {
    protected string $queue = 'payments';
}

// Medium priority - emails
class SendEmailJob extends Job {
    protected string $queue = 'emails';
}

// Low priority - analytics
class ProcessAnalyticsJob extends Job {
    protected string $queue = 'analytics';
}

// Run workers with different configurations:
// php console queue:work --queue=payments --sleep=0
// php console queue:work --queue=emails --sleep=1
// php console queue:work --queue=analytics --sleep=5
```

## Architecture & SOLID Principles

This implementation follows Clean Architecture and SOLID principles:

### Single Responsibility Principle
- **BackoffStrategy**: Only calculates delays
- **JobMiddleware**: Only wraps execution
- **Worker**: Only processes jobs from queue

### Open/Closed Principle
- Add new backoff strategies by implementing `BackoffStrategy`
- Add new middleware by implementing `JobMiddleware`
- No modification to existing code required

### Liskov Substitution Principle
- All `BackoffStrategy` implementations are interchangeable
- All `JobMiddleware` implementations are interchangeable

### Interface Segregation Principle
- Minimal interfaces: `BackoffStrategy` has one method, `JobMiddleware` has one method
- Jobs only implement methods they need

### Dependency Inversion Principle
- Worker depends on `QueueInterface`, not concrete queue implementations
- Middleware depends on `CacheInterface` and `RateLimiterInterface`
- All dependencies are injected, not instantiated

## See Also

- [Queue Basics](../CLAUDE.md#queue-system) - Basic queue usage
- [Console Commands](../CLAUDE.md#console-commands) - Queue worker commands
- [Cache System](../CLAUDE.md#cache-system) - Used by middleware
- [Rate Limiting](../CLAUDE.md#rate-limiting) - Standalone rate limiting
