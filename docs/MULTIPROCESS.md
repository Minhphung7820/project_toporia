# Multi-Process System

Professional multi-process execution system for true parallel processing with PCNTL fork.

## Table of Contents

- [Overview](#overview)
- [Architecture](#architecture)
- [Quick Start](#quick-start)
- [Process Facade](#process-facade)
- [ProcessManager](#processmanager)
- [ProcessPool](#processpool)
- [ForkProcess](#forkprocess)
- [Performance](#performance)
- [Best Practices](#best-practices)
- [Troubleshooting](#troubleshooting)

## Overview

The multi-process system provides **true parallel execution** using PHP's PCNTL extension. Each process runs in **isolated memory** with automatic resource cleanup.

**Key Features:**
- ✅ True parallel execution with fork-based multiprocessing
- ✅ Memory isolation (shared-nothing architecture)
- ✅ Automatic resource cleanup and zombie process prevention
- ✅ Signal handling for graceful shutdown (SIGTERM, SIGINT)
- ✅ Non-blocking wait for optimal performance
- ✅ CPU core auto-detection
- ✅ Clean Architecture with SOLID principles
- ✅ High reusability with facade pattern

**Requirements:**
- PHP 8.1+ with PCNTL extension
- Unix-like OS (Linux, macOS) - not supported on Windows
- Fork privileges (typically requires CLI environment)

**Check if supported:**
```php
use Toporia\Framework\Support\Accessors\Process;

if (!Process::isSupported()) {
    die('PCNTL extension not available');
}

$cores = Process::getCpuCores();
echo "CPU cores: {$cores}\n";
```

## Architecture

```
┌─────────────────────────────────────────────┐
│          Process Facade                     │
│  (Static accessor with helper methods)      │
└──────────────┬──────────────────────────────┘
               │
       ┌───────┴───────┐
       │               │
┌──────▼──────┐  ┌────▼─────┐
│ProcessManager│  │ProcessPool│
│ (Task Queue) │  │(Map/Reduce)│
└──────┬──────┘  └────┬─────┘
       │               │
       └───────┬───────┘
               │
        ┌──────▼──────┐
        │ ForkProcess │
        │  (PCNTL)    │
        └─────────────┘
```

**Contracts (Interfaces):**
- `ProcessInterface` - Single process management
- `ProcessManagerInterface` - Process pool with concurrency control
- `WorkerInterface` - Worker pool abstraction

**Implementations:**
- `ForkProcess` - PCNTL fork-based process
- `ProcessManager` - Manages process pool with resource limits
- `ProcessPool` - High-performance parallel map/filter/reduce

**Service Provider:**
- `ProcessServiceProvider` - Registers services with CPU core detection

## Quick Start

### Using Process Facade

```php
use Toporia\Framework\Support\Accessors\Process;

// Run tasks in parallel
$results = Process::run([
    fn() => heavyComputation1(),
    fn() => heavyComputation2(),
    fn() => heavyComputation3(),
], maxConcurrent: 4);

// Map over array in parallel
$results = Process::map([1, 2, 3, 4, 5], function($n) {
    return $n * 2;
});
// Result: [2, 4, 6, 8, 10]

// Filter in parallel
$evens = Process::filter([1, 2, 3, 4, 5], fn($n) => $n % 2 === 0);
// Result: [2, 4]

// Reduce in parallel
$sum = Process::reduce([1, 2, 3, 4, 5], fn($acc, $n) => $acc + $n, 0);
// Result: 15
```

### Using Container

```php
// Get ProcessManager
$manager = app('process.manager');

// Get ProcessPool (auto-detects CPU cores)
$pool = app('process.pool');
```

## Process Facade

Static accessor for convenient multi-process operations.

### Available Methods

```php
use Toporia\Framework\Support\Accessors\Process;

// Get instances
$manager = Process::manager();
$pool = Process::pool(workerCount: 4);

// Run tasks in parallel
$results = Process::run($tasks, maxConcurrent: 4);

// Process pool operations
$results = Process::map($items, $callback, workerCount: null);
$filtered = Process::filter($items, $callback, workerCount: null);
$reduced = Process::reduce($items, $callback, $initial, workerCount: null);

// Single fork
$process = Process::fork($callback, $args);

// System info
$supported = Process::isSupported();
$cores = Process::getCpuCores();
```

### Example: Parallel API Requests

```php
use Toporia\Framework\Support\Accessors\Process;

$urls = [
    'https://api.example.com/users',
    'https://api.example.com/posts',
    'https://api.example.com/comments',
];

// Fetch all URLs in parallel
$results = Process::map($urls, function($url) {
    return file_get_contents($url);
});

foreach ($results as $i => $data) {
    echo "URL {$urls[$i]}: " . strlen($data) . " bytes\n";
}
```

## ProcessManager

Manages a pool of forked processes with concurrency control.

### Basic Usage

```php
use Toporia\Framework\Process\ProcessManager;

$manager = new ProcessManager();

// Add tasks
for ($i = 0; $i < 100; $i++) {
    $manager->add(function($n) {
        return $n * 2;
    }, [$i]);
}

// Run with max 4 concurrent processes
$results = $manager->run(maxConcurrent: 4);

// Results in order of tasks
echo "Processed " . count($results) . " tasks\n";
```

### Methods

```php
// Add task to queue
$process = $manager->add(callable $callback, array $args = []): ProcessInterface;

// Run all tasks with concurrency limit
$results = $manager->run(int $maxConcurrent = 4): array;

// Wait for all processes to complete
$results = $manager->wait(): array;

// Get running process count
$count = $manager->getRunningCount(): int;

// Check if any process is running
$hasRunning = $manager->hasRunning(): bool;

// Kill all processes (SIGTERM or SIGKILL)
$manager->killAll(int $signal = SIGTERM): void;
```

### Example: Batch Processing

```php
use Toporia\Framework\Process\ProcessManager;

class BatchProcessor
{
    public function processRecords(array $records): array
    {
        $manager = new ProcessManager();

        foreach ($records as $record) {
            $manager->add(function($record) {
                // Heavy processing
                $processed = $this->transform($record);
                $this->validate($processed);
                return $processed;
            }, [$record]);
        }

        // Process 8 records at a time
        return $manager->run(maxConcurrent: 8);
    }

    private function transform($record): array
    {
        // Complex transformation
        sleep(1); // Simulating heavy work
        return ['id' => $record['id'], 'processed' => true];
    }

    private function validate($record): void
    {
        // Validation logic
    }
}

$processor = new BatchProcessor();
$results = $processor->processRecords($largeDataset);
```

## ProcessPool

High-performance process pool for parallel map/filter/reduce operations.

### Basic Usage

```php
use Toporia\Framework\Process\ProcessPool;

// Auto-detect CPU cores
$pool = new ProcessPool();

// Or specify worker count
$pool = new ProcessPool(workerCount: 8);
```

### Map Operation

Apply function to each item in parallel:

```php
$items = range(1, 1000);

$results = $pool->map($items, function($n) {
    return pow($n, 2); // Square each number
});

// Result: [1, 4, 9, 16, ..., 1000000]
```

**Performance:** Automatically chunks items across workers for optimal performance.

### Filter Operation

Filter items in parallel:

```php
$numbers = range(1, 1000);

$primes = $pool->filter($numbers, function($n) {
    if ($n < 2) return false;
    for ($i = 2; $i <= sqrt($n); $i++) {
        if ($n % $i === 0) return false;
    }
    return true;
});

// Result: [2, 3, 5, 7, 11, 13, ...]
```

### Reduce Operation

Reduce array to single value in parallel:

```php
$numbers = range(1, 100);

$sum = $pool->reduce($numbers, function($acc, $n) {
    return $acc + $n;
}, initial: 0);

// Result: 5050
```

**How it works:**
1. Chunks array across workers
2. Each worker reduces its chunk
3. Parent process reduces worker results
4. Returns final value

### Example: Image Processing

```php
use Toporia\Framework\Process\ProcessPool;

class ImageProcessor
{
    public function resizeImages(array $imagePaths): array
    {
        $pool = new ProcessPool(workerCount: 4);

        return $pool->map($imagePaths, function($path) {
            $image = imagecreatefromjpeg($path);
            $resized = imagescale($image, 800, 600);

            $outputPath = str_replace('.jpg', '_thumb.jpg', $path);
            imagejpeg($resized, $outputPath, 85);

            imagedestroy($image);
            imagedestroy($resized);

            return $outputPath;
        });
    }

    public function filterCorruptImages(array $imagePaths): array
    {
        $pool = new ProcessPool();

        return $pool->filter($imagePaths, function($path) {
            $info = @getimagesize($path);
            return $info !== false;
        });
    }
}

$processor = new ImageProcessor();
$thumbnails = $processor->resizeImages($images);
$valid = $processor->filterCorruptImages($images);
```

## ForkProcess

Low-level process management for custom parallel execution.

### Basic Usage

```php
use Toporia\Framework\Process\ForkProcess;

$process = new ForkProcess(function($x, $y) {
    return $x + $y;
}, [10, 20]);

// Start process
$process->start();

echo "Process PID: " . $process->getPid() . "\n";

// Wait for completion
$exitCode = $process->wait();

// Get output
$result = $process->getOutput();
echo "Result: {$result}\n"; // 30
```

### Methods

```php
// Start process (fork and execute)
$started = $process->start(): bool;

// Check if running
$running = $process->isRunning(): bool;

// Wait for completion
$exitCode = $process->wait(): int;

// Get process ID
$pid = $process->getPid(): ?int;

// Get exit code
$exitCode = $process->getExitCode(): ?int;

// Kill process
$killed = $process->kill(int $signal = SIGTERM): bool;

// Get output
$output = $process->getOutput(): mixed;

// Check if PCNTL supported
$supported = ForkProcess::isSupported(): bool;
```

### Example: Custom Parallel Pipeline

```php
use Toporia\Framework\Process\ForkProcess;

class ParallelPipeline
{
    private array $processes = [];

    public function addStage(callable $callback, array $args): void
    {
        $this->processes[] = new ForkProcess($callback, $args);
    }

    public function run(): array
    {
        // Start all processes
        foreach ($this->processes as $process) {
            $process->start();
        }

        // Wait for all
        $results = [];
        foreach ($this->processes as $process) {
            $process->wait();
            $results[] = $process->getOutput();
        }

        return $results;
    }
}

$pipeline = new ParallelPipeline();
$pipeline->addStage(fn() => fetchUserData(), []);
$pipeline->addStage(fn() => fetchOrderData(), []);
$pipeline->addStage(fn() => fetchAnalytics(), []);

[$users, $orders, $analytics] = $pipeline->run();
```

## Performance

### Benchmarks

**Test Environment:**
- 4 CPU cores
- PHP 8.1+
- Linux/WSL2

**Results:**

| Operation | Items | Workers | Time | Throughput |
|-----------|-------|---------|------|------------|
| ProcessManager | 4 tasks | 4 | ~11ms | 360 tasks/sec |
| ProcessPool map | 16 items | 4 | ~12ms | 1,333 items/sec |
| ProcessPool filter | 20 items | 4 | ~10ms | 2,000 items/sec |
| ProcessPool reduce | 100 items | 4 | ~13ms | 7,692 items/sec |

**Performance Characteristics:**
- **Process creation:** O(1) via fork()
- **Concurrency:** O(N) where N = concurrent processes
- **Memory overhead:** Minimal in parent process
- **Scaling:** Linear with CPU cores

### Performance Tips

**✅ DO:**
```php
// Use ProcessPool for array operations
$results = Process::map($items, $callback);

// Batch small tasks together
$chunks = array_chunk($items, 100);
$results = Process::map($chunks, $batchProcessor);

// Let system auto-detect CPU cores
$pool = Process::pool(); // Uses all cores
```

**❌ DON'T:**
```php
// Don't create too many processes
Process::run($items, maxConcurrent: 1000); // Excessive!

// Don't fork for trivial operations
Process::map([1, 2, 3], fn($n) => $n + 1); // Too small!

// Don't share state between processes
$shared = [];
Process::map($items, function($item) use (&$shared) {
    $shared[] = $item; // Won't work - processes are isolated!
});
```

### When to Use

**✅ Good use cases:**
- CPU-intensive operations (image processing, data transformation)
- I/O-bound operations (API requests, file processing)
- Batch processing of large datasets
- Parallel testing

**❌ Not recommended:**
- Trivial operations (< 1ms per item)
- Small datasets (< 10 items)
- Operations requiring shared state
- Database transactions (isolation issues)

## Best Practices

### 1. Error Handling

```php
use Toporia\Framework\Process\ProcessManager;

$manager = new ProcessManager();

$manager->add(function() {
    try {
        return riskyOperation();
    } catch (\Throwable $e) {
        // Log error
        error_log("Process error: " . $e->getMessage());
        return null; // Return safe value
    }
});

$results = $manager->run();
```

### 2. Resource Cleanup

```php
// ProcessManager automatically kills processes on destruction
$manager = new ProcessManager();
$manager->add($task1);
$manager->add($task2);

try {
    $results = $manager->run();
} finally {
    // Optional: manual cleanup
    $manager->killAll(SIGKILL);
}
```

### 3. Signal Handling

```php
// ProcessManager handles SIGTERM and SIGINT automatically
$manager = new ProcessManager();

// Register custom signal handler
pcntl_signal(SIGUSR1, function() use ($manager) {
    echo "Received SIGUSR1, stopping workers...\n";
    $manager->killAll(SIGTERM);
});

pcntl_async_signals(true);
```

### 4. Choosing Worker Count

```php
// CPU-bound tasks: Use number of CPU cores
$cpuCores = Process::getCpuCores();
$pool = Process::pool($cpuCores);

// I/O-bound tasks: Use more workers (2-4x cores)
$pool = Process::pool($cpuCores * 2);

// Memory-constrained: Reduce workers
$pool = Process::pool(max(1, $cpuCores / 2));
```

### 5. Testing

```php
use PHPUnit\Framework\TestCase;
use Toporia\Framework\Support\Accessors\Process;

class ProcessTest extends TestCase
{
    public function testParallelExecution(): void
    {
        if (!Process::isSupported()) {
            $this->markTestSkipped('PCNTL not available');
        }

        $results = Process::map([1, 2, 3], fn($n) => $n * 2);

        $this->assertEquals([2, 4, 6], $results);
    }
}
```

## Troubleshooting

### "Call to undefined function pcntl_fork()"

**Problem:** PCNTL extension not installed.

**Solution:**
```bash
# Debian/Ubuntu
sudo apt-get install php-pcntl

# Alpine Linux
apk add php-pcntl

# Check if installed
php -m | grep pcntl
```

### Processes returning NULL

**Problem:** Output not being collected correctly.

**Causes:**
1. Child process exits before writing to pipe
2. Parent process exits before reading pipe
3. Serialization error

**Solution:**
```php
// Ensure proper error handling
$process = Process::fork(function() {
    try {
        return doWork();
    } catch (\Throwable $e) {
        error_log($e->getMessage());
        return null;
    }
});
```

### "Too many open files"

**Problem:** System limit on file descriptors exceeded.

**Solution:**
```bash
# Check limit
ulimit -n

# Increase limit (temporary)
ulimit -n 4096

# Reduce concurrent processes
Process::run($tasks, maxConcurrent: 4); // Lower value
```

### Zombie Processes

**Problem:** Child processes not being reaped.

**Solution:** ProcessManager automatically handles this via:
- Non-blocking wait with `WNOHANG`
- Automatic cleanup in destructor
- Signal handlers for graceful shutdown

```php
// ProcessManager prevents zombie processes automatically
$manager = new ProcessManager();
$results = $manager->run(); // All processes properly reaped
```

### Performance Issues

**Problem:** Slower than expected execution.

**Solutions:**
```php
// 1. Check if operation is too small
if (count($items) < 100) {
    // Use synchronous execution
    $results = array_map($callback, $items);
}

// 2. Adjust worker count
$cores = Process::getCpuCores();
$pool = Process::pool($cores); // Start with CPU cores

// 3. Batch small operations
$chunks = array_chunk($items, 100);
$results = Process::map($chunks, fn($chunk) =>
    array_map($callback, $chunk)
);
```

### Memory Leaks

**Problem:** Memory usage grows over time.

**Solution:**
```php
// Each process has isolated memory
// Memory is freed when process exits
$manager = new ProcessManager();

foreach ($largeBatches as $batch) {
    $manager->add(function($data) {
        // Process data
        $result = transform($data);

        // Memory is freed when process exits
        return $result;
    }, [$batch]);
}

$results = $manager->run();
// All child process memory is now freed
```

## See Also

- [Process Contracts](../src/Framework/Process/Contracts/)
- [ProcessServiceProvider](../src/Framework/Providers/ProcessServiceProvider.php)
- [Test Examples](../test-multiprocess-fixed.php)
