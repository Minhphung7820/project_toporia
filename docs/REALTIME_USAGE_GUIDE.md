# Realtime System - Usage Guide

Complete guide for using the Realtime communication system.

## Installation

### 1. Register Service Provider

Add to `bootstrap/app.php`:

```php
$app->registerProviders([
    // ... other providers
    \Toporia\Framework\Providers\RealtimeServiceProvider::class,
]);
```

### 2. Configure `.env`

```env
# Transport (memory, websocket, sse, longpolling)
REALTIME_TRANSPORT=memory

# Broker for multi-server (null, redis, rabbitmq, nats)
REALTIME_BROKER=null

# Redis (if using Redis broker)
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=
REDIS_DB=0
```

### 3. Configure Channels

Edit `config/realtime.php` authorizers:

```php
'authorizers' => [
    // Private chat rooms
    'private-chat.*' => function ($connection, $channel) {
        $chatId = str_replace('private-chat.', '', $channel);
        $userId = $connection->getUserId();
        return ChatRoom::find($chatId)->hasUser($userId);
    },

    // User-specific channels
    'user.*' => function ($connection, $channel) {
        $userId = str_replace('user.', '', $channel);
        return $connection->getUserId() == $userId;
    },
],
```

---

## Basic Usage

### 1. Broadcasting Events

```php
use Toporia\Framework\Support\Accessors\Realtime;

// Option 1: Via Accessor
Realtime::broadcast('chat.room.1', 'message.sent', [
    'user' => 'John',
    'text' => 'Hello everyone!',
    'timestamp' => time()
]);

// Option 2: Via Helper Function
broadcast('chat.room.1', 'message.sent', [
    'user' => 'John',
    'text' => 'Hello!'
]);

// Option 3: Via Container
app('realtime')->broadcast('chat.room.1', 'message.sent', $data);
```

### 2. Send to Specific User

```php
// Send to all connections of a user
Realtime::sendToUser($userId, 'notification.new', [
    'title' => 'New Message',
    'body' => 'You have a new message from Jane',
    'url' => '/messages/123'
]);
```

### 3. Send to Specific Connection

```php
// Send to single connection
Realtime::send($connectionId, 'private.message', [
    'from' => 'Admin',
    'text' => 'Welcome to the platform!'
]);
```

---

## Supported Drivers

### Transport Drivers

#### 1. Memory Transport (Testing/Single Server)

```php
// config/realtime.php
'default_transport' => 'memory',

'transports' => [
    'memory' => [
        'driver' => 'memory',
    ],
],
```

**Use Cases**:
- Unit testing
- Integration testing
- Single-server without WebSocket
- Background jobs → Realtime events

**Limitations**:
- No actual client connections
- Single PHP process only
- No HTTP/WebSocket server

#### 2. WebSocket Transport (Production - ✅ Ready)

```php
'default_transport' => 'websocket',

'transports' => [
    'websocket' => [
        'driver' => 'websocket',
        'host' => '0.0.0.0',
        'port' => 6001,
        'ssl' => false,
    ],
],
```

**Requires**: Swoole or RoadRunner

**Install Swoole**:
```bash
pecl install swoole
php -m | grep swoole
```

**Start Server**:
```bash
php console realtime:serve --transport=websocket
```

**Performance**:
- Latency: 1-5ms
- Throughput: 100k+ msg/sec
- Concurrent: 10k+ connections

#### 3. SSE Transport (Server-Sent Events - ✅ Ready)

```php
'default_transport' => 'sse',

'transports' => [
    'sse' => [
        'driver' => 'sse',
        'path' => '/realtime/sse',
    ],
],
```

**Use Cases**:
- Notifications
- Live updates
- One-way server→client communication

**Performance**:
- Latency: 10-50ms
- Throughput: 10k msg/sec
- Concurrent: 1k+ connections

#### 4. Long-Polling Transport (Fallback - ✅ Ready)

```php
'default_transport' => 'longpolling',

'transports' => [
    'longpolling' => [
        'driver' => 'longpolling',
        'path' => '/realtime/poll',
        'timeout' => 30,
    ],
],
```

