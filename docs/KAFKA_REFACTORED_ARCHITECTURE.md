# Kafka Consumer Architecture - Refactored

## Overview

The Kafka consumer system has been refactored following Clean Architecture principles and SOLID design patterns, inspired by professional Laravel Kafka implementations. The new architecture provides:

- **High Reusability**: Base classes and interfaces for easy extension
- **Clean Architecture**: Strict separation of concerns
- **SOLID Principles**: Every component follows SOLID principles
- **Performance Optimized**: Batch processing, DLQ support, efficient error handling
- **Multiple Formats**: Support for JSON and Avro message formats
- **Dead Letter Queue**: Automatic retry and DLQ handling for failed messages

## Architecture

```
┌─────────────────────────────────────────────────────────┐
│              Consumer Interfaces                        │
│  SingleMessageHandlerInterface                          │
│  BatchingMessagesHandlerInterface                       │
└─────────────────────────────────────────────────────────┘
                           ↓
┌─────────────────────────────────────────────────────────┐
│              Abstract Base Classes                      │
│  AbstractKafkaConsumer                                  │
│    ├── AbstractJsonKafkaConsumer                        │
│    │   └── AbstractSingleAvroKafkaConsumer             │
│    └── AbstractBatchKafkaConsumer                       │
│        └── AbstractBatchAvroKafkaConsumer               │
└─────────────────────────────────────────────────────────┘
                           ↓
┌─────────────────────────────────────────────────────────┐
│              Concrete Implementations                   │
│  RealtimeKafkaConsumerCommand                           │
│  ExampleJsonConsumer                                    │
│  ExampleBatchConsumer                                   │
└─────────────────────────────────────────────────────────┘
```

## Base Classes

### 1. AbstractKafkaConsumer

Base class for all Kafka consumers with common functionality:

- Configuration management
- Broker access
- Signal handling for graceful shutdown
- Performance tracking
- Error logging

**Key Methods:**
- `getBroker()` - Get Kafka broker instance
- `getBrokers()` - Get broker list
- `setupSignalHandlers()` - Setup graceful shutdown
- `displayHeader()` - Display consumer information
- `displaySummary()` - Display performance summary

### 2. AbstractJsonKafkaConsumer

Base class for JSON message consumers:

- JSON deserialization
- Single message processing
- Error handling with DLQ support

**Usage:**
```php
class MyJsonConsumer extends AbstractJsonKafkaConsumer
{
    protected function getTopic(): string { return 'my-topic'; }
    protected function getGroupId(): string { return 'my-group'; }
    protected function getOffset(): string { return 'earliest'; }

    public function handleMessage(MessageInterface $message, array $metadata = []): void
    {
        // Process message
    }
}
```

### 3. AbstractBatchKafkaConsumer

Base class for batch message consumers:

- Batch processing with configurable size and interval
- High throughput processing
- Atomic batch operations

**Usage:**
```php
class MyBatchConsumer extends AbstractBatchKafkaConsumer
{
    protected function getTopic(): string { return 'my-topic'; }
    protected function getGroupId(): string { return 'my-group'; }
    protected function getOffset(): string { return 'earliest'; }
    protected function getBatchSizeLimit(): int { return 100; }
    protected function getBatchReleaseInterval(): int { return 1500; }

    public function handleMessages(Collection $messages): void
    {
        // Process batch
    }
}
```

### 4. AbstractAvroKafkaConsumer

Base class for Avro message consumers:

- Avro deserialization with Schema Registry
- Schema caching for performance
- Fallback to JSON if Avro not available

### 5. AbstractSingleAvroKafkaConsumer

Single Avro message consumer (extends AbstractAvroKafkaConsumer).

### 6. AbstractBatchAvroKafkaConsumer

Batch Avro message consumer (extends AbstractAvroKafkaConsumer).

## Dead Letter Queue (DLQ)

The DLQ system provides automatic retry and error handling:

- **Automatic Retries**: Configurable retry attempts with exponential backoff
- **DLQ Publishing**: Failed messages sent to DLQ topic after max retries
- **Error Tracking**: Comprehensive error logging with context

