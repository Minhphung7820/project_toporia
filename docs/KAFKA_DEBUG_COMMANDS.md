# Kafka Debug Commands

## Khi produce message nhưng chưa thấy log

### 1. Kiểm tra Consumer có đang chạy

```bash
# Check consumer process
ps aux | grep "order:tracking:consume"

# Hoặc check trong terminal đang chạy consumer
# Consumer phải đang polling messages
```

### 2. Kiểm tra Topic có tồn tại và có message

```bash
# List topics
php console kafka:topics list

# Describe topic để xem partitions
php console kafka:topics describe --topic=orders.events

# Kiểm tra messages trong topic (Docker command)
docker exec project_topo_kafka /usr/bin/kafka-console-consumer \
  --bootstrap-server localhost:29092 \
  --topic orders.events \
  --from-beginning \
  --max-messages 10
```

### 3. Xem Log Files

```bash
# Xem log file PHP
tail -f storage/logs/$(date +%Y-%m-%d).log

# Hoặc xem tất cả logs
tail -f storage/logs/*.log

# Xem PHP error log
tail -f /var/log/php-fpm/error.log

# Hoặc nếu dùng Docker
docker logs -f project_topo_php 2>&1 | grep -i kafka
```

### 4. Kiểm tra Kafka Logs

```bash
# Kafka broker logs
docker logs project_topo_kafka --tail 50

# Zookeeper logs
docker logs project_topo_zookeeper --tail 50

# Check consumer group status
docker exec project_topo_kafka /usr/bin/kafka-consumer-groups \
  --bootstrap-server localhost:29092 \
  --group order-tracking-consumers \
  --describe
```

### 5. Health Check

```bash
# Kiểm tra Kafka health
php console kafka:topics health-check

# Kiểm tra connection
docker exec project_topo_kafka /usr/bin/kafka-broker-api-versions \
  --bootstrap-server localhost:29092
```

### 6. Test Producer và Consumer

```bash
# Test producer (tạo message thủ công)
docker exec project_topo_kafka /usr/bin/kafka-console-producer \
  --bootstrap-server localhost:29092 \
  --topic orders.events

# Sau đó gõ message và nhấn Enter
# Ví dụ: {"event":"order.created","order_id":"test123"}

# Test consumer (đọc message)
docker exec project_topo_kafka /usr/bin/kafka-console-consumer \
  --bootstrap-server localhost:29092 \
  --topic orders.events \
  --from-beginning
```

### 7. Kiểm tra Consumer Group Offset

```bash
# Xem offset của consumer group
docker exec project_topo_kafka /usr/bin/kafka-consumer-groups \
  --bootstrap-server localhost:29092 \
  --group order-tracking-consumers \
  --describe

# Reset offset về earliest (nếu cần)
docker exec project_topo_kafka /usr/bin/kafka-consumer-groups \
  --bootstrap-server localhost:29092 \
  --group order-tracking-consumers \
  --reset-offsets \
  --to-earliest \
  --topic orders.events \
  --execute
```

### 8. Debug Consumer trong Terminal

```bash
# Chạy consumer với verbose output
php console order:tracking:consume --verbose

# Hoặc check realtime logs trong terminal consumer
# Consumer sẽ log mỗi message nhận được
```

### 9. Kiểm tra Network Connectivity

```bash
# Test connection từ host
nc -zv localhost 9092

# Test từ container
docker exec project_topo_php nc -zv kafka 29092
```

### 10. Xem Messages trong Topic (Real-time)

```bash
# Monitor topic messages
docker exec -it project_topo_kafka /usr/bin/kafka-console-consumer \
  --bootstrap-server localhost:29092 \
  --topic orders.events \
  --from-beginning \
  --property print.timestamp=true \
  --property print.key=true \
  --property print.value=true
```

## Troubleshooting Checklist

1. ✅ Consumer đang chạy?
   ```bash
   ps aux | grep "order:tracking:consume"
   ```

2. ✅ Topic tồn tại?
   ```bash
   php console kafka:topics list
   ```

3. ✅ Messages có trong topic?
   ```bash
   docker exec project_topo_kafka /usr/bin/kafka-console-consumer \
     --bootstrap-server localhost:29092 \
     --topic orders.events \
     --from-beginning \
     --max-messages 1
   ```

4. ✅ Consumer group offset đang ở đâu?
   ```bash
   docker exec project_topo_kafka /usr/bin/kafka-consumer-groups \
     --bootstrap-server localhost:29092 \
     --group order-tracking-consumers \
     --describe
   ```

5. ✅ Kafka health OK?
   ```bash
   php console kafka:topics health-check
   ```

## Common Issues

### Issue 1: Consumer đã consume message nhưng không log

**Solution:**
- Check log file path: `storage/logs/$(date +%Y-%m-%d).log`
- Check PHP error log
- Check consumer terminal output

### Issue 2: Consumer không nhận message

**Solution:**
- Check consumer group offset: có thể đã consume rồi
- Reset offset về earliest
- Check consumer có đang subscribe đúng topic

### Issue 3: Producer không publish được

**Solution:**
- Check Kafka health: `php console kafka:topics health-check`
- Check topic tồn tại: `php console kafka:topics list`
- Check Kafka logs: `docker logs project_topo_kafka`

## Quick Debug Script

```bash
#!/bin/bash
# Quick Kafka debug

echo "=== Kafka Health ==="
php console kafka:topics health-check

echo ""
echo "=== Topics ==="
php console kafka:topics list

echo ""
echo "=== Consumer Groups ==="
docker exec project_topo_kafka /usr/bin/kafka-consumer-groups \
  --bootstrap-server localhost:29092 \
  --list

echo ""
echo "=== Last 10 Kafka Logs ==="
docker logs project_topo_kafka --tail 10
```