**Use Cases**:
- Legacy browser support
- Fallback when WebSocket blocked

**Performance**:
- Latency: 100-500ms
- Throughput: 1k msg/sec
- Concurrent: 100+ connections

---

### Broker Drivers (Multi-Server Scaling)

#### 1. Redis Broker (Recommended)

```php
// config/realtime.php
'default_broker' => 'redis',

'brokers' => [
    'redis' => [
        'driver' => 'redis',
        'host' => '127.0.0.1',
        'port' => 6379,
        'password' => null,
        'database' => 0,
    ],
],
```

**Architecture**:
```
Server A → Redis Pub/Sub → Server B
                          → Server C
                          → Server D
```

**Performance**:
- Latency: ~0.1ms
- Throughput: 100k+ msg/sec
- Memory: Ephemeral (no persistence)

**Setup**:
```bash
apt-get install redis-server
redis-cli ping # Should return PONG
```

#### 2. RabbitMQ Broker (Enterprise)

```php
'default_broker' => 'rabbitmq',

'brokers' => [
    'rabbitmq' => [
        'driver' => 'rabbitmq',
        'host' => env('RABBITMQ_HOST', '127.0.0.1'),
        'port' => env('RABBITMQ_PORT', 5672),
        'user' => env('RABBITMQ_USER', 'guest'),
        'password' => env('RABBITMQ_PASSWORD', 'guest'),
        'vhost' => env('RABBITMQ_VHOST', '/'),
        'exchange' => env('RABBITMQ_EXCHANGE', 'realtime'),
        'exchange_type' => env('RABBITMQ_EXCHANGE_TYPE', 'topic'),
        'prefetch_count' => env('RABBITMQ_PREFETCH_COUNT', 50),
        'queue_prefix' => env('RABBITMQ_QUEUE_PREFIX', 'realtime'),
        'queue_durable' => env('RABBITMQ_QUEUE_DURABLE', false),
        'queue_exclusive' => env('RABBITMQ_QUEUE_EXCLUSIVE', true),
        'queue_auto_delete' => env('RABBITMQ_QUEUE_AUTO_DELETE', true),
        'persistent_messages' => env('RABBITMQ_PERSISTENT_MESSAGES', true),
    ],
],
```

**Architecture**:
```
Server A ─┐
          ├─> Exchange "realtime" (topic) → auto queues per node → local clients
Server B ─┘
```

**Setup**:
```bash
docker run -d --name rabbitmq \
  -p 5672:5672 -p 15672:15672 \
  rabbitmq:3-management
# Open http://localhost:15672 (guest/guest) to monitor queues/exchanges
```

**Performance**:
- Latency: ~1ms
- Throughput: 50k msg/sec
- Features: Durable routing, QoS, message persistence

#### 3. NATS Broker (Ultra-Fast - TODO)

```php
'default_broker' => 'nats',

'brokers' => [
    'nats' => [
        'driver' => 'nats',
        'url' => 'nats://localhost:4222',
    ],
],
```

**Performance**:
- Latency: ~0.05ms
- Throughput: 1M+ msg/sec
- Features: Clustering, wildcard subjects

---

## Usage Examples

### Example 1: Chat Room

```php
// When user sends message
class ChatController
{
    public function sendMessage(Request $request)
    {
        $message = Message::create([
            'user_id' => auth()->id(),
            'room_id' => $request->input('room_id'),
            'text' => $request->input('text')
        ]);

        // Broadcast to room
        broadcast('chat.room.' . $message->room_id, 'message.sent', [
            'id' => $message->id,
            'user' => auth()->user()->name,
            'text' => $message->text,
            'timestamp' => $message->created_at
        ]);

        return response()->json(['success' => true]);
    }
}
```

### Example 2: User Notifications

```php
// When user receives notification
class NotificationController
{
    public function send(User $user, string $title, string $body)
    {
        // Save to database
        $notification = Notification::create([
            'user_id' => $user->id,
            'title' => $title,
            'body' => $body
        ]);

        // Send realtime notification
        Realtime::sendToUser($user->id, 'notification.new', [
            'id' => $notification->id,
            'title' => $title,
            'body' => $body,
            'url' => '/notifications/' . $notification->id
        ]);
    }
}
```

