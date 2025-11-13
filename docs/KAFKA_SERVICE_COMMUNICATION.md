# Kafka Service Communication - Service A & Service B

Hướng dẫn chi tiết về mục đích của Kafka và cách Service A giao tiếp với Service B.

---

## 🎯 Mục đích của Kafka trong Source này

### 1. **Multi-Server Communication (Giao tiếp đa server)**

Kafka được dùng như **message broker** để:
- ✅ **Publish messages** từ Service A lên Kafka
- ✅ **Consume messages** từ Kafka ở Service B
- ✅ **Decouple services** - Services không cần biết nhau trực tiếp
- ✅ **Horizontal scaling** - Nhiều servers có thể consume cùng lúc
- ✅ **Message persistence** - Messages được lưu trữ, có thể replay

### 2. **Realtime Broadcasting**

Khi Service A broadcast message:
```
Service A → Kafka Topic → Service B (Consumer) → WebSocket → Clients
```

**Flow:**
1. Service A: `broadcast('channel', 'event', $data)` → Publish lên Kafka
2. Kafka: Lưu message vào topic
3. Service B: Consumer nhận message từ Kafka
4. Service B: Broadcast đến local WebSocket connections
5. Clients: Nhận realtime notification

### 3. **Use Cases**

- ✅ **Notification System**: Service A gửi notification, Service B nhận và gửi đến users
- ✅ **Event Broadcasting**: Service A có event, Service B xử lý và broadcast
- ✅ **Multi-Server Deployment**: Nhiều servers cùng consume, load balancing tự động
- ✅ **Message Queue**: Service A publish, Service B consume và xử lý

---

## 🏗️ Kiến trúc

```
┌─────────────────┐
│   Service A     │
│  (Publisher)    │
│                 │
│  broadcast()    │
│      ↓          │
└────────┬────────┘
         │
         │ Publish
         ▼
┌─────────────────┐
│  Kafka Broker   │
│                 │
│  Topic:         │
│  realtime_ch1   │
│  realtime_ch2   │
└────────┬────────┘
         │
         │ Consume
         ▼
┌─────────────────┐
│   Service B     │
│  (Consumer)     │
│                 │
│  Consumer       │
│      ↓          │
│  broadcast()    │
│      ↓          │
│  WebSocket      │
└─────────────────┘
```

---

## 🚀 Setup Service A & Service B

### Bước 1: Setup Kafka (Chung cho cả 2 services)

```bash
# Start Kafka server
docker run -d --name kafka -p 9092:9092 apache/kafka:latest
```

### Bước 2: Config `.env` (Cả 2 services)

```env
# Kafka Configuration
KAFKA_BROKERS=localhost:9092
KAFKA_TOPIC_PREFIX=realtime
KAFKA_CONSUMER_GROUP=realtime-servers

# Enable Kafka làm default broker
REALTIME_BROKER=kafka
```

### Bước 3: Service A - Publisher (Gửi messages)

**Service A chỉ cần publish, không cần consumer:**

```php
<?php

namespace App\Services;

use Toporia\Framework\Realtime\Contracts\RealtimeManagerInterface;

class NotificationService
{
    public function __construct(
        private readonly RealtimeManagerInterface $realtime
    ) {}

    /**
     * Service A: Gửi notification đến user
     */
    public function sendNotification(int $userId, string $title, string $body): void
    {
        // Broadcast - tự động publish lên Kafka
        $this->realtime->broadcast(
            channel: "user.{$userId}",
            event: 'notification',
            data: [
                'title' => $title,
                'body' => $body,
                'timestamp' => time()
            ]
        );
    }

    /**
     * Service A: Gửi event đến public channel
     */
    public function announce(string $title, string $message): void
    {
        $this->realtime->broadcast(
            channel: 'public.announcements',
            event: 'announcement',
            data: [
                'title' => $title,
                'message' => $message
            ]
        );
    }
}
```

**Controller trong Service A:**

```php
<?php

namespace App\Presentation\Http\Controllers;

use App\Services\NotificationService;
use Toporia\Framework\Http\Request;

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

### Bước 4: Service B - Consumer (Nhận messages)

**Service B chạy consumer command:**

```bash
# Service B: Chạy consumer
php console realtime:kafka:consume --channels=user.1,user.2,public.announcements
```

**Hoặc tạo custom consumer trong Service B:**

```php
<?php

namespace App\Console\Commands;

use Toporia\Framework\Console\Command;
use Toporia\Framework\Realtime\Contracts\RealtimeManagerInterface;

