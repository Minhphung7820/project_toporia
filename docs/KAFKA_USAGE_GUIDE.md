# Hướng dẫn sử dụng Kafka Realtime trong Toporia

Hướng dẫn chi tiết cách sử dụng Kafka broker cho realtime communication sau khi đã tích hợp.

---

## 📋 Mục lục

1. [Setup & Configuration](#setup--configuration)
2. [Publish Messages (Gửi tin nhắn)](#publish-messages)
3. [Consume Messages (Nhận tin nhắn)](#consume-messages)
4. [Ví dụ thực tế](#ví-dụ-thực-tế)
5. [Multi-Server Setup](#multi-server-setup)
6. [Best Practices](#best-practices)

---

## 🚀 Setup & Configuration

### Bước 1: Cài đặt Kafka Client Library

```bash
# Option 1: nmred/kafka-php (Dễ cài, pure PHP)
composer require nmred/kafka-php

# Option 2: enqueue/rdkafka (Hiệu năng cao, cần C extension)
pecl install rdkafka
composer require enqueue/rdkafka
```

### Bước 2: Cấu hình `.env`

```env
# Kafka Configuration
KAFKA_BROKERS=localhost:9092
KAFKA_TOPIC_PREFIX=realtime
KAFKA_CONSUMER_GROUP=realtime-servers

# Enable Kafka làm default broker
REALTIME_BROKER=kafka
```

### Bước 3: Start Kafka Server

```bash
# Docker (khuyến nghị)
docker run -d \
  --name kafka \
  -p 9092:9092 \
  apache/kafka:latest

# Hoặc dùng Kafka service của bạn
```

### Bước 4: Verify Configuration

```bash
# Kiểm tra config
php -r "require 'vendor/autoload.php'; \$app = require 'bootstrap/app.php'; var_dump(config('realtime.brokers.kafka'));"
```

---

## 📤 Publish Messages (Gửi tin nhắn)

### Cách 1: Sử dụng RealtimeManager (Khuyến nghị)

```php
<?php

use Toporia\Framework\Realtime\Contracts\RealtimeManagerInterface;

class NotificationController
{
    public function __construct(
        private readonly RealtimeManagerInterface $realtime
    ) {}

    public function sendNotification($userId, $message)
    {
        // Broadcast message - tự động publish lên Kafka
        $this->realtime->broadcast(
            channel: "user.{$userId}",
            event: 'notification',
            data: [
                'title' => 'New Message',
                'body' => $message,
                'timestamp' => time()
            ]
        );
    }
}
```

### Cách 2: Sử dụng Broker trực tiếp

```php
<?php

use Toporia\Framework\Realtime\Contracts\RealtimeManagerInterface;
use Toporia\Framework\Realtime\Message;

class MessageService
{
    public function __construct(
        private readonly RealtimeManagerInterface $realtime
    ) {}

    public function publishToChannel(string $channel, string $event, mixed $data): void
    {
        $broker = $this->realtime->broker('kafka');

        if ($broker) {
            $message = Message::event($channel, $event, $data);
            $broker->publish($channel, $message);
        }
    }
}
```

### Cách 3: Sử dụng Helper Functions

```php
<?php

// Option A: Dùng realtime() helper
realtime()->broadcast('public.news', 'announcement', [
    'title' => 'System Maintenance',
    'message' => 'Scheduled maintenance tonight at 2 AM'
]);

// Option B: Dùng broadcast() helper (ngắn gọn hơn)
broadcast('public.news', 'announcement', [
    'title' => 'System Maintenance',
    'message' => 'Scheduled maintenance tonight at 2 AM'
]);
```

---

## 📥 Consume Messages (Nhận tin nhắn)

### Chạy Consumer Command

```bash
# Basic usage
php console realtime:kafka:consume --channels=test

# Subscribe nhiều channels
php console realtime:kafka:consume --channels=user.1,user.2,public.news

# Với options
php console realtime:kafka:consume \
  --channels=user.1,public.news \
  --batch-size=200 \
  --timeout=500

# Process limited messages (testing)
php console realtime:kafka:consume \
  --channels=user.1 \
  --max-messages=1000
```

### Consumer Options

| Option | Mô tả | Default |
|--------|-------|---------|
| `--broker=kafka` | Broker name từ config | `kafka` |
| `--channels=ch1,ch2` | Channels để subscribe (bắt buộc) | - |
| `--batch-size=N` | Số messages mỗi batch | `100` |
| `--timeout=N` | Poll timeout (ms) | `1000` |
| `--max-messages=N` | Max messages trước khi exit (0 = unlimited) | `0` |
| `--stop-when-empty` | Stop khi không có messages (testing) | `false` |

### Consumer hoạt động như thế nào?

1. **Subscribe channels**: Consumer đăng ký các channels cần listen
2. **Consume messages**: Nhận messages từ Kafka topics
3. **Broadcast locally**: Tự động broadcast messages đến local RealtimeManager
4. **WebSocket delivery**: RealtimeManager gửi đến WebSocket connections

**Flow:**
```
Kafka Topic → Consumer → RealtimeManager → WebSocket → Client
```

---

## 💡 Ví dụ thực tế

### Ví dụ 1: User Notifications

**Publish (trong Controller/Service):**

```php
<?php

namespace App\Presentation\Http\Controllers;

use Toporia\Framework\Realtime\Contracts\RealtimeManagerInterface;

class NotificationController
{
    public function __construct(
        private readonly RealtimeManagerInterface $realtime
    ) {}

    public function notifyUser(int $userId, array $data)
    {
        // Publish lên Kafka
        $this->realtime->broadcast(
            channel: "user.{$userId}",
            event: 'notification',
            data: $data
        );
    }
}
```

**Consume (chạy command):**

```bash
# Server 1
php console realtime:kafka:consume --channels=user.1,user.2,user.3

# Server 2 (cùng consumer group = load balancing)
php console realtime:kafka:consume --channels=user.1,user.2,user.3
```

**Kết quả:**
- Message được publish lên Kafka topic `realtime_user_1`
- Consumer nhận message và broadcast đến local WebSocket connections
- User nhận notification realtime trong browser

---

### Ví dụ 2: Public Channel (News, Announcements)

**Publish:**

```php
<?php

// Trong AdminController
public function publishAnnouncement(string $title, string $message)
{
    realtime()->broadcast('public.announcements', 'announcement', [
        'title' => $title,
        'message' => $message,
        'published_at' => now()->toIso8601String()
    ]);
}
```

**Consume:**

```bash
php console realtime:kafka:consume --channels=public.announcements
```

---

### Ví dụ 3: Presence Channel (Chat Room)

**Publish:**

```php
<?php

// Khi user join chat room
public function userJoinedChat(int $roomId, int $userId, string $username)
{
    realtime()->broadcast("presence-chat.{$roomId}", 'user.joined', [
        'user_id' => $userId,
        'username' => $username,
        'joined_at' => time()
    ]);
}
```

**Consume:**

```bash
php console realtime:kafka:consume --channels=presence-chat.1,presence-chat.2
```

---

### Ví dụ 4: Order Updates (E-commerce)

**Publish:**

```php
<?php

// Trong OrderService
public function updateOrderStatus(int $orderId, string $status)
{
    $order = Order::find($orderId);

    // Notify customer
    realtime()->broadcast("user.{$order->user_id}", 'order.updated', [
        'order_id' => $orderId,
        'status' => $status,
        'tracking' => $order->tracking_number
    ]);

    // Notify admin
    realtime()->broadcast('admin.orders', 'order.status_changed', [
        'order_id' => $orderId,
        'status' => $status
    ]);
}
```

**Consume:**

```bash
# Customer notifications
php console realtime:kafka:consume --channels=user.1,user.2,user.3

# Admin notifications
php console realtime:kafka:consume --channels=admin.orders
```

---

## 🌐 Multi-Server Setup

### Kiến trúc

```
┌─────────────┐
│  Server A   │  publish() → Kafka Topic
│  (Web App)  │
└─────────────┘
       │
       ▼
┌──────────────┐
│ Kafka Broker │
└──────────────┘
       │
   ┌───┴───┐
   │       │
   ▼       ▼
┌─────┐ ┌─────┐
│ Svr B│ │ Svr C│
│Consumer│ │Consumer│
└─────┘ └─────┘
   │       │
   └───┬───┘
       ▼
┌──────────────┐
│ WebSocket    │
│ Connections  │
└──────────────┘
```

### Setup

**Server A (Publisher):**
```php
// Chỉ cần publish, không cần consumer
realtime()->broadcast('user.1', 'notification', $data);
```

**Server B & C (Consumers):**
```bash
# Server B
php console realtime:kafka:consume --channels=user.1,user.2

# Server C (cùng consumer group = load balancing)
php console realtime:kafka:consume --channels=user.1,user.2
```

**Lợi ích:**
- ✅ Horizontal scaling
- ✅ Load balancing tự động
- ✅ High availability
- ✅ Message persistence

---

## 🎯 Best Practices

### 1. Channel Naming Convention

```php
// ✅ Good: Rõ ràng, có cấu trúc
"user.{$userId}"              // User-specific
"public.news"                 // Public channel
"presence-chat.{$roomId}"     // Presence channel
"admin.orders"                // Admin channel

// ❌ Bad: Không rõ ràng
"channel1"
"test"
"abc123"
```

### 2. Consumer Management

**Development:**
```bash
# Chạy trực tiếp
php console realtime:kafka:consume --channels=test
```

**Production:**
```bash
# Dùng process manager (PM2, Supervisor, systemd)
pm2 start "php console realtime:kafka:consume --channels=user.1,user.2" \
  --name kafka-consumer \
  --instances 2

# Hoặc Supervisor
[program:kafka-consumer]
command=php /path/to/console realtime:kafka:consume --channels=user.1,user.2
autostart=true
autorestart=true
```

### 3. Error Handling

```php
<?php

try {
    realtime()->broadcast('user.1', 'notification', $data);
} catch (\Throwable $e) {
    // Log error
    logger()->error('Failed to broadcast message', [
        'channel' => 'user.1',
        'error' => $e->getMessage()
    ]);

    // Fallback: Store in database for retry
    // hoặc dùng queue system
}
```

### 4. Performance Tuning

**High-throughput scenarios:**

```bash
# Tăng batch size
php console realtime:kafka:consume \
  --channels=user.1 \
  --batch-size=500

# Giảm timeout (nếu messages nhiều)
php console realtime:kafka:consume \
  --channels=user.1 \
  --timeout=100
```

**Config optimization (`config/realtime.php`):**

```php
'kafka' => [
    'producer_config' => [
        'compression.type' => 'snappy',  // Compress messages
        'batch.size' => '16384',         // Batch size
        'linger.ms' => '10',             // Wait for batch
    ],
    'consumer_config' => [
        'fetch.min.bytes' => '1024',     // Min bytes per fetch
        'fetch.max.wait.ms' => '500',    // Max wait time
    ],
],
```

### 5. Monitoring

```bash
# Check consumer status
kafka-consumer-groups.sh \
  --bootstrap-server localhost:9092 \
  --group realtime-servers \
  --describe

# View topic messages (testing)
kafka-console-consumer.sh \
  --bootstrap-server localhost:9092 \
  --topic realtime_user_1 \
  --from-beginning
```

### 6. Graceful Shutdown

```bash
# Sử dụng SIGTERM (không dùng SIGKILL)
kill -TERM <pid>

# Hoặc Ctrl+C trong terminal
```

---

## 📝 Code Examples

### Example 1: Complete Notification System

**Service:**

```php
<?php

namespace App\Application\Services;

use Toporia\Framework\Realtime\Contracts\RealtimeManagerInterface;

class NotificationService
{
    public function __construct(
        private readonly RealtimeManagerInterface $realtime
    ) {}

    public function sendNotification(int $userId, string $title, string $body): void
    {
        $this->realtime->broadcast(
            channel: "user.{$userId}",
            event: 'notification',
            data: [
                'title' => $title,
                'body' => $body,
                'timestamp' => time(),
                'read' => false
            ]
        );
    }

    public function sendBulkNotifications(array $userIds, string $title, string $body): void
    {
        foreach ($userIds as $userId) {
            $this->sendNotification($userId, $title, $body);
        }
    }
}
```

**Controller:**

```php
<?php

namespace App\Presentation\Http\Controllers;

use App\Application\Services\NotificationService;

class NotificationController
{
    public function __construct(
        private readonly NotificationService $notificationService
    ) {}

    public function send(Request $request): JsonResponse
    {
        $this->notificationService->sendNotification(
            userId: $request->input('user_id'),
            title: $request->input('title'),
            body: $request->input('body')
        );

        return response()->json(['success' => true]);
    }
}
```

**Consumer (chạy command):**

```bash
php console realtime:kafka:consume --channels=user.1,user.2,user.3
```

---

### Example 2: Real-time Chat

**Publish message:**

```php
<?php

// Khi user gửi message
public function sendMessage(int $roomId, int $userId, string $message)
{
    realtime()->broadcast("chat.{$roomId}", 'message.new', [
        'user_id' => $userId,
        'message' => $message,
        'timestamp' => time()
    ]);
}
```

**Consumer:**

```bash
php console realtime:kafka:consume --channels=chat.1,chat.2,chat.3
```

---

### Example 3: Live Dashboard Updates

**Publish metrics:**

```php
<?php

// Trong background job
public function updateDashboardMetrics()
{
    $metrics = [
        'users_online' => 1250,
        'orders_today' => 342,
        'revenue' => 12500.50
    ];

    realtime()->broadcast('dashboard.metrics', 'metrics.updated', $metrics);
}
```

**Consumer:**

```bash
php console realtime:kafka:consume --channels=dashboard.metrics
```

---

## 🔍 Troubleshooting

### Lỗi: "Not has broker can connection"

**Nguyên nhân:** Kafka server chưa chạy hoặc không thể kết nối.

**Giải pháp:**
```bash
# Kiểm tra Kafka đang chạy
docker ps | grep kafka

# Hoặc
netstat -an | grep 9092

# Start Kafka nếu chưa chạy
docker start kafka
```

### Lỗi: "Command not found: realtime:kafka:consume"

**Nguyên nhân:** Command chưa được register.

**Giải pháp:**
```bash
# Clear cache và verify
php console list | grep kafka
```

### Messages không được consume

**Kiểm tra:**
1. Consumer có đang chạy không?
2. Channels có đúng không?
3. Topics có tồn tại trong Kafka không?

```bash
# List topics
kafka-topics.sh --list --bootstrap-server localhost:9092

# Check consumer group
kafka-consumer-groups.sh \
  --bootstrap-server localhost:9092 \
  --group realtime-servers \
  --describe
```

---

## 📊 Performance Tips

1. **Batch Size**: Tăng `--batch-size` cho high-throughput (200-500)
2. **Timeout**: Giảm `--timeout` nếu messages nhiều (100-500ms)
3. **Multiple Consumers**: Chạy nhiều consumer instances cho load balancing
4. **Compression**: Enable compression trong producer config
5. **Connection Pooling**: Reuse connections khi có thể

---

## ✅ Checklist

- [ ] Kafka server đang chạy
- [ ] Kafka client library đã cài (`nmred/kafka-php` hoặc `enqueue/rdkafka`)
- [ ] `.env` đã config `KAFKA_BROKERS`
- [ ] `REALTIME_BROKER=kafka` trong `.env`
- [ ] Consumer command đang chạy
- [ ] Channels được subscribe đúng
- [ ] Topics được tạo trong Kafka (auto-created khi publish)

---

## 🎉 Kết luận

Sau khi setup xong:

1. **Publish messages**: Dùng `realtime()->broadcast()` - tự động publish lên Kafka
2. **Consume messages**: Chạy `php console realtime:kafka:consume --channels=...`
3. **Multi-server**: Chạy consumer trên mỗi server, cùng consumer group = load balancing

**Flow hoàn chỉnh:**
```
Controller → RealtimeManager → Kafka → Consumer → RealtimeManager → WebSocket → Client
```

Tất cả đã sẵn sàng! 🚀

