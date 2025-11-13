# Kafka Consumer - Hướng dẫn xử lý Messages

Hướng dẫn chi tiết về cách consumer xử lý messages từ Kafka trong Toporia Framework.

---

## 📋 Tổng quan

Consumer là process chạy liên tục, nhận messages từ Kafka topics và xử lý chúng. Trong Toporia, consumer được implement qua:

1. **RealtimeKafkaConsumerCommand** - CLI command để chạy consumer
2. **KafkaBroker::consume()** - Logic consume messages
3. **Callback system** - Xử lý messages khi nhận được

---

## 🔄 Flow xử lý Messages

### Flow hoàn chỉnh

```
┌─────────────────┐
│ Kafka Topic     │
│ (realtime_user_1)│
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ Consumer Poll   │  ← Non-blocking poll với timeout
│ (consume loop)   │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ Batch Messages  │  ← Accumulate messages
│ (batch size)    │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ Process Batch   │  ← Xử lý batch
│ (processBatch)  │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ Process Message │  ← Xử lý từng message
│ (processMessage)│
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ Callback        │  ← Invoke callback
│ (handleMessage) │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ RealtimeManager │  ← Broadcast đến local connections
│ (broadcast)     │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ WebSocket       │  ← Gửi đến clients
│ Connections     │
└─────────────────┘
```

---

## 🚀 Cách sử dụng Consumer

### 1. Chạy Consumer Command (Cơ bản)

```bash
# Subscribe một channel
php console realtime:kafka:consume --channels=user.1

# Subscribe nhiều channels
php console realtime:kafka:consume --channels=user.1,user.2,public.news

# Với options
php console realtime:kafka:consume \
  --channels=user.1,user.2 \
  --batch-size=200 \
  --timeout=500 \
  --max-messages=1000
```

### 2. Consumer Options

| Option | Mô tả | Default |
|--------|-------|---------|
| `--broker=kafka` | Broker name từ config | `kafka` |
| `--channels=ch1,ch2` | Channels để subscribe (bắt buộc) | - |
| `--batch-size=N` | Số messages mỗi batch | `100` |
| `--timeout=N` | Poll timeout (ms) | `1000` |
| `--max-messages=N` | Max messages trước khi exit (0 = unlimited) | `0` |
| `--stop-when-empty` | Stop khi không có messages (testing) | `false` |

---

## 💻 Code Implementation

### 1. Consumer Command (`RealtimeKafkaConsumerCommand`)

**File:** `src/Framework/Console/Commands/RealtimeKafkaConsumerCommand.php`

**Chức năng:**
- Parse command options
- Subscribe to channels
- Setup signal handlers (graceful shutdown)
- Start consume loop
- Handle messages và broadcast

**Key methods:**

```php
// 1. Subscribe to channels
private function subscribeToChannels(BrokerInterface $broker, array $channels): void
{
    foreach ($channels as $channel) {
        $broker->subscribe($channel, function ($message) use ($channel) {
            $this->handleMessage($channel, $message);
        });
    }
}

// 2. Handle incoming message
private function handleMessage(string $channel, $message): void
{
    // Broadcast to local RealtimeManager
    if ($message->getEvent() && $message->getData() !== null) {
        $this->realtime->broadcast(
            $channel,
            $message->getEvent(),
            $message->getData()
        );
    }

    $this->processed++;
}

// 3. Start consume loop
private function consumeLoop(
    KafkaBroker $broker,
    int $timeout,
    int $batchSize,
    int $maxMessages
): void {
    $broker->consume($timeout, $batchSize);
}
```

### 2. KafkaBroker::consume()

**File:** `src/Framework/Realtime/Brokers/KafkaBroker.php`

**Chức năng:**
- Poll messages từ Kafka
- Batch processing
- Invoke callbacks

**Implementation (rdkafka):**