class ServiceBConsumer extends Command
{
    protected string $signature = 'service-b:consume';
    protected string $description = 'Service B: Consume messages from Kafka';

    public function __construct(
        private readonly RealtimeManagerInterface $realtime
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $broker = $this->realtime->broker('kafka');

        if (!$broker) {
            $this->error('Kafka broker not found');
            return 1;
        }

        // Subscribe to channels
        $channels = [
            'user.1',
            'user.2',
            'user.3',
            'public.announcements'
        ];

        foreach ($channels as $channel) {
            $broker->subscribe($channel, function ($message) use ($channel) {
                $this->handleMessage($channel, $message);
            });

            $this->info("Subscribed to: {$channel}");
        }

        $this->info('Consumer started. Waiting for messages...');

        // Start consuming (blocking)
        $broker->consume(1000, 100);

        return 0;
    }

    private function handleMessage(string $channel, $message): void
    {
        $event = $message->getEvent();
        $data = $message->getData();

        $this->info("Received: {$channel} - {$event}");

        // Service B: Xử lý message và broadcast đến local connections
        $this->realtime->broadcast($channel, $event, $data);

        // Hoặc custom business logic
        switch ($event) {
            case 'notification':
                $this->processNotification($data);
                break;
            case 'announcement':
                $this->processAnnouncement($data);
                break;
        }
    }

    private function processNotification(array $data): void
    {
        // Service B: Custom logic
        $this->info("Processing notification: {$data['title']}");

        // Có thể: Save to database, send email, etc.
    }

    private function processAnnouncement(array $data): void
    {
        // Service B: Custom logic
        $this->info("Processing announcement: {$data['title']}");
    }
}
```

---

## 💡 Ví dụ thực tế

### Ví dụ 1: Order Service → Notification Service

**Service A (Order Service):**

```php
<?php

namespace App\Order\Services;

use Toporia\Framework\Realtime\Contracts\RealtimeManagerInterface;

class OrderService
{
    public function __construct(
        private readonly RealtimeManagerInterface $realtime
    ) {}

    public function createOrder(array $orderData): void
    {
        // Create order in database
        $order = Order::create($orderData);

        // Service A: Publish event lên Kafka
        $this->realtime->broadcast(
            channel: "user.{$order->user_id}",
            event: 'order.created',
            data: [
                'order_id' => $order->id,
                'status' => 'pending',
                'total' => $order->total
            ]
        );

        // Publish to admin channel
        $this->realtime->broadcast(
            channel: 'admin.orders',
            event: 'order.new',
            data: [
                'order_id' => $order->id,
                'user_id' => $order->user_id
            ]
        );
    }
}
```

**Service B (Notification Service):**

```bash
# Chạy consumer
php console realtime:kafka:consume --channels=user.1,user.2,admin.orders
```

**Hoặc custom consumer:**

```php
<?php

namespace App\Notification\Console\Commands;

class NotificationConsumer extends Command
{
    public function handle(): int
    {
        $broker = $this->realtime->broker('kafka');

        // Subscribe to order events
        $broker->subscribe('user.1', function ($message) {
            if ($message->getEvent() === 'order.created') {
                $this->sendOrderNotification($message->getData());
            }
        });

        $broker->subscribe('admin.orders', function ($message) {
            if ($message->getEvent() === 'order.new') {
                $this->notifyAdmins($message->getData());
            }
        });

        $broker->consume(1000, 100);
        return 0;
    }

    private function sendOrderNotification(array $data): void
    {
        // Service B: Gửi notification đến user
        $this->notificationService->send(
            userId: $data['user_id'],
            title: 'Order Created',
            body: "Your order #{$data['order_id']} has been created"
        );
    }
}
```

### Ví dụ 2: Chat Service → Realtime Service

**Service A (Chat Service):**

```php
<?php

namespace App\Chat\Services;

class ChatService
{
    public function sendMessage(int $roomId, int $userId, string $message): void
    {
        // Save message to database
        $chatMessage = ChatMessage::create([
            'room_id' => $roomId,
            'user_id' => $userId,
            'message' => $message
        ]);

        // Service A: Publish lên Kafka
        $this->realtime->broadcast(
            channel: "chat.{$roomId}",
            event: 'message.new',
            data: [
                'message_id' => $chatMessage->id,
                'user_id' => $userId,
                'message' => $message,
                'timestamp' => time()
            ]
        );
    }
}
```

**Service B (Realtime Service):**

```bash
# Chạy consumer cho chat rooms
php console realtime:kafka:consume --channels=chat.1,chat.2,chat.3
```

**Service B sẽ:**
1. Nhận message từ Kafka
2. Broadcast đến local WebSocket connections
3. Clients nhận realtime message

---

## 🔄 Flow hoàn chỉnh

### Scenario: Service A gửi notification, Service B nhận và gửi đến user

```
1. Service A: User tạo order
   ↓
