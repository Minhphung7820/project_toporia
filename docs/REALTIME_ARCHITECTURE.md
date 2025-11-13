# Realtime Architecture - Complete Guide

## Overview

Toporia Framework's Realtime system provides multi-transport, multi-broker realtime communication with clear separation of concerns.

## Core Concepts

### 1. TRANSPORTS (Server <-> Client)

**Purpose:** Communication between server and clients

**Direction:**
- **Bidirectional:** WebSocket (client can send/receive)
- **Server → Client:** SSE, Long-polling (server pushes to client)

**Types:**
- `memory`: In-memory (testing only, single server)
- `websocket`: WebSocket via Swoole/RoadRunner (production, bidirectional)
- `sse`: Server-Sent Events (notifications, server → client)
- `longpolling`: HTTP long-polling (fallback, server → client)
- `socketio`: Socket.IO gateway (compatibility)

**Usage:**
- HTTP requests (for SSE, long-polling)
- WebSocket connections (for WebSocket transport)
- Direct client connections

**Performance:**
- WebSocket: <5ms latency, bidirectional
- SSE: ~10ms latency, server → client only
- Long-polling: ~50ms latency, server → client only

### 2. BROKERS (Server <-> Server)

**Purpose:** Multi-server message distribution

**Direction:** Server-to-server only

**Types:**
- `redis`: Redis Pub/Sub (simple, fast, ephemeral)
- `kafka`: Apache Kafka (high-throughput, persistent, replay)
- `rabbitmq`: RabbitMQ AMQP (durable, routing) - planned
- `nats`: NATS messaging (ultra-fast, clustering) - planned
- `postgres`: PostgreSQL LISTEN/NOTIFY (DB-based) - planned

**Critical Rules:**
1. **PRODUCER (Publishing):** Can be called from ANYWHERE
   - HTTP requests, CLI commands, background jobs, events, scheduled tasks, etc.
   - Via `broadcast()` method (automatically publishes to broker)
   - Like a normal event - call it anywhere you need to broadcast
2. **CONSUMER (Consuming):** ONLY in CLI commands
   - Long-lived processes (e.g., `realtime:kafka:consume`)
   - NEVER consume brokers in HTTP requests (blocks request)

**Why Producer Can Be Called Anywhere?**
- Publishing is fast and non-blocking (O(1) operation)
- Just like firing an event - can be called from anywhere
- HTTP requests, CLI commands, background jobs, event listeners, etc.

**Why Consumer Only in CLI?**
- Brokers require long-lived connections (not suitable for HTTP request lifecycle)
- CLI commands run as daemons, perfect for continuous consumption
- Prevents blocking HTTP requests
- Better resource management

## Architecture Flow

### Single Server (No Broker)

```
HTTP Request → broadcast() → Channel → Transport → Clients
```

### Multi-Server (With Broker)

**Publishing (Can be called from ANYWHERE):**
```
ANYWHERE → broadcast() → [Publish to Broker] + [Broadcast Local]
(HTTP, CLI, Jobs, Events, etc.)
                                    ↓
                              Other Servers
                                    ↓
                            CLI Consumer Commands
```

**Consuming (CLI Command):**
```
CLI Command → consume() → Receive from Broker → broadcastLocal() → Transport → Clients
```

**Important:** `broadcastLocal()` does NOT publish to broker (prevents infinite loop)

## Implementation Details

### RealtimeManager

**Key Methods:**

1. **`broadcast($channel, $event, $data)`**
   - Broadcasts locally (always)
   - Publishes to broker (if available)
   - Can be called from ANYWHERE: HTTP, CLI, jobs, events, etc.
   - Producer (publish) is available everywhere - like a normal event

2. **`broadcastLocal($channel, $event, $data)`**
   - Broadcasts locally only
   - Does NOT publish to broker
   - Used in CLI consumer commands

3. **`broker($name)`**
   - Returns broker instance
   - Can be called in HTTP (for publishing)
   - Should NOT be consumed in HTTP (use CLI only)

### Consumer Commands

**Example: `RealtimeKafkaConsumerCommand`**

```php
// CLI Command (runs as daemon)
php console realtime:kafka:consume --channels=user.1,public.news

// Flow:
1. Subscribe to Kafka topics
2. Consume messages in batches
3. For each message: broadcastLocal() (NOT broadcast())
4. Clients on this server receive message
```

## Performance Optimizations

### 1. Topic Strategy (Kafka)

**Problem:** One topic per channel creates too many topics

