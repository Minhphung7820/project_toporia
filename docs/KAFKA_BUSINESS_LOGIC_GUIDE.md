# Kafka Consumer cho Business Logic

## Tổng quan

Framework hỗ trợ 2 loại Kafka consumers:

1. **Realtime Consumers** (`RealtimeKafkaConsumerCommand`, `RealtimeRedisConsumerCommand`)
   - Dành cho realtime communication system
   - Broadcast messages đến clients qua WebSocket/SSE
   - Signature: `realtime:kafka:consume`, `realtime:redis:consume`

2. **Business Logic Consumers** (Custom consumers)
   - Dành cho business logic (order tracking, analytics, notifications, etc.)
   - Xử lý business logic, không liên quan đến realtime
   - Signature: Tùy chỉnh (ví dụ: `order:tracking:consume`)

## Architecture

```
┌─────────────────────────────────────────────────────────┐
│                    PRODUCER (Anywhere)                   │
│  HTTP, CLI, Jobs, Events → publish to Kafka topic       │
└─────────────────────────────────────────────────────────┘
                        ↓
┌─────────────────────────────────────────────────────────┐
│                    KAFKA TOPIC                           │
│  - orders.events (business logic)                        │
│  - realtime.* (realtime system)                          │
└─────────────────────────────────────────────────────────┘
                        ↓
        ┌───────────────┴───────────────┐
        ↓                               ↓
┌───────────────────┐         ┌───────────────────┐
│ Business Consumer │         │ Realtime Consumer │
│ (Order Tracking) │         │ (Broadcast)       │
└───────────────────┘         └───────────────────┘
        ↓                               ↓
┌───────────────────┐         ┌───────────────────┐
│ Business Logic    │         │ Broadcast to      │
│ - Update DB       │         │ WebSocket Clients │
│ - Send Email      │         │                   │
│ - Analytics       │         │                   │
└───────────────────┘         └───────────────────┘
```

## Tạo Consumer cho Business Logic

### Ví dụ: Order Tracking Consumer

**File:** `src/App/Console/Commands/OrderTrackingConsumerCommand.php`

```php
<?php

namespace App\Console\Commands;

use Toporia\Framework\Console\Commands\Kafka\Base\AbstractBatchKafkaConsumer;
use Toporia\Framework\Console\Commands\Kafka\Contracts\BatchingMessagesHandlerInterface;

final class OrderTrackingConsumerCommand extends AbstractBatchKafkaConsumer
{
    protected string $signature = 'order:tracking:consume {--batch-size=50}';
    protected string $description = 'Consume order events and process tracking';

    protected function getTopic(): string
    {
        return 'orders.events'; // Business logic topic
    }

    protected function getGroupId(): string
    {
        return 'order-tracking-consumers';
    }

    protected function getOffset(): string
    {
        return 'earliest';
    }

    protected function getBatchSizeLimit(): int
    {
        return (int) $this->option('batch-size', 50);
    }

    protected function getBatchReleaseInterval(): int
    {
        return 2000; // 2 seconds
    }

    public function handleMessages(Collection $messages): void
    {
        foreach ($messages as $item) {
            $message = $item['message'] ?? null;
            $orderData = $this->extractOrderData($message);

            // Your business logic here
            $this->processOrderEvent($orderData);
        }
    }

    private function processOrderEvent(array $orderData): void
    {
        $event = $orderData['event'];
        $orderId = $orderData['order_id'];

        match ($event) {
            'order.created' => $this->handleOrderCreated($orderData),
            'order.shipped' => $this->handleOrderShipped($orderData),
            'order.delivered' => $this->handleOrderDelivered($orderData),
            default => $this->handleUnknownEvent($orderData, $event),
        };
    }

    private function handleOrderCreated(array $orderData): void
    {
        // Create tracking record
        // Send confirmation email
        // Update inventory
    }

    // ... other handlers
}
```

## Publish Messages từ Bất kỳ đâu

### 1. Từ HTTP Controller

```php
// routes/web.php hoặc Controller
$router->post('/orders', function (Request $request, Response $response) {
    // Create order
    $order = Order::create($request->input());

    // Publish order event to Kafka (business logic topic)
    $kafkaBroker = realtime()->broker('kafka');
    $kafkaBroker->publish('orders.events', Message::event(
        'orders.events',
        'order.created',
        [
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'total' => $order->total,
            'items' => $order->items,
        ]
    ));

    return $response->json(['order' => $order]);
});
```

