# Socket.IO Gateway - Complete Guide

Complete guide for using Toporia Realtime with Socket.IO Gateway for maximum client compatibility.

## Overview

Socket.IO Gateway is a WebSocket-based transport that provides compatibility with the popular Socket.IO JavaScript library, enabling real-time bidirectional communication with automatic reconnection, room management, and event acknowledgments.

### Why Socket.IO Gateway?

**Advantages**:
- ✅ **Massive Ecosystem**: Works with existing Socket.IO client libraries (JavaScript, Swift, Java, C++, Python)
- ✅ **Automatic Reconnection**: Built-in reconnection with exponential backoff
- ✅ **Fallback Support**: Auto-fallback to HTTP long-polling when WebSocket unavailable
- ✅ **Room Management**: First-class support for rooms/channels
- ✅ **Event Acknowledgments**: Callback support for request-response patterns
- ✅ **Binary Data**: Supports binary events via MessagePack
- ✅ **Namespace Support**: Logical separation via namespaces (/chat, /notifications, etc.)

**Performance**:
- ⚡ Latency: 2-10ms (slightly higher than pure WebSocket due to protocol overhead)
- 📊 Throughput: 80,000+ messages/second
- 👥 Concurrent connections: 8,000+
- 💾 Memory per connection: ~1.5KB

---

## Installation

### Server Requirements

1. **Swoole Extension** (required):
```bash
pecl install swoole
php -m | grep swoole
```

2. **Start Socket.IO Gateway**:
```bash
php console realtime:serve --transport=socketio --port=3000
```

### Client Installation

**Browser (CDN)**:
```html
<script src="https://cdn.socket.io/4.5.4/socket.io.min.js"></script>
<script src="/js/realtime-socketio-client.js"></script>
```

**Node.js/NPM**:
```bash
npm install socket.io-client
```

**React/Vue/Angular**:
```bash
npm install socket.io-client
```

---

## Configuration

### Server Configuration

**config/realtime.php**:
```php
'default_transport' => 'socketio',

'transports' => [
    'socketio' => [
        'driver' => 'socketio',
        'host' => '0.0.0.0',
        'port' => 3000,
        'ssl' => false,

        // Optional SSL/TLS
        'cert' => '/path/to/cert.pem',
        'key' => '/path/to/key.pem',
    ],
],
```

**.env**:
```env
REALTIME_TRANSPORT=socketio
REALTIME_SOCKETIO_HOST=0.0.0.0
REALTIME_SOCKETIO_PORT=3000
REALTIME_SOCKETIO_SSL=false
```

---

## Usage

### Basic Client (JavaScript)

```javascript
// Initialize
const realtime = new ToporiaRealtime('http://localhost:3000');

// Connection events
realtime.on('connected', ({ id }) => {
    console.log('Connected with ID:', id);
});

// Join room
await realtime.join('chat.room.1');

// Listen for events
realtime.on('message.sent', (data) => {
    console.log('Message:', data);
});

// Send event
realtime.emit('message.sent', {
    user: 'John',
    text: 'Hello World!'
});
```

### React Integration

```jsx
import { useEffect, useState } from 'react';

function Chat() {
    const [realtime, setRealtime] = useState(null);
    const [messages, setMessages] = useState([]);

    useEffect(() => {
        const rt = new ToporiaRealtime('http://localhost:3000');

        rt.on('connected', async () => {
            await rt.join('chat.room.1');
        });

        rt.on('message.sent', (data) => {
            setMessages(prev => [...prev, data]);
        });

        setRealtime(rt);

        return () => rt.disconnect();
    }, []);

    const sendMessage = (text) => {
        realtime?.emit('message.sent', {
            user: currentUser.name,
            text: text,
            timestamp: Date.now()
        });
    };

    return (
        <div>
            {messages.map((msg, i) => (
                <div key={i}>{msg.user}: {msg.text}</div>
            ))}
        </div>
    );
}
```

### Vue Integration

