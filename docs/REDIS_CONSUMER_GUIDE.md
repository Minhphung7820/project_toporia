# Redis Consumer Guide

## Overview

Redis consumer command for realtime communication system. Consumes messages from Redis Pub/Sub and broadcasts to local clients.

## Usage

### Basic Usage

```bash
# Subscribe to single channel
php console realtime:redis:consume --channels=user.1

# Subscribe to multiple channels
php console realtime:redis:consume --channels=user.1,user.2,public.news

# With options
php console realtime:redis:consume \
  --channels=user.1,user.2 \
  --timeout=1000 \
  --max-messages=1000
```

### Options

| Option | Description | Default |
|--------|-------------|---------|
| `--broker=redis` | Broker name from config | `redis` |
| `--channels=ch1,ch2` | Channels to subscribe (required) | - |
| `--timeout=N` | Poll timeout (ms) - not used for Redis | `1000` |
| `--max-messages=N` | Max messages before exit (0 = unlimited) | `0` |
| `--stop-when-empty` | Stop when no messages (testing) | `false` |

## Architecture

### Flow

```
Server A → broadcast() → Redis Pub/Sub → Server B (Consumer) → broadcastLocal() → Clients
```

### Key Points

1. **Blocking Subscribe**: Redis `subscribe()` is blocking by design (expected behavior)
2. **Event-Driven**: Messages are pushed immediately when published (no polling)
3. **Signal Handling**: Graceful shutdown via SIGTERM/SIGINT
4. **No Loop Prevention**: Uses `broadcastLocal()` to prevent infinite loops

## Performance

- **Latency**: ~0.1ms per message
- **Throughput**: 100k+ messages/sec
- **Memory**: Ephemeral (no persistence)
- **Scalability**: Unlimited subscribers

## Clean Architecture & SOLID

### Single Responsibility
- `RealtimeRedisConsumerCommand`: Only consumes Redis messages
- `RedisBroker`: Only handles Redis Pub/Sub operations
- `AbstractBrokerConsumerCommand`: Base functionality for all brokers

### Open/Closed
- Extensible via broker configuration
- New brokers can extend `AbstractBrokerConsumerCommand`

### Dependency Inversion
- Depends on `BrokerInterface` abstraction
- Not on concrete implementations

### High Reusability
- `AbstractBrokerConsumerCommand` can be used for any broker
- Common functionality shared across all broker consumers

## Examples

### Production Setup

```bash
# Run as daemon with supervisor/systemd
php console realtime:redis:consume --channels=user.*,public.*
```

### Development

```bash
# Test with limited messages
php console realtime:redis:consume --channels=user.1 --max-messages=10
```

## Troubleshooting

### Consumer Not Receiving Messages

1. Check Redis connection: `redis-cli ping`
2. Verify channels are subscribed: Check command output
3. Check if messages are being published: `redis-cli PUBSUB NUMSUB realtime:user.1`

### Graceful Shutdown Not Working

1. Install PCNTL extension: `apt-get install php-pcntl`
2. Check signal handlers: Command should show "Received SIGTERM/SIGINT"

## Comparison with Kafka

| Feature | Redis | Kafka |
|---------|-------|-------|
| Latency | ~0.1ms | ~5ms |
| Throughput | 100k msg/s | 1M+ msg/s |
| Persistence | No | Yes |
| Replay | No | Yes |
| Complexity | Simple | Complex |
| Use Case | Real-time | High-throughput, replay |

