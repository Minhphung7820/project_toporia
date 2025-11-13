# Kafka Realtime - Quick Start Guide

Hướng dẫn nhanh cách sử dụng Kafka realtime trong Toporia Framework.

---

## ⚡ Quick Start (5 phút)

### 1. Cài đặt

```bash
# Cài Kafka client (chọn một)
composer require nmred/kafka-php
# HOẶC
composer require enqueue/rdkafka
```

### 2. Cấu hình `.env`

```env
KAFKA_BROKERS=localhost:9092
REALTIME_BROKER=kafka
```

### 3. Start Kafka Server

```bash
docker run -d --name kafka -p 9092:9092 apache/kafka:latest
```

### 4. Publish Message (Gửi)

```php
<?php

// Trong Controller hoặc Service
broadcast('user.1', 'notification', [
    'title' => 'New Message',
    'body' => 'You have a new message'
]);
```

### 5. Consume Messages (Nhận)

```bash
php console realtime:kafka:consume --channels=user.1
```

**Xong!** Messages sẽ được gửi qua Kafka và nhận realtime. 🎉

---

## 📝 Các cách sử dụng

### Cách 1: Helper Function (Đơn giản nhất)

```php
<?php

// Broadcast message
broadcast('user.1', 'notification', ['title' => 'Hello']);

// Hoặc dùng realtime() helper
realtime()->broadcast('public.news', 'announcement', ['message' => 'News']);
```

### Cách 2: Dependency Injection (Khuyến nghị cho Services)

```php
<?php

namespace App\Application\Services;

use Toporia\Framework\Realtime\Contracts\RealtimeManagerInterface;

class NotificationService
{
    public function __construct(
        private readonly RealtimeManagerInterface $realtime
    ) {}

    public function notify(int $userId, string $message): void
    {
        $this->realtime->broadcast(
            channel: "user.{$userId}",
            event: 'notification',
            data: ['message' => $message]
        );
    }
}
```

### Cách 3: Trong Controller

```php
<?php

namespace App\Presentation\Http\Controllers;

use Toporia\Framework\Realtime\Contracts\RealtimeManagerInterface;

class MessageController
{
    public function __construct(
        private readonly RealtimeManagerInterface $realtime
    ) {}

    public function send(Request $request): JsonResponse
    {
        $this->realtime->broadcast(
            channel: 'public.chat',
            event: 'message.new',
            data: [
                'user' => auth()->user()->name,
                'message' => $request->input('message')
            ]
        );

        return response()->json(['success' => true]);
    }
}
```

---

## 🔄 Flow hoàn chỉnh

```
1. Controller/Service
   ↓
2. broadcast() hoặc realtime()->broadcast()
   ↓
3. RealtimeManager → KafkaBroker
   ↓
4. Kafka Topic (realtime_user_1)
   ↓
5. Consumer Command (php console realtime:kafka:consume)
   ↓
6. RealtimeManager (local)
   ↓
7. WebSocket Connections
   ↓
8. Client Browser (realtime notification)
```

---

## 💻 Ví dụ thực tế

### Ví dụ 1: User Notification

**Publish:**
```php
// Khi có event xảy ra
broadcast("user.{$userId}", 'notification', [
    'title' => 'Order Shipped',
    'body' => 'Your order #123 has been shipped'
]);
```

**Consume:**
```bash
php console realtime:kafka:consume --channels=user.1,user.2,user.3
```

### Ví dụ 2: Public Announcement

**Publish:**
```php
broadcast('public.announcements', 'announcement', [
    'title' => 'System Maintenance',
    'message' => 'Scheduled maintenance tonight'
]);
```

**Consume:**
```bash
php console realtime:kafka:consume --channels=public.announcements
```

### Ví dụ 3: Chat Room

**Publish:**
```php
broadcast("chat.{$roomId}", 'message.new', [
    'user_id' => $userId,
    'username' => $username,
    'message' => $message
]);
```

**Consume:**
```bash
php console realtime:kafka:consume --channels=chat.1,chat.2
```

---

## 🎯 Command Reference

### Consumer Command

```bash
# Basic
php console realtime:kafka:consume --channels=test

# Multiple channels
php console realtime:kafka:consume --channels=user.1,user.2,public.news

# With options
php console realtime:kafka:consume \
  --channels=user.1 \
  --batch-size=200 \
  --timeout=500 \
  --max-messages=1000
```

### Options

- `--broker=kafka` - Broker name (default: kafka)
- `--channels=ch1,ch2` - Channels to subscribe (required)
- `--batch-size=N` - Messages per batch (default: 100)
- `--timeout=N` - Poll timeout ms (default: 1000)
- `--max-messages=N` - Max messages (0 = unlimited)
- `--stop-when-empty` - Stop when empty (testing)

---

## 🌐 Multi-Server

**Server A (Publisher):**
```php
// Chỉ cần publish
broadcast('user.1', 'notification', $data);
```

**Server B & C (Consumers):**
```bash
# Server B
php console realtime:kafka:consume --channels=user.1,user.2

# Server C (cùng consumer group = load balancing)
php console realtime:kafka:consume --channels=user.1,user.2
```

**Kết quả:** Messages được phân phối tự động giữa Server B và C.

---

## ✅ Checklist

- [ ] Kafka server đang chạy (`docker ps | grep kafka`)
- [ ] Kafka client library đã cài (`composer show | grep kafka`)
- [ ] `.env` đã config `KAFKA_BROKERS=localhost:9092`
- [ ] `.env` đã set `REALTIME_BROKER=kafka`
- [ ] Consumer command đang chạy
- [ ] Test publish message: `broadcast('test', 'event', ['data' => 'test'])`

---

## 🚨 Troubleshooting

### Lỗi: "Not has broker can connection"

**Nguyên nhân:** Kafka server chưa chạy.

**Giải pháp:**
```bash
# Start Kafka
docker start kafka

# Hoặc check
docker ps | grep kafka
```

### Messages không được nhận

**Kiểm tra:**
1. Consumer có đang chạy không?
2. Channels có đúng không?
3. Kafka server có chạy không?

```bash
# Check consumer
ps aux | grep kafka:consume

# Check Kafka
docker ps | grep kafka
```

---

## 📚 Tài liệu thêm

- [KAFKA_USAGE_GUIDE.md](KAFKA_USAGE_GUIDE.md) - Hướng dẫn chi tiết
- [KAFKA_REALTIME.md](KAFKA_REALTIME.md) - Tài liệu kỹ thuật
- [KAFKA_LIBRARY_COMPARISON.md](KAFKA_LIBRARY_COMPARISON.md) - So sánh libraries

---

**Tóm tắt:**
1. Cài library → 2. Config `.env` → 3. Start Kafka → 4. `broadcast()` → 5. Chạy consumer

Xong! 🎉