```vue
<template>
    <div>
        <div v-for="msg in messages" :key="msg.id">
            {{ msg.user }}: {{ msg.text }}
        </div>
    </div>
</template>

<script>
export default {
    data() {
        return {
            realtime: null,
            messages: []
        };
    },

    async mounted() {
        this.realtime = new ToporiaRealtime('http://localhost:3000');

        this.realtime.on('connected', async () => {
            await this.realtime.join('chat.room.1');
        });

        this.realtime.on('message.sent', (data) => {
            this.messages.push(data);
        });
    },

    beforeUnmount() {
        this.realtime?.disconnect();
    },

    methods: {
        sendMessage(text) {
            this.realtime?.emit('message.sent', {
                user: this.currentUser.name,
                text: text
            });
        }
    }
};
</script>
```

---

## Server-Side Usage

### Broadcasting from PHP

```php
use Toporia\Framework\Support\Accessors\Realtime;

// Broadcast to all clients
Realtime::broadcast('chat.room.1', 'message.sent', [
    'user' => 'Server',
    'text' => 'System announcement',
    'timestamp' => time()
]);

// Send to specific user
Realtime::sendToUser($userId, 'notification.new', [
    'title' => 'New Message',
    'body' => 'You have a new message'
]);
```

### Event Handling in Controllers

```php
class ChatController
{
    public function sendMessage(Request $request)
    {
        $message = Message::create([
            'user_id' => auth()->id(),
            'room_id' => $request->input('room_id'),
            'text' => $request->input('text')
        ]);

        // Broadcast via Socket.IO
        broadcast('chat.room.' . $message->room_id, 'message.sent', [
            'id' => $message->id,
            'user' => auth()->user()->name,
            'text' => $message->text,
            'timestamp' => $message->created_at->timestamp
        ]);

        return response()->json(['success' => true]);
    }
}
```

---

## Advanced Features

### Rooms & Channels

```javascript
// Join multiple rooms
await realtime.join('chat.room.1');
await realtime.join('notifications');
await realtime.join('presence.online');

// Leave room
await realtime.leave('chat.room.1');

// Get joined rooms
const rooms = realtime.getRooms();
console.log('Joined rooms:', rooms);
```

### Event Acknowledgments

```javascript
// Client → Server with acknowledgment
realtime.emit('message.sent', { text: 'Hello' }, (ack) => {
    console.log('Server acknowledged:', ack);
});

// Wait for acknowledgment
const ack = await new Promise((resolve) => {
    realtime.emit('join-room', { room: 'vip.lounge' }, resolve);
});
```

### Typing Indicators

```javascript
let typingTimeout;

function startTyping() {
    realtime.emit('typing.started', {
        user: currentUser.name,
        room: 'chat.room.1'
    });

    clearTimeout(typingTimeout);
    typingTimeout = setTimeout(stopTyping, 3000);
}

function stopTyping() {
    realtime.emit('typing.stopped', {
        user: currentUser.name,
        room: 'chat.room.1'
    });
}

// Listen for typing events
realtime.on('typing.started', (data) => {
    showTypingIndicator(data.user);
});

realtime.on('typing.stopped', (data) => {
    hideTypingIndicator(data.user);
});
```

### Presence Tracking

```javascript
// Join presence channel
await realtime.join('presence.room.1');

// Listen for presence events
realtime.on('user.joined', (data) => {
    updateUserList(data.user_id, 'online');
});

realtime.on('user.left', (data) => {
    updateUserList(data.user_id, 'offline');
});

// Get online users (server-side)
$channel = Realtime::channel('presence.room.1');
$presence = $channel->getPresenceData();
// Returns: [
//     ['user_id' => 1, 'user_info' => [...], 'connected_at' => ...],
//     ['user_id' => 2, 'user_info' => [...], 'connected_at' => ...],
// ]
```

### Namespaces

```javascript
// Connect to different namespaces
const chat = new ToporiaRealtime('http://localhost:3000/chat');
const notifications = new ToporiaRealtime('http://localhost:3000/notifications');
const admin = new ToporiaRealtime('http://localhost:3000/admin');

// Each namespace has isolated events and rooms
chat.on('message', handleChatMessage);
notifications.on('alert', handleNotification);
admin.on('admin-event', handleAdminEvent);
```

---

## Production Deployment

### Nginx Reverse Proxy

