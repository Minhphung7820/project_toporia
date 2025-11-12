# RabbitMQ Performance: basic_consume vs basic_get

## Tổng quan

Có 2 cách chính để nhận messages từ RabbitMQ:

### 1. `basic_get()` - Pull Model (Polling)
```php
$message = $channel->basic_get($queue, true);
```

### 2. `basic_consume() + wait()` - Push Model (Event-driven)
```php
$channel->basic_consume($queue, $tag, false, false, false, false, $callback);
while ($channel->is_consuming()) {
    $channel->wait();
}
```

## So sánh Performance

### `basic_consume + wait()` - **TỐI ƯU HƠN** ✅

**Ưu điểm:**
1. **Push-based model**: Server tự động push messages đến client
   - Zero latency khi có message mới
   - Không cần polling loop

2. **Prefetch support**: Nhận nhiều messages cùng lúc
   ```php
   $channel->basic_qos(null, 10, false); // Prefetch 10 messages
   ```
   - Giảm network round-trips
   - Tăng throughput đáng kể

3. **Resource efficiency**:
   - Long-lived consumer (1 connection setup)
   - Server-side buffering
   - Ít network overhead

4. **Scalability**:
   - Hỗ trợ multiple consumers
   - Load balancing tự động
   - Better for high-throughput systems

**Nhược điểm:**
- Phức tạp hơn (cần callback pattern)
- Cần refactor Worker để dùng event-driven
- Khó handle timeout/cleanup

**Performance Metrics:**
- Latency: ~0.1-1ms (push-based)
- Throughput: 10,000-50,000 msg/s (với prefetch)
- CPU: Thấp (event-driven)
- Network: Ít round-trips

---

### `basic_get()` - Đơn giản hơn nhưng chậm hơn ⚠️

**Ưu điểm:**
1. **Đơn giản**: Dễ implement và debug
2. **Flexible**: Có thể check queue bất cứ lúc nào
3. **Synchronous**: Phù hợp với polling pattern hiện tại
4. **No state**: Không cần quản lý consumer state

**Nhược điểm:**
1. **Pull-based model**: Client phải poll server
   - Latency cao (phụ thuộc vào sleep time)
   - Wasted CPU cycles khi không có message

2. **No prefetch**: 1 request = 1 message
   - Nhiều network round-trips
   - Overhead cao

3. **Resource waste**:
   - Constant polling (ngay cả khi không có message)
   - Network overhead cho mỗi poll
   - CPU waste trong sleep loop

**Performance Metrics:**
- Latency: 1-1000ms (phụ thuộc sleep time)
- Throughput: 1,000-5,000 msg/s (limited by polling)
- CPU: Trung bình (polling + sleep)
- Network: Nhiều round-trips

---

## Benchmark Comparison

### Scenario: 10,000 messages

| Metric | basic_get() | basic_consume() |
|--------|-------------|-----------------|
| **Time** | ~10-30s | ~2-5s |
| **Network Calls** | 10,000+ | ~100 (với prefetch=100) |
| **CPU Usage** | Medium | Low |
| **Memory** | Low | Medium (prefetch buffer) |
| **Latency** | 1-1000ms | 0.1-1ms |

---

## Khi nào dùng cái nào?

### Dùng `basic_consume()` khi:
✅ High-throughput systems (>1000 msg/s)
✅ Low latency requirements
✅ Long-running workers
✅ Multiple consumers
✅ Production environments

### Dùng `basic_get()` khi:
✅ Low message rate (<100 msg/s)
✅ Simple polling pattern
✅ Testing/debugging
✅ One-off message retrieval
✅ Development environments

---

## Recommendation cho Toporia Framework

### Hiện tại (basic_get):
- ✅ Đơn giản, dễ maintain
- ✅ Phù hợp với Worker pattern hiện tại
- ⚠️ Chậm hơn (30s cho 10,000 messages)

### Nên chuyển sang basic_consume:
- ✅ Nhanh hơn 5-10x
- ✅ Tối ưu resource
- ⚠️ Cần refactor Worker để dùng callback

### Hybrid Approach (Best of both):
```php
// Fast path: basic_get() nếu có message ngay
$message = $channel->basic_get($queue, true);
if ($message !== null) {
    return $message;
}

// Slow path: basic_consume() với timeout nếu không có message
// (blocking wait thay vì sleep)
```

---

## Implementation Example

### basic_consume Pattern (Recommended):
```php
public function pop(string $queue = 'default'): ?JobInterface
{
    $channel = $this->getChannel();
    $this->declareQueue($queue);

    // Set prefetch for batch delivery
    $channel->basic_qos(null, 10, false);

    $receivedMessage = null;
    $consumerTag = 'consumer_' . uniqid();

    $callback = function (AMQPMessage $msg) use (&$receivedMessage, $channel, $consumerTag) {
        $receivedMessage = $msg;
        $channel->basic_cancel($consumerTag);
    };

    // Start consuming
    $channel->basic_consume($queue, $consumerTag, false, true, false, false, $callback);

    // Wait with timeout (blocking)
    try {
        $channel->wait(null, false, 1.0); // 1 second timeout
    } catch (AMQPTimeoutException $e) {
        $channel->basic_cancel($consumerTag);
        return null;
    }

    if ($receivedMessage === null) {
        return null;
    }

    return unserialize($receivedMessage->getBody());
}
```

### basic_get Pattern (Current):
```php
public function pop(string $queue = 'default'): ?JobInterface
{
    $channel = $this->getChannel();
    $this->declareQueue($queue);

    // Simple polling
    $message = $channel->basic_get($queue, true);

    if ($message === null) {
        return null; // Worker sẽ sleep và retry
    }

    return unserialize($message->getBody());
}
```

---

## Kết luận

**`basic_consume + wait()` tối ưu hơn 5-10x** về performance, nhưng:
- Phức tạp hơn
- Cần refactor Worker

**`basic_get()` đơn giản hơn** nhưng:
- Chậm hơn
- Tốn resource hơn

**Recommendation**:
- **Production**: Dùng `basic_consume()` với prefetch
- **Development**: Có thể dùng `basic_get()` cho đơn giản
- **Best**: Hybrid approach (fast path + slow path)