```php
public function consume(int $timeoutMs = 1000, int $batchSize = 100): void
{
    $this->consuming = true;
    $topics = array_keys($this->subscriptions);

    // Subscribe to topics
    $consumer->subscribe($topics);

    $batch = [];

    while ($this->consuming) {
        // Poll for messages (non-blocking with timeout)
        $message = $consumer->consume($timeoutMs);

        if ($message === null) {
            continue; // Timeout, no message
        }

        // Handle errors
        switch ($message->err) {
            case RD_KAFKA_RESP_ERR_NO_ERROR:
                // Valid message - add to batch
                $batch[] = $message;

                // Process batch when full
                if (count($batch) >= $batchSize) {
                    $this->processBatch($batch);
                    $batch = [];
                }
                break;

            case RD_KAFKA_RESP_ERR__PARTITION_EOF:
                // End of partition (normal)
                break;

            case RD_KAFKA_RESP_ERR__TIMED_OUT:
                // Timeout (normal, continue)
                break;

            default:
                // Error - log it
                error_log("Kafka consumer error: {$message->errstr()}");
                break;
        }

        // Periodic flush (every 10 messages or 100ms)
        if (count($batch) > 0 && shouldFlush) {
            $this->processBatch($batch);
            $batch = [];
        }
    }

    // Process remaining batch
    if (!empty($batch)) {
        $this->processBatch($batch);
    }
}
```

### 3. Process Batch

```php
private function processBatch(array $batch): void
{
    foreach ($batch as $message) {
        $this->processMessage($message->topic_name, $message->payload);
    }
}

private function processMessage(string $topicName, string $payload): void
{
    $callback = $this->subscriptions[$topicName] ?? null;

    if (!$callback) {
        return; // No subscription
    }

    try {
        // Decode message
        $message = Message::fromJson($payload);

        // Invoke callback (registered in subscribeToChannels)
        $callback($message);
    } catch (\Throwable $e) {
        error_log("Error processing message: {$e->getMessage()}");
    }
}
```

---

## 🎯 Tạo Custom Consumer

### Ví dụ 1: Custom Consumer với Business Logic

```php
<?php

namespace App\Console\Commands;

use Toporia\Framework\Console\Command;
use Toporia\Framework\Realtime\Contracts\RealtimeManagerInterface;

class CustomKafkaConsumer extends Command
{
    protected string $signature = 'kafka:custom:consume {--channels=*}';
    protected string $description = 'Custom Kafka consumer with business logic';

    public function __construct(
        private readonly RealtimeManagerInterface $realtime
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $broker = $this->realtime->broker('kafka');

        if (!$broker instanceof \Toporia\Framework\Realtime\Brokers\KafkaBroker) {
            $this->error('Kafka broker not found');
            return 1;
        }

        $channels = $this->option('channels', ['user.1']);

        // Subscribe với custom callback
        foreach ($channels as $channel) {
            $broker->subscribe($channel, function ($message) use ($channel) {
                $this->processMessage($channel, $message);
            });
        }

        // Start consuming
        $broker->consume(1000, 100);

        return 0;
    }

    private function processMessage(string $channel, $message): void
    {
        // Custom business logic
        $event = $message->getEvent();
        $data = $message->getData();

        switch ($event) {
            case 'order.created':
                $this->handleOrderCreated($data);
                break;

            case 'user.updated':
                $this->handleUserUpdated($data);
                break;

            default:
                // Broadcast to local RealtimeManager
                $this->realtime->broadcast($channel, $event, $data);
        }
    }

    private function handleOrderCreated(array $data): void
    {
        // Custom logic: Send email, update database, etc.
        $orderId = $data['order_id'];
        $this->info("Processing order: {$orderId}");

        // Then broadcast
        $this->realtime->broadcast('admin.orders', 'order.created', $data);
    }

    private function handleUserUpdated(array $data): void
    {
        // Custom logic
        $userId = $data['user_id'];
        $this->info("User updated: {$userId}");

        // Then broadcast
        $this->realtime->broadcast("user.{$userId}", 'profile.updated', $data);
    }
}
```

