# Schedule & Queue System

Hướng dẫn setup và sử dụng Schedule (cron jobs) và Queue (background jobs) trong framework.

## Table of Contents

- [Schedule System (Cron Jobs)](#schedule-system-cron-jobs)
- [Queue System (Background Jobs)](#queue-system-background-jobs)
- [Integration: Schedule + Queue](#integration-schedule--queue)
- [Production Setup](#production-setup)

---

## Schedule System (Cron Jobs)

Schedule system cho phép định nghĩa các task chạy định kỳ (giống cron) với fluent API.

### 1. Khai báo Schedule Tasks

Tạo file `schedule.php` ở root project:

```php
<?php

require __DIR__ . '/vendor/autoload.php';

// Bootstrap application
$app = require __DIR__ . '/bootstrap/app.php';

// Get scheduler instance
use Toporia\Framework\Support\Accessors\{Schedule, Queue};

// ============================================================================
// Define Scheduled Tasks
// ============================================================================

// Example 1: Cleanup old files every day at 2 AM
Schedule::call(function() {
    $files = glob(__DIR__ . '/storage/temp/*');
    foreach ($files as $file) {
        if (filemtime($file) < strtotime('-7 days')) {
            unlink($file);
        }
    }
})->dailyAt('02:00')->description('Cleanup old temp files');

// Example 2: Send daily report every weekday at 9 AM
Schedule::call(function() {
    // Generate and send daily report
    $report = generateDailyReport();
    sendEmailReport($report);
})->weekdays()->dailyAt('09:00')->description('Send daily report');

// Example 3: Database backup every Sunday at midnight
Schedule::exec('php backup-database.php')
    ->sundays()
    ->description('Weekly database backup');

// Example 4: Queue newsletter every Monday at 8 AM
Schedule::job(\App\Jobs\SendNewsletterJob::class)
    ->mondays()
    ->dailyAt('08:00')
    ->description('Send weekly newsletter');

// Example 5: Clear cache every hour
Schedule::call(function() {
    Cache::clear();
})->hourly()->description('Clear cache');

// Example 6: Custom cron expression
Schedule::call(function() {
    // Your task
})->cron('*/15 * * * *') // Every 15 minutes
  ->description('Custom task');

// Example 7: Conditional execution
Schedule::call(function() {
    // Process payments
})->everyMinute()
  ->when(function() {
      return date('H') >= 9 && date('H') < 18; // Only business hours
  })
  ->skip(function() {
      return date('l') === 'Sunday'; // Skip Sundays
  })
  ->description('Process payments (business hours only)');

// Example 8: With timezone
Schedule::call(function() {
    // Task in Vietnam timezone
})->dailyAt('10:00')
  ->timezone('Asia/Ho_Chi_Minh')
  ->description('Vietnam timezone task');

// ============================================================================
// Run Due Tasks
// ============================================================================

// This will execute all tasks that are due to run right now
Schedule::runDueTasks();
```

### 2. Cron Expression Syntax

Format: `minute hour day month day-of-week`

| Expression | Meaning |
|------------|---------|
| `* * * * *` | Every minute |
| `0 * * * *` | Every hour |
| `0 0 * * *` | Every day at midnight |
| `0 0 * * 0` | Every Sunday at midnight |
| `*/15 * * * *` | Every 15 minutes |
| `0 9-17 * * 1-5` | Every hour 9am-5pm on weekdays |

### 3. Fluent API Methods

**Frequency:**
```php
->everyMinute()               // * * * * *
->everyMinutes(5)             // */5 * * * *
->hourly()                    // 0 * * * *
->hourlyAt(15)                // 15 * * * *
->daily()                     // 0 0 * * *
->dailyAt('14:30')            // 30 14 * * *
->weekly()                    // 0 0 * * 0
->monthly()                   // 0 0 1 * *
->weekdays()                  // 0 0 * * 1-5
->weekends()                  // 0 0 * * 0,6
->mondays()                   // 0 0 * * 1
->tuesdays()                  // 0 0 * * 2
// ... (tất cả các ngày trong tuần)
```

**Conditional:**
```php
->when(callable $callback)    // Run if callback returns true
->skip(callable $callback)    // Skip if callback returns true
->timezone(string $timezone)  // Set timezone
```

**Metadata:**
```php
->description(string $desc)   // Set task description
->cron(string $expression)    // Custom cron expression
```

### 4. Setup Crontab

Thêm vào crontab để chạy schedule.php mỗi phút:

```bash
# Mở crontab editor
crontab -e

# Thêm dòng này (thay /path/to/project):
* * * * * cd /path/to/project && php schedule.php >> /dev/null 2>&1
```

**Hoặc log output:**
```bash
* * * * * cd /path/to/project && php schedule.php >> storage/logs/schedule.log 2>&1
```

**Verify crontab:**
```bash
crontab -l
```

### 5. Testing Schedule

Test thủ công:
```bash
php schedule.php
```

List all tasks:
```php
$tasks = Schedule::getInstance()->listTasks();
print_r($tasks);
```

---

## Queue System (Background Jobs)

Queue system cho phép push jobs vào queue và xử lý bất đồng bộ.

### 1. Tạo Job Class

```php
<?php

namespace App\Jobs;

use Toporia\Framework\Queue\Job;

class SendEmailJob extends Job
{
    public function __construct(
        private array $user,
        private string $subject,
        private string $message
    ) {
        parent::__construct();
    }

    /**
     * Execute the job
     */
    public function handle(): void
    {
        // Send email logic
        mail(
            $this->user['email'],
            $this->subject,
            $this->message
        );

        echo "Email sent to {$this->user['email']}\n";
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        // Log failure
        error_log("Failed to send email to {$this->user['email']}: {$exception->getMessage()}");

        // Notify admin
        // ...
    }
}
```

**Job Lifecycle Hooks:**
```php
class MyJob extends Job
{
    protected function before(): void
    {
        // Called before handle()
        echo "Job starting...\n";
    }

    public function handle(): void
    {
        // Main job logic
    }

    protected function after(): void
    {
        // Called after successful handle()
        echo "Job completed!\n";
    }

    public function failed(\Throwable $exception): void
    {
        // Called when job fails after max retries
        error_log($exception->getMessage());
    }
}
```

### 2. Push Jobs to Queue

**Trong Controllers:**
```php
use Toporia\Framework\Support\Accessors\Queue;

class UserController extends BaseController
{
    public function register(Request $request)
    {
        $user = User::create($request->all());

        // Push welcome email to queue (immediate)
        Queue::push(new SendEmailJob(
            user: $user->toArray(),
            subject: 'Welcome!',
            message: 'Thank you for registering'
        ));

        // Push verification email with 5-minute delay
        Queue::later(
            new SendVerificationEmailJob($user),
            delay: 300 // seconds
        );

        return $this->response->json($user, 201);
    }
}
```

**Với specific queue:**
```php
// High priority
Queue::push($job, 'high-priority');

// Low priority
Queue::push($job, 'low-priority');

// Emails
Queue::push($emailJob, 'emails');
```

### 3. Worker Script

Tạo file `worker.php` ở root:

```php
<?php

require __DIR__ . '/vendor/autoload.php';

// Bootstrap application
$app = require __DIR__ . '/bootstrap/app.php';

use Toporia\Framework\Queue\Worker;
use Toporia\Framework\Support\Accessors\Queue;

// Configuration
$queueName = $argv[1] ?? 'default';
$maxJobs = (int)($argv[2] ?? 0); // 0 = unlimited
$sleep = (int)($argv[3] ?? 3);   // seconds

// Get queue instance
$queue = Queue::getInstance();

// Create worker
$worker = new Worker($queue, $maxJobs, $sleep);

// Handle graceful shutdown
pcntl_async_signals(true);
pcntl_signal(SIGTERM, function() use ($worker) {
    $worker->stop();
});
pcntl_signal(SIGINT, function() use ($worker) {
    $worker->stop();
});

// Start processing
echo "Starting queue worker...\n";
echo "Queue: {$queueName}\n";
echo "Max jobs: " . ($maxJobs > 0 ? $maxJobs : 'unlimited') . "\n";
echo "Sleep: {$sleep}s\n";
echo str_repeat('-', 50) . "\n";

$worker->work($queueName);
```

### 4. Chạy Worker

**Development (foreground):**
```bash
# Default queue
php worker.php

# Specific queue
php worker.php emails

# With max jobs limit
php worker.php default 100

# With custom sleep time
php worker.php default 0 5
```

**Production (background):**
```bash
# Run in background
nohup php worker.php default >> storage/logs/worker.log 2>&1 &

# Multiple workers for different queues
nohup php worker.php high-priority >> storage/logs/worker-high.log 2>&1 &
nohup php worker.php emails >> storage/logs/worker-emails.log 2>&1 &
nohup php worker.php default >> storage/logs/worker-default.log 2>&1 &
```

**Stop worker:**
```bash
# Find process
ps aux | grep worker.php

# Kill gracefully
kill -SIGTERM <PID>

# Force kill
kill -9 <PID>
```

### 5. Queue Drivers

**MemoryQueue** (testing only):
```php
// config/queue.php
'default' => 'memory',
'connections' => [
    'memory' => ['driver' => 'memory'],
]
```

**DatabaseQueue** (recommended):
```php
'default' => 'database',
'connections' => [
    'database' => [
        'driver' => 'database',
        'table' => 'jobs',
    ],
]
```

**RedisQueue** (high performance):
```php
'default' => 'redis',
'connections' => [
    'redis' => [
        'driver' => 'redis',
        'host' => '127.0.0.1',
        'port' => 6379,
    ],
]
```

---

## Integration: Schedule + Queue

Combine Schedule và Queue để chạy heavy tasks theo schedule:

```php
// schedule.php

// Example 1: Queue batch emails every Monday
Schedule::call(function() {
    $users = User::where('subscribed', true)->get();

    foreach ($users as $user) {
        Queue::push(new SendNewsletterJob($user));
    }

    echo "Queued newsletter for " . count($users) . " users\n";
})->mondays()->dailyAt('08:00');

// Example 2: Generate reports and queue delivery
Schedule::call(function() {
    $report = generateMonthlyReport();
    Queue::push(new SendReportJob($report));
})->monthly();

// Example 3: Cleanup old jobs every day
Schedule::call(function() {
    // Delete old completed jobs from database
    DB::table('jobs')->where('created_at', '<', time() - 86400 * 7)->delete();
    DB::table('failed_jobs')->where('failed_at', '<', time() - 86400 * 30)->delete();
})->daily()->description('Cleanup old queue jobs');

// Example 4: Process pending payments every 5 minutes
Schedule::call(function() {
    $pendingPayments = Payment::where('status', 'pending')->get();

    foreach ($pendingPayments as $payment) {
        Queue::push(new ProcessPaymentJob($payment), 'high-priority');
    }
})->everyMinutes(5);
```

---

## Production Setup

### 1. Supervisor (Recommended)

Supervisor tự động restart worker khi crash:

**Install:**
```bash
sudo apt-get install supervisor
```

**Config:** `/etc/supervisor/conf.d/queue-worker.conf`
```ini
[program:queue-worker-default]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/html/worker.php default
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/html/storage/logs/worker-default.log
stopwaitsecs=3600

[program:queue-worker-high]
process_name=%(program_name)s
command=php /var/www/html/worker.php high-priority
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/html/storage/logs/worker-high.log
```

**Reload & Start:**
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start queue-worker-default:*
sudo supervisorctl start queue-worker-high
```

**Monitor:**
```bash
sudo supervisorctl status
sudo supervisorctl tail -f queue-worker-default:queue-worker-default_00 stdout
```

### 2. Systemd Service

**Create service:** `/etc/systemd/system/queue-worker@.service`
```ini
[Unit]
Description=Queue Worker %i
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/html
ExecStart=/usr/bin/php /var/www/html/worker.php %i
Restart=always
RestartSec=5
StandardOutput=append:/var/www/html/storage/logs/worker-%i.log
StandardError=append:/var/www/html/storage/logs/worker-%i.log

[Install]
WantedBy=multi-user.target
```

**Enable & Start:**
```bash
sudo systemctl enable queue-worker@default
sudo systemctl enable queue-worker@high-priority
sudo systemctl start queue-worker@default
sudo systemctl start queue-worker@high-priority
```

**Monitor:**
```bash
sudo systemctl status queue-worker@default
sudo journalctl -u queue-worker@default -f
```

### 3. Monitoring & Alerts

**Check worker health:**
```php
// health-check.php
require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';

use Toporia\Framework\Support\Accessors\{Queue, Cache};

// Check if workers are running
$lastJobTime = Cache::get('queue:last_job_processed');

if ($lastJobTime && (time() - $lastJobTime) > 300) {
    // No job processed in 5 minutes - workers might be down
    sendAlert('Queue workers may be down!');
}

// Check queue size
$queueSize = DB::table('jobs')->count();
if ($queueSize > 1000) {
    sendAlert("Queue backlog: {$queueSize} jobs pending");
}
```

**Cron health check:**
```bash
*/5 * * * * php /var/www/html/health-check.php
```

### 4. Best Practices

**Schedule:**
- ✓ Always set descriptions for tasks
- ✓ Use timezone for international apps
- ✓ Log task execution for debugging
- ✓ Use `when()` for conditional execution
- ✓ Keep tasks idempotent (safe to run multiple times)

**Queue:**
- ✓ Use DatabaseQueue or RedisQueue in production
- ✓ Set appropriate max attempts (default: 3)
- ✓ Implement `failed()` hook for error handling
- ✓ Use multiple workers for high throughput
- ✓ Separate queues by priority (high, default, low)
- ✓ Monitor queue size and processing time
- ✓ Clean up old completed/failed jobs regularly

**Worker:**
- ✓ Use Supervisor or systemd for auto-restart
- ✓ Run multiple workers for parallel processing
- ✓ Set reasonable sleep time (3-5 seconds)
- ✓ Log all output for debugging
- ✓ Handle graceful shutdown (SIGTERM)

---

## Examples

See working examples:
- `demo/schedule_demo.php` - Schedule examples
- `demo/queue_demo.php` - Queue examples
- `demo/integration_demo.php` - Schedule + Queue integration