```nginx
upstream socketio {
    ip_hash;  # Sticky sessions for Socket.IO
    server 127.0.0.1:3000;
    server 127.0.0.1:3001;  # Multiple workers
    server 127.0.0.1:3002;
}

server {
    listen 443 ssl http2;
    server_name socketio.example.com;

    ssl_certificate /path/to/cert.pem;
    ssl_certificate_key /path/to/key.pem;

    location /socket.io/ {
        proxy_pass http://socketio;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;

        # Timeouts
        proxy_read_timeout 86400;
        proxy_connect_timeout 60;
        proxy_send_timeout 60;

        # Buffering
        proxy_buffering off;
    }
}
```

### Supervisor Configuration

```ini
[program:socketio-gateway]
command=php /path/to/project/console realtime:serve --transport=socketio --port=3000
directory=/path/to/project
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/log/socketio.log
```

### Multi-Server with Redis Broker

**config/realtime.php**:
```php
'default_broker' => 'redis',

'brokers' => [
    'redis' => [
        'driver' => 'redis',
        'host' => '127.0.0.1',
        'port' => 6379,
    ],
],
```

This enables broadcasting across multiple Socket.IO Gateway servers.

---

## Client Libraries

### Official Socket.IO Clients

| Platform | Library | Installation |
|----------|---------|--------------|
| JavaScript | socket.io-client | `npm install socket.io-client` |
| iOS/Swift | socket.io-client-swift | `pod 'Socket.IO-Client-Swift'` |
| Android/Java | socket.io-client-java | `implementation 'io.socket:socket.io-client:2.0.1'` |
| React Native | socket.io-client | `npm install socket.io-client` |
| Python | python-socketio | `pip install python-socketio` |
| Unity/C# | SocketIOClient | NuGet: `SocketIOClient` |

All official Socket.IO v4 clients work with Toporia Realtime Socket.IO Gateway.

---

## Performance Optimization

### Server-Side

```php
// config/realtime.php - Swoole settings
'socketio' => [
    'driver' => 'socketio',
    'host' => '0.0.0.0',
    'port' => 3000,

    // Performance tuning
    'worker_num' => swoole_cpu_num() * 2,
    'max_conn' => 8000,
    'heartbeat_check_interval' => 25,
    'heartbeat_idle_time' => 60,
],
```

### Client-Side

```javascript
// Enable compression
const realtime = new ToporiaRealtime('http://localhost:3000', {
    transports: ['websocket'],  // WebSocket only (fastest)
    upgrade: false,             // Disable transport upgrade
    rememberUpgrade: true,      // Remember successful transport
    timeout: 20000,
    reconnectionDelay: 1000,
    reconnectionDelayMax: 5000,
});

// Batch messages
const queue = [];
setInterval(() => {
    if (queue.length > 0) {
        realtime.emit('batch', queue);
        queue.length = 0;
    }
}, 100);
```

---

## Troubleshooting

### Connection Fails

```javascript
realtime.on('connect_error', (error) => {
    console.error('Connection error:', error);
    // Check server URL, CORS, firewall
});
```

### Reconnection Issues

```javascript
realtime.on('reconnect_failed', () => {
    console.error('Failed to reconnect');
    // Manual reconnection or user notification
    setTimeout(() => realtime.reconnect(), 5000);
});
```

### Room Join Fails

```javascript
realtime.join('private.room').catch((err) => {
    console.error('Failed to join room:', err);
    // Check authorization, room exists
});
```

---

## Migration from Laravel Echo

If migrating from Laravel Echo + Socket.IO:

```javascript
// Laravel Echo
const echo = new Echo({
    broadcaster: 'socket.io',
    host: 'http://localhost:6001'
});

echo.channel('chat').listen('MessageSent', (e) => {
    console.log(e);
});

// Toporia Realtime (equivalent)
const realtime = new ToporiaRealtime('http://localhost:3000');
await realtime.join('chat');
realtime.on('MessageSent', (e) => {
    console.log(e);
});
```

---

## Next Steps

1. **Install Swoole**: `pecl install swoole`
2. **Start Server**: `php console realtime:serve --transport=socketio`
3. **Include Client**: Add Socket.IO client library to your HTML
4. **Test Connection**: Use the [demo chat app](../examples/socketio-chat-demo.html)
5. **Deploy to Production**: Configure Nginx + Supervisor
6. **Add Redis Broker**: For multi-server scaling

See [REALTIME_USAGE_GUIDE.md](REALTIME_USAGE_GUIDE.md) for general realtime system usage.