### Ví dụ 2: Consumer với Database Processing

```php
<?php

namespace App\Console\Commands;

use App\Application\Services\OrderService;
use Toporia\Framework\Console\Command;
use Toporia\Framework\Realtime\Contracts\RealtimeManagerInterface;

class OrderKafkaConsumer extends Command
{
    protected string $signature = 'kafka:orders:consume';
    protected string $description = 'Consume order events from Kafka';

    public function __construct(
        private readonly RealtimeManagerInterface $realtime,
        private readonly OrderService $orderService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $broker = $this->realtime->broker('kafka');

        // Subscribe to order channels
        $broker->subscribe('orders', function ($message) {
            $this->processOrder($message);
        });

        $broker->subscribe('orders.payments', function ($message) {
            $this->processPayment($message);
        });

        $broker->consume(1000, 50); // Smaller batch for faster processing

        return 0;
    }

    private function processOrder($message): void
    {
        $data = $message->getData();
        $orderId = $data['order_id'];

        try {
            // Update database
            $this->orderService->updateOrderStatus($orderId, $data['status']);

            // Broadcast to user
            $this->realtime->broadcast(
                "user.{$data['user_id']}",
                'order.updated',
                $data
            );

            $this->info("Processed order: {$orderId}");
        } catch (\Throwable $e) {
            $this->error("Error processing order {$orderId}: {$e->getMessage()}");
        }
    }

    private function processPayment($message): void
    {
        $data = $message->getData();

        // Process payment logic
        // ...

        // Broadcast
        $this->realtime->broadcast(
            "user.{$data['user_id']}",
            'payment.completed',
            $data
        );
    }
}
```

### Ví dụ 3: Consumer với Retry Logic

```php
<?php

namespace App\Console\Commands;

use Toporia\Framework\Console\Command;
use Toporia\Framework\Realtime\Contracts\RealtimeManagerInterface;

class ReliableKafkaConsumer extends Command
{
    protected string $signature = 'kafka:reliable:consume {--channels=*}';

    private int $maxRetries = 3;
    private array $failedMessages = [];

    public function __construct(
        private readonly RealtimeManagerInterface $realtime
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $broker = $this->realtime->broker('kafka');
        $channels = $this->option('channels', ['test']);

        foreach ($channels as $channel) {
            $broker->subscribe($channel, function ($message) use ($channel) {
                $this->processWithRetry($channel, $message);
            });
        }

        $broker->consume(1000, 100);

        // Retry failed messages
        $this->retryFailedMessages($broker);

        return 0;
    }

    private function processWithRetry(string $channel, $message): void
    {
        $retries = 0;

        while ($retries < $this->maxRetries) {
            try {
                // Process message
                $this->realtime->broadcast(
                    $channel,
                    $message->getEvent(),
                    $message->getData()
                );

                // Success - remove from failed if exists
                unset($this->failedMessages[$message->getId()]);
                return;

            } catch (\Throwable $e) {
                $retries++;

                if ($retries >= $this->maxRetries) {
                    // Max retries reached - store for later
                    $this->failedMessages[$message->getId()] = [
                        'channel' => $channel,
                        'message' => $message,
                        'error' => $e->getMessage()
                    ];

                    $this->error("Failed after {$this->maxRetries} retries: {$e->getMessage()}");
                } else {
                    // Wait before retry
                    usleep(100000 * $retries); // Exponential backoff
                }
            }
        }
    }

    private function retryFailedMessages($broker): void
    {
        if (empty($this->failedMessages)) {
            return;
        }

        $this->warn("Retrying " . count($this->failedMessages) . " failed messages...");

        foreach ($this->failedMessages as $id => $failed) {
            $this->processWithRetry($failed['channel'], $failed['message']);
        }
    }
}
```

---