2. Service A: OrderService::createOrder()
   ↓
3. Service A: realtime()->broadcast('user.1', 'order.created', $data)
   ↓
4. RealtimeManager: Kiểm tra có broker (Kafka) → Publish lên Kafka
   ↓
5. Kafka: Lưu message vào topic "realtime_user_1"
   ↓
6. Service B: Consumer đang chạy, poll message từ Kafka
   ↓
7. Service B: Nhận message, invoke callback
   ↓
8. Service B: realtime()->broadcast('user.1', 'order.created', $data)
   ↓
9. Service B: RealtimeManager broadcast đến local WebSocket connections
   ↓
10. Client: Nhận realtime notification trong browser
```

---

## 📝 Code Examples

### Service A - Simple Publisher

```php
<?php

// Service A: Chỉ cần publish
use Toporia\Framework\Realtime\Contracts\RealtimeManagerInterface;

class ServiceA
{
    public function __construct(
        private readonly RealtimeManagerInterface $realtime
    ) {}

    public function sendEvent(string $channel, string $event, array $data): void
    {
        // Publish lên Kafka
        $this->realtime->broadcast($channel, $event, $data);
    }
}
```

### Service B - Simple Consumer

```php
<?php

// Service B: Chỉ cần consume
use Toporia\Framework\Realtime\Contracts\RealtimeManagerInterface;

class ServiceB
{
    public function __construct(
        private readonly RealtimeManagerInterface $realtime
    ) {}

    public function startConsumer(): void
    {
        $broker = $this->realtime->broker('kafka');

        // Subscribe
        $broker->subscribe('test', function ($message) {
            // Xử lý message
            $this->handleMessage($message);
        });

        // Start consuming
        $broker->consume(1000, 100);
    }

    private function handleMessage($message): void
    {
        // Broadcast đến local connections
        $this->realtime->broadcast(
            $message->getChannel(),
            $message->getEvent(),
            $message->getData()
        );
    }
}
```

---

## 🎯 Best Practices

### 1. **Service A (Publisher)**
- ✅ Chỉ publish, không cần consumer
- ✅ Dùng `broadcast()` helper hoặc `realtime()->broadcast()`
- ✅ Không cần chạy consumer command

### 2. **Service B (Consumer)**
- ✅ Chạy consumer command hoặc custom consumer
- ✅ Subscribe to channels cần thiết
- ✅ Xử lý messages và broadcast đến local connections

### 3. **Channel Naming**
```php
// ✅ Good: Rõ ràng
"user.{$userId}"           // User-specific
"admin.orders"            // Admin channel
"chat.{$roomId}"          // Chat room

// ❌ Bad: Không rõ ràng
"channel1"
"test"
```

### 4. **Multi-Service Setup**

**Service A, B, C cùng consume (Load Balancing):**

```bash
# Service A
php console realtime:kafka:consume --channels=user.1,user.2

# Service B (cùng consumer group = load balancing)
php console realtime:kafka:consume --channels=user.1,user.2

# Service C (cùng consumer group = load balancing)
php console realtime:kafka:consume --channels=user.1,user.2
```

**Kết quả:** Messages được phân phối tự động giữa A, B, C.

---

## ✅ Tóm tắt

### Mục đích của Kafka:
1. ✅ **Multi-server communication** - Services giao tiếp qua Kafka
2. ✅ **Message persistence** - Messages được lưu trữ
3. ✅ **Horizontal scaling** - Nhiều consumers cùng consume
4. ✅ **Decoupling** - Services không cần biết nhau trực tiếp

### Service A → Service B:
1. **Service A**: `broadcast()` → Publish lên Kafka
2. **Kafka**: Lưu message vào topic
3. **Service B**: Consumer nhận message
4. **Service B**: Xử lý và broadcast đến local connections

### Setup:
- **Service A**: Chỉ cần config Kafka, dùng `broadcast()`
- **Service B**: Config Kafka + chạy consumer command

**Đơn giản vậy thôi!** 🚀