**Solution:** Grouped topic strategy
- Groups channels into fewer topics
- Uses partitioning for load distribution
- Uses message keys for consistent partitioning

**Before:**
- 1000 channels = 1000 topics
- Throughput: ~10K msg/s

**After:**
- 1000 channels = 4 topics (grouped)
- Throughput: ~100K msg/s (10x improvement)

### 2. Batch Processing

- Consume messages in batches (configurable size)
- Process batch together for better performance
- Reduces network round-trips

### 3. Manual Commit (Kafka)

- Optional manual commit for better reliability
- Messages only committed after successful processing
- Prevents message loss on errors

### 4. Topic Caching

- Reuse topic instances (O(1) lookup)
- Reduces object creation overhead

## Clean Architecture & SOLID

### Single Responsibility
- `RealtimeManager`: Coordinates only
- `Transport`: Client communication only
- `Broker`: Server communication only
- `Channel`: Channel management only

### Open/Closed
- Extensible via new transports/brokers
- Factory pattern for creation
- Interface-based design

### Liskov Substitution
- All transports implement `TransportInterface`
- All brokers implement `BrokerInterface`
- All channels implement `ChannelInterface`

### Interface Segregation
- Separate interfaces for each concern
- No fat interfaces

### Dependency Inversion
- Depends on abstractions (interfaces)
- Not on concrete implementations

## Best Practices

### 1. Transport Selection

- **Production:** WebSocket (best latency, bidirectional)
- **Notifications:** SSE (server → client, simple)
- **Legacy Browsers:** Long-polling (fallback)
- **Testing:** Memory (single server only)

### 2. Broker Selection

- **Simple Setup:** Redis Pub/Sub
- **High Throughput:** Kafka
- **Durability:** RabbitMQ (when implemented)
- **Ultra-Fast:** NATS (when implemented)

### 3. Channel Naming

- **Public:** `news`, `announcements`
- **Private:** `private-chat.123`, `user.456`
- **Presence:** `presence-room.1`

### 4. Multi-Server Setup

1. Configure broker in `config/realtime.php`
2. Run consumer command on each server:
   ```bash
   php console realtime:kafka:consume --channels=user.*,public.*
   ```
3. Use `broadcast()` ANYWHERE (HTTP, CLI, jobs, events, etc.)
   - Automatically publishes to broker
   - Like a normal event - call it anywhere you need
4. Consumer commands receive and broadcast locally

**Examples of where to call `broadcast()`:**
```php
// HTTP Controller
$realtime->broadcast('user.1', 'message', $data);

// CLI Command
$realtime->broadcast('user.1', 'message', $data);

// Background Job
$realtime->broadcast('user.1', 'message', $data);

// Event Listener
$realtime->broadcast('user.1', 'message', $data);

// Scheduled Task
$realtime->broadcast('user.1', 'message', $data);
```

## Common Pitfalls

### ❌ Wrong: Consuming Broker in HTTP

```php
// DON'T DO THIS in HTTP controllers!
$broker = $realtime->broker();
$broker->consume(); // Blocks HTTP request!
```

### ✅ Correct: Consume in CLI Only

```bash
# Run as daemon
php console realtime:kafka:consume --channels=user.1
```

### ❌ Wrong: Using broadcast() in Consumer

```php
// In consumer command - WRONG!
$this->realtime->broadcast($channel, $event, $data);
// This will publish to broker again → infinite loop!
```

### ✅ Correct: Use broadcastLocal() in Consumer

```php
// In consumer command - CORRECT!
$this->realtime->broadcastLocal($channel, $event, $data);
// Only broadcasts locally, no broker publish
```

## Monitoring & Debugging

### Check Broker Status

```php
$broker = $realtime->broker();
if ($broker && $broker->isConnected()) {
    // Broker is ready
}
```

### Monitor Channels

```php
$channel = $realtime->channel('user.1');
$subscriberCount = $channel->getSubscriberCount();
```

### Check Connections

```php
$connectionCount = $realtime->getConnectionCount();
$userConnections = $realtime->getUserConnections($userId);
```

## Summary

- **Transports:** Server <-> Client (HTTP, WebSocket)
- **Brokers:** Server <-> Server (CLI commands only for consumption)
- **broadcast():** Publishes to broker + broadcasts local
- **broadcastLocal():** Broadcasts local only (for consumers)
- **Performance:** Batch processing, topic strategy, manual commit
- **Architecture:** Clean, SOLID, extensible