## 🔧 Advanced: Direct Consumer Usage

Nếu không muốn dùng command, có thể dùng trực tiếp:

```php
<?php

use Toporia\Framework\Realtime\Contracts\RealtimeManagerInterface;

class MyService
{
    public function __construct(
        private readonly RealtimeManagerInterface $realtime
    ) {}

    public function startConsumer(): void
    {
        $broker = $this->realtime->broker('kafka');

        // Subscribe
        $broker->subscribe('user.1', function ($message) {
            // Process message
            $this->handleMessage($message);
        });

        // Start consuming (blocking)
        $broker->consume(1000, 100);
    }

    private function handleMessage($message): void
    {
        // Your logic here
        $data = $message->getData();
        // ...
    }
}
```

---

## 📊 Performance Tuning

### Batch Size

```php
// High throughput (more messages per batch)
$broker->consume(1000, 500); // 500 messages per batch

// Low latency (smaller batches)
$broker->consume(1000, 10); // 10 messages per batch
```

### Timeout

```php
// High-frequency messages (lower timeout)
$broker->consume(100, 100); // 100ms timeout

// Low-frequency messages (higher timeout)
$broker->consume(5000, 100); // 5s timeout
```

### Consumer Config

```php
// config/realtime.php
'consumer_config' => [
    'fetch.min.bytes' => '1024',        // Min bytes per fetch
    'fetch.max.wait.ms' => '500',       // Max wait time
    'max.partition.fetch.bytes' => '1048576', // 1MB per partition
],
```

---

## 🛡️ Error Handling

### Automatic Error Handling

Consumer tự động xử lý:
- ✅ Connection errors
- ✅ Timeout errors
- ✅ Partition EOF
- ✅ Invalid messages

### Custom Error Handling

```php
$broker->subscribe('channel', function ($message) {
    try {
        // Process message
    } catch (\Throwable $e) {
        // Custom error handling
        logger()->error('Message processing failed', [
            'channel' => 'channel',
            'error' => $e->getMessage(),
            'message' => $message->toArray()
        ]);

        // Optionally: Send to dead letter queue
        // $this->sendToDLQ($message);
    }
});
```

---

## 🎯 Best Practices

### 1. **Idempotent Processing**

```php
// Check if already processed
if ($this->isProcessed($message->getId())) {
    return; // Skip
}

// Process
$this->process($message);

// Mark as processed
$this->markAsProcessed($message->getId());
```

### 2. **Batch Processing**

```php
// Process batch together (more efficient)
$broker->consume(1000, 100); // 100 messages per batch
```

### 3. **Graceful Shutdown**

```bash
# Use SIGTERM (not SIGKILL)
kill -TERM <pid>

# Or Ctrl+C in terminal
```

### 4. **Monitoring**

```php
// Track metrics
$processed = 0;
$errors = 0;

$broker->subscribe('channel', function ($message) use (&$processed, &$errors) {
    try {
        $this->process($message);
        $processed++;
    } catch (\Throwable $e) {
        $errors++;
    }

    // Log every 1000 messages
    if ($processed % 1000 === 0) {
        logger()->info('Consumer stats', [
            'processed' => $processed,
            'errors' => $errors
        ]);
    }
});
```

---

## ✅ Tóm tắt

**Consumer Flow:**
1. **Subscribe** → Đăng ký channels
2. **Poll** → Nhận messages từ Kafka
3. **Batch** → Accumulate messages
4. **Process** → Xử lý batch
5. **Callback** → Invoke callback cho mỗi message
6. **Broadcast** → Gửi đến local RealtimeManager
7. **WebSocket** → Gửi đến clients

**Key Points:**
- ✅ Non-blocking poll với timeout
- ✅ Batch processing cho performance
- ✅ Automatic error handling
- ✅ Graceful shutdown support
- ✅ Configurable batch size và timeout

Consumer đã được tối ưu và sẵn sàng cho production! 🚀

