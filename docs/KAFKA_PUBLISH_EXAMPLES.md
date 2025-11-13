# Kafka Publish Examples - Business Logic

## Tổng quan

Hướng dẫn cách publish messages từ bất kỳ đâu trong application để xử lý business logic (không phải realtime).

## Architecture

```
ANYWHERE (HTTP, CLI, Jobs, Events)
    ↓
publish to Kafka topic (orders.events)
    ↓
Business Logic Consumer (CLI command)
    ↓
Process Business Logic (DB, Email, Analytics)
```

## Ví dụ: Order Tracking

### 1. Publish từ HTTP Controller

```php
// routes/web.php hoặc Controller
$router->post('/api/orders', function (Request $request, Response $response) {
    // Create order
    $order = Order::create([
        'user_id' => $request->input('user_id'),
        'total' => $request->input('total'),
        'items' => $request->input('items'),
    ]);

    // Publish order event to Kafka (business logic topic)
    $kafkaBroker = realtime()->broker('kafka');

    if ($kafkaBroker) {
        $kafkaBroker->publish('orders.events', \Toporia\Framework\Realtime\Message::event(
            'orders.events',  // Topic (not a realtime channel)
            'order.created',  // Event type
            [                 // Event data
                'order_id' => $order->id,
                'user_id' => $order->user_id,
                'total' => $order->total,
                'items' => $order->items,
                'created_at' => $order->created_at->toIso8601String(),
            ]
        ));
    }

    return $response->json([
        'success' => true,
        'order' => $order,
    ]);
});
```

### 2. Publish từ Background Job

```php
// app/Jobs/ProcessOrderJob.php
namespace App\Jobs;

use Toporia\Framework\Queue\Contracts\JobInterface;

final class ProcessOrderJob implements JobInterface
{
    public function __construct(
        private readonly int $orderId
    ) {}

    public function handle(): void
    {
        $order = Order::find($this->orderId);

        // Process order
        $order->update(['status' => 'processed']);

        // Publish event to Kafka
        $kafkaBroker = realtime()->broker('kafka');

        if ($kafkaBroker) {
            $kafkaBroker->publish('orders.events', \Toporia\Framework\Realtime\Message::event(
                'orders.events',
                'order.processed',
                [
                    'order_id' => $order->id,
                    'status' => 'processed',
                    'processed_at' => now()->toIso8601String(),
                ]
            ));
        }
    }
}
```

### 3. Publish từ Event Listener

```php
// app/Listeners/OrderEventListener.php
namespace App\Listeners;

use App\Events\OrderCreated;
use Toporia\Framework\Realtime\Message;

final class OrderEventListener
{
    public function handle(OrderCreated $event): void
    {
        $order = $event->order;
        $kafkaBroker = realtime()->broker('kafka');

        if ($kafkaBroker) {
            $kafkaBroker->publish('orders.events', Message::event(
                'orders.events',
                'order.created',
                [
                    'order_id' => $order->id,
                    'user_id' => $order->user_id,
                    'total' => $order->total,
                ]
            ));
        }
    }
}
```

### 4. Publish từ CLI Command

```php
// app/Console/Commands/ImportOrdersCommand.php
namespace App\Console\Commands;

use Toporia\Framework\Console\Command;

final class ImportOrdersCommand extends Command
{
    protected string $signature = 'orders:import {file}';

    public function handle(): int
    {
        $orders = $this->parseOrdersFromFile($this->argument('file'));

        foreach ($orders as $orderData) {
            $order = Order::create($orderData);

            // Publish to Kafka
            $kafkaBroker = realtime()->broker('kafka');
            if ($kafkaBroker) {
                $kafkaBroker->publish('orders.events', Message::event(
                    'orders.events',
                    'order.created',
                    ['order_id' => $order->id, ...]
                ));
            }
        }

        return 0;
    }
}
```

## Consumer Command

```bash
# Run order tracking consumer
php console order:tracking:consume

# With options
php console order:tracking:consume --batch-size=100 --dlq-enabled
```

## Key Points

1. **Topic khác với Realtime Channels**
   - Business logic: `orders.events`, `analytics.events`
   - Realtime: `realtime.user.*`, `realtime.public.*`

2. **Publish từ bất kỳ đâu**
   - HTTP requests ✅
   - CLI commands ✅
   - Background jobs ✅
   - Event listeners ✅

3. **Consumer chỉ trong CLI**
   - Long-lived processes
   - Chạy như daemon

4. **Tách biệt concerns**
   - Business logic consumers ≠ Realtime consumers
   - Mỗi loại có topic và consumer group riêng