### Example 3: Typing Indicators

```php
// When user starts typing
class ChatTypingController
{
    public function startTyping(Request $request)
    {
        $roomId = $request->input('room_id');
        $userId = auth()->id();

        broadcast('chat.room.' . $roomId, 'typing.started', [
            'user_id' => $userId,
            'user_name' => auth()->user()->name
        ]);
    }

    public function stopTyping(Request $request)
    {
        $roomId = $request->input('room_id');
        $userId = auth()->id();

        broadcast('chat.room.' . $roomId, 'typing.stopped', [
            'user_id' => $userId
        ]);
    }
}
```

### Example 4: Presence Tracking

```php
// Get channel presence
$channel = Realtime::channel('presence-room.1');
$presenceData = $channel->getPresenceData();

// Returns:
// [
//     ['user_id' => 1, 'user_info' => ['name' => 'John'], 'connected_at' => 1234567890],
//     ['user_id' => 2, 'user_info' => ['name' => 'Jane'], 'connected_at' => 1234567900],
// ]

// Broadcast presence update
broadcast('presence-room.1', 'presence.sync', [
    'members' => $presenceData
]);
```

---

## Channel Types

### 1. Public Channels

Anyone can subscribe without authentication.

```php
// Public channel names
'news'
'announcements'
'public.updates'
```

### 2. Private Channels

Require authentication, prefix with `private-` or `private.`

```php
// Private channel names
'private-chat.123'
'private.user.456'
'user.789'  // User-specific channel
```

**Authorization**:
```php
// config/realtime.php
'authorizers' => [
    'private-chat.*' => function ($connection, $channel) {
        $chatId = str_replace('private-chat.', '', $channel);
        return ChatRoom::find($chatId)->hasUser($connection->getUserId());
    },
],
```

### 3. Presence Channels

Track who's online, prefix with `presence-` or `presence.`

```php
// Presence channel names
'presence-room.1'
'presence.chat.123'
```

**Features**:
- Automatic join/leave events
- Online user list
- User metadata

---

## Helper Functions

```php
// Broadcast to channel
broadcast('chat.room.1', 'message.sent', $data);

// Via Accessor (same as above)
use Toporia\Framework\Support\Accessors\Realtime;
Realtime::broadcast('chat.room.1', 'message.sent', $data);

// Via Container
app('realtime')->broadcast('chat.room.1', 'message.sent', $data);
```

---

## Performance Tips

1. **Use Redis Broker for Multi-Server**
   ```php
   'default_broker' => 'redis',
   ```

2. **Use WebSocket for Production**
   ```php
   'default_transport' => 'websocket',
   ```

3. **Enable Rate Limiting**
   ```php
   'rate_limit' => [
       'enabled' => true,
       'messages_per_minute' => 60,
   ],
   ```

4. **Optimize Channel Names**
   - Short names: `chat.1` not `chat.room.number.1`
   - Use IDs not slugs: `room.123` not `room.my-awesome-room`

5. **Batch Messages**
   ```php
   // Bad: 10 separate broadcasts
   for ($i = 0; $i < 10; $i++) {
       broadcast('news', 'update', ['item' => $i]);
   }

   // Good: 1 broadcast with all data
   broadcast('news', 'updates', ['items' => range(0, 9)]);
   ```

---

## Next Steps

1. **Install Swoole** for WebSocket support
2. **Setup Redis** for multi-server scaling
3. **Configure channels** in `config/realtime.php`
4. **Build JavaScript client** (see below)
5. **Test with example chat app**

---

## JavaScript Client (Planned)

```javascript
// Connect
const realtime = new RealtimeClient('ws://localhost:6001');

// Subscribe to channel
const channel = realtime.subscribe('chat.room.1');

// Listen for events
channel.on('message.sent', (data) => {
    console.log(`${data.user}: ${data.text}`);
});

// Send message
channel.send('message.sent', {
    text: 'Hello from client!'
});
```