**Usage:**
```php
// Enable DLQ in consumer
php console realtime:kafka:consume --dlq-enabled

// Configure in config/kafka.php
'dlq_topic_prefix' => 'dlq',
'dlq_max_retries' => 3,
'dlq_enabled' => true,
```

## Configuration

### config/kafka.php

```php
return [
    'offset_reset' => 'earliest',
    'schema_registry' => [
        'uri' => 'http://localhost:8081',
        'cache_enabled' => true,
    ],
    'batch_release_interval' => 1500, // milliseconds
    'dlq_topic_prefix' => 'dlq',
    'dlq_max_retries' => 3,
    'topics' => [
        'json' => 'realtime-json',
        'avro' => 'realtime-avro',
    ],
];
```

## Examples

### Example 1: Simple JSON Consumer

```php
use Toporia\Framework\Console\Commands\Kafka\Base\AbstractJsonKafkaConsumer;

class UserNotificationConsumer extends AbstractJsonKafkaConsumer
{
    protected function getTopic(): string { return 'user-notifications'; }
    protected function getGroupId(): string { return 'notification-consumers'; }
    protected function getOffset(): string { return 'latest'; }

    public function handleMessage(MessageInterface $message, array $metadata = []): void
    {
        $data = $message->getData();
        // Send notification to user
        NotificationService::send($data['user_id'], $data['message']);
    }
}
```

### Example 2: Batch Consumer

```php
use Toporia\Framework\Console\Commands\Kafka\Base\AbstractBatchKafkaConsumer;

class AnalyticsBatchConsumer extends AbstractBatchKafkaConsumer
{
    protected function getTopic(): string { return 'analytics-events'; }
    protected function getGroupId(): string { return 'analytics-consumers'; }
    protected function getOffset(): string { return 'earliest'; }
    protected function getBatchSizeLimit(): int { return 500; }
    protected function getBatchReleaseInterval(): int { return 2000; }

    public function handleMessages(Collection $messages): void
    {
        // Bulk insert to database
        $events = $messages->map(fn($item) => $item['message']->getData())->toArray();
        AnalyticsEvent::insert($events);
    }
}
```

## Performance Optimizations

1. **Batch Processing**: Process messages in batches to reduce overhead
2. **Schema Caching**: Cache Avro schemas to reduce network calls
3. **Non-blocking Polls**: Use timeouts to prevent CPU spinning
4. **Graceful Shutdown**: Clean shutdown with signal handling
5. **Error Handling**: Efficient error handling with DLQ support

## SOLID Principles

- **Single Responsibility**: Each class has one clear purpose
- **Open/Closed**: Extensible via inheritance, closed for modification
- **Liskov Substitution**: Interfaces ensure substitutability
- **Interface Segregation**: Small, focused interfaces
- **Dependency Inversion**: Depend on abstractions, not concretions

## Migration Guide

### Old Way (Before Refactor)

```php
class MyConsumer extends Command
{
    public function handle()
    {
        $broker = $this->realtime->broker('kafka');
        // Manual setup, error handling, etc.
    }
}
```

### New Way (After Refactor)

```php
class MyConsumer extends AbstractJsonKafkaConsumer
{
    protected function getTopic(): string { return 'my-topic'; }
    protected function getGroupId(): string { return 'my-group'; }
    protected function getOffset(): string { return 'earliest'; }

    public function handleMessage(MessageInterface $message, array $metadata = []): void
    {
        // Just focus on business logic
    }
}
```

## Benefits

1. **Less Code**: Base classes handle common functionality
2. **Consistency**: All consumers follow the same pattern
3. **Error Handling**: Built-in DLQ and retry logic
4. **Performance**: Optimized batch processing
5. **Maintainability**: Easy to extend and modify
6. **Testing**: Easy to test with clear interfaces

## References

- Inspired by: [Viblo Article on Kafka Consumers](https://viblo.asia/p/xay-dung-kafka-consumer-trong-laravel-tu-json-avro-den-batch-processing-va-dlq-zXRJ8PPqJGq)
- Clean Architecture principles
- SOLID design patterns

