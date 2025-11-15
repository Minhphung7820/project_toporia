# Kafka Topic Management Command

## Overview

Professional Kafka topic management command với Clean Architecture, SOLID principles, và high reusability. Tự động fix các lỗi thường gặp như cluster ID mismatch và connection issues.

## Architecture

```
┌─────────────────────────────────────────┐
│   KafkaTopicManagerCommand              │  ← Command (Orchestration)
│   - Orchestrates operations             │
│   - User interface                      │
└──────────────┬──────────────────────────┘
               │
               ├─→ KafkaTopicService       ← Service (Business Logic)
               │   - Topic creation        │
               │   - Health checking       │
               │   - Metadata parsing      │
               │
               ├─→ KafkaHealthChecker      ← Health Check
               │   - Connection check      │
               │   - API version check     │
               │   - Security protocol     │
               │
               └─→ KafkaClusterIdFixer     ← Auto-recovery
                   - Detect mismatch       │
                   - Auto-fix cluster ID   │
```

## SOLID Principles

### Single Responsibility
- **KafkaTopicService**: Chỉ quản lý topics
- **KafkaHealthChecker**: Chỉ check health
- **KafkaClusterIdFixer**: Chỉ fix cluster ID
- **KafkaTopicManagerCommand**: Chỉ orchestration

### Open/Closed
- Extensible via services
- Có thể thêm health check mới mà không sửa code cũ

### Liskov Substitution
- Tất cả services implement interfaces tương lai

### Interface Segregation
- Separate concerns: Health check, Topic management, Cluster fix

### Dependency Inversion
- Command depends on abstractions (services)
- Services có thể inject dependencies

## Usage

### 1. Health Check

```bash
# Check Kafka health
php console kafka:topics health-check
```

**Output:**
```
=== Kafka Health Check ===

✅ Connection: OK
✅ API Version: OK
✅ Security Protocol: OK

✅ Kafka is healthy and ready
```

### 2. Fix Cluster ID Mismatch

```bash
# Auto-fix cluster ID mismatch
php console kafka:topics fix-cluster-id
```

**Features:**
- Tự động detect cluster ID mismatch
- Stop Kafka container
- Xóa data volume
- Restart với cluster ID mới
- Đợi Kafka ready

### 3. Create Single Topic

```bash
# Create topic with specific partitions
php console kafka:topics create --topic=orders.events --partitions=10

# With replication factor
php console kafka:topics create --topic=orders.events --partitions=10 --replication-factor=1
```

### 4. Create All Topics from Config

```bash
# Create all topics from config/kafka.php
php console kafka:topics create --from-config

# Or
php console kafka:topics create --all
```

**Topics created:**
- `orders.events` (10 partitions)
- `realtime.user` (10 partitions)
- `realtime.public` (3 partitions)
- `realtime.presence` (5 partitions)
- `realtime.chat` (10 partitions)
- `realtime` (10 partitions - default)

### 5. List Topics

```bash
# List all topics
php console kafka:topics list
```

### 6. Describe Topic

```bash
# Get topic details
php console kafka:topics describe --topic=orders.events
```

## Auto-Fix Features

### Cluster ID Mismatch Auto-Fix

Khi Zookeeper restart → Cluster ID mới, nhưng Kafka data volume giữ Cluster ID cũ:

1. **Detection**: `KafkaClusterIdFixer` checks:
   - Kafka logs for `InconsistentClusterIdException`
   - Marker file `.cluster_id_mismatch_detected`

2. **Auto-Fix**:
   - Stop Kafka container
   - Remove Kafka data volume
   - Restart Kafka (tạo volume mới với Cluster ID mới)

3. **Verification**:
   - Wait for Kafka ready
   - Check API version compatibility

### Connection Issues Auto-Fix

1. **Health Check**:
   - Verify connection
   - Check API version
   - Validate security protocol

2. **Auto-Recovery**:
   - Fix cluster ID if needed
   - Ensure Kafka is ready before operations

## Performance Optimizations

1. **Lazy Initialization**:
   - Services chỉ được tạo khi cần
   - Giảm memory overhead

2. **Batch Operations**:
   - Create multiple topics cùng lúc
   - Giảm network round-trips

3. **Connection Pooling**:
   - Reuse connections khi có thể
   - Cache broker instances

4. **Parallel Health Checks**:
   - Check multiple health indicators
   - Fast failure detection

## Configuration

### Environment Variables

```env
# Kafka Bootstrap Server
KAFKA_BOOTSTRAP_SERVER=localhost:29092

# Kafka Container Name
KAFKA_CONTAINER=project_topo_kafka
```

### Config File (`config/kafka.php`)

```php
'bootstrap_server' => env('KAFKA_BOOTSTRAP_SERVER', 'localhost:29092'),
'kafka_container' => env('KAFKA_CONTAINER', 'project_topo_kafka'),

'topic_mapping' => [
    'orders.*' => [
        'topic' => 'orders.events',
        'partitions' => 10,
    ],
    // ...
],
```

## Error Handling

### Common Errors

1. **Cluster ID Mismatch**:
   ```bash
   php console kafka:topics fix-cluster-id
   ```

2. **Connection Refused**:
   ```bash
   # Check health first
   php console kafka:topics health-check

   # Then fix if needed
   php console kafka:topics fix-cluster-id
   ```

3. **API Version Error**:
   - Auto-checked by health checker
   - Ensures `security.protocol=plaintext` is set

## Examples

### Complete Workflow

```bash
# 1. Check health
php console kafka:topics health-check

# 2. Fix issues if needed
php console kafka:topics fix-cluster-id

# 3. Create all topics
php console kafka:topics create --from-config

# 4. Verify topics
php console kafka:topics list

# 5. Check topic details
php console kafka:topics describe --topic=orders.events
```

## Best Practices

1. **Always check health before operations**
2. **Use `--from-config` để create topics consistently**
3. **Run `fix-cluster-id` khi Zookeeper restart**
4. **Monitor logs khi có issues**

## Troubleshooting

### Kafka not accessible
```bash
php console kafka:topics health-check
```

### Cluster ID mismatch
```bash
php console kafka:topics fix-cluster-id
```

### Topic creation fails
1. Check health: `php console kafka:topics health-check`
2. Verify Kafka is running: `docker ps | grep kafka`
3. Check logs: `docker logs project_topo_kafka`