### 2. Từ CLI Command

```php
// app/Console/Commands/CreateOrderCommand.php
public function handle(): int
{
    $order = Order::create([...]);

    // Publish to Kafka
    $kafkaBroker = $this->realtime->broker('kafka');
    $kafkaBroker->publish('orders.events', Message::event(
        'orders.events',
        'order.created',
        ['order_id' => $order->id, ...]
    ));

    return 0;
}
```

### 3. Từ Background Job

```php
// app/Jobs/ProcessOrderJob.php
public function handle(): void
{
    // Process order
    $order->update(['status' => 'processed']);

    // Publish event
    $kafkaBroker = realtime()->broker('kafka');
    $kafkaBroker->publish('orders.events', Message::event(
        'orders.events',
        'order.processed',
        ['order_id' => $order->id]
    ));
}
```

### 4. Từ Event Listener

```php
// app/Listeners/OrderEventListener.php
public function handle(OrderCreated $event): void
{
    $kafkaBroker = realtime()->broker('kafka');
    $kafkaBroker->publish('orders.events', Message::event(
        'orders.events',
        'order.created',
        ['order_id' => $event->order->id]
    ));
}
```

## So sánh: Realtime vs Business Logic

| Aspect | Realtime Consumer | Business Logic Consumer |
|--------|-------------------|-------------------------|
| **Purpose** | Broadcast đến clients | Xử lý business logic |
| **Topic** | `realtime.*` | `orders.*`, `analytics.*`, etc. |
| **Output** | WebSocket/SSE clients | Database, Email, Analytics |
| **Command** | `realtime:kafka:consume` | `order:tracking:consume` |
| **Base Class** | `AbstractBatchKafkaConsumer` | `AbstractBatchKafkaConsumer` |
| **Usage** | Multi-server realtime | Business processing |

## Best Practices

### 1. Tách biệt Topics

```php
// Business logic topics
'orders.events'      // Order events
'analytics.events'   // Analytics events
'notifications'      // Notification events

// Realtime topics (framework tự động)
'realtime.user.*'    // Realtime channels
'realtime.public.*'  // Realtime channels
```

### 2. Consumer Groups

```php
// Mỗi business logic consumer có group riêng
'order-tracking-consumers'     // Order tracking
'analytics-consumers'          // Analytics
'notification-consumers'       // Notifications
```

### 3. Error Handling

```php
// Sử dụng DLQ cho business logic
php console order:tracking:consume --dlq-enabled
```

### 4. Batch Processing

```php
// Tối ưu batch size cho business logic
protected function getBatchSizeLimit(): int
{
    return 50; // Smaller batches for business logic
}
```

## Example: Complete Flow

### 1. Publish Order Event (HTTP)

```php
// POST /api/orders
$order = Order::create($data);

// Publish to Kafka
$kafkaBroker = realtime()->broker('kafka');
$kafkaBroker->publish('orders.events', Message::event(
    'orders.events',
    'order.created',
    [
        'order_id' => $order->id,
        'user_id' => $order->user_id,
        'total' => $order->total,
        'created_at' => $order->created_at,
    ]
));
```

### 2. Consume Order Events (CLI)

```bash
# Run consumer
php console order:tracking:consume --batch-size=50
```

### 3. Process Business Logic

```php
// OrderTrackingConsumerCommand.php
public function handleMessages(Collection $messages): void
{
    foreach ($messages as $item) {
        $orderData = $this->extractOrderData($item['message']);

        // Update database
        OrderTracking::create([
            'order_id' => $orderData['order_id'],
            'status' => $orderData['event'],
            'timestamp' => now(),
        ]);

        // Send notification
        NotificationService::send($orderData);

        // Update analytics
        AnalyticsService::record($orderData);
    }
}
```

## Summary

- **Realtime Consumers**: Cho realtime system (broadcast đến clients)
- **Business Logic Consumers**: Cho business logic (order tracking, analytics, etc.)
- **Publish**: Có thể publish từ bất kỳ đâu (HTTP, CLI, Jobs, Events)
- **Consume**: Chỉ trong CLI commands (long-lived processes)
- **Topics**: Tách biệt topics cho business logic và realtime

