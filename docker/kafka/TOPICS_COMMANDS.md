# Lệnh tạo Kafka Topics cho các sự kiện

## ⚠️ Lưu ý quan trọng

Trong Confluent Kafka container, sử dụng `/usr/bin/kafka-topics` (KHÔNG có `.sh`):
- ❌ SAI: `kafka-topics.sh`
- ✅ ĐÚNG: `/usr/bin/kafka-topics`

## Lệnh tạo topic `orders.events` (đã có)

```bash
docker exec project_topo_kafka /usr/bin/kafka-topics \
  --bootstrap-server localhost:29092 \
  --create \
  --topic orders.events \
  --partitions 10 \
  --replication-factor 1 \
  --if-not-exists
```

## Lệnh tạo các topic khác

### 1. Topic cho order events (ví dụ: order.events)
```bash
docker exec project_topo_kafka /usr/bin/kafka-topics \
  --bootstrap-server localhost:29092 \
  --create \
  --topic order.events \
  --partitions 3 \
  --replication-factor 1 \
  --if-not-exists
```

### 2. Topic cho user events
```bash
docker exec project_topo_kafka /usr/bin/kafka-topics \
  --bootstrap-server localhost:29092 \
  --create \
  --topic realtime.user \
  --partitions 10 \
  --replication-factor 1 \
  --if-not-exists
```

### 3. Topic cho public events
```bash
docker exec project_topo_kafka /usr/bin/kafka-topics \
  --bootstrap-server localhost:29092 \
  --create \
  --topic realtime.public \
  --partitions 3 \
  --replication-factor 1 \
  --if-not-exists
```

### 4. Topic cho presence events
```bash
docker exec project_topo_kafka /usr/bin/kafka-topics \
  --bootstrap-server localhost:29092 \
  --create \
  --topic realtime.presence \
  --partitions 5 \
  --replication-factor 1 \
  --if-not-exists
```

### 5. Topic cho chat events
```bash
docker exec project_topo_kafka /usr/bin/kafka-topics \
  --bootstrap-server localhost:29092 \
  --create \
  --topic realtime.chat \
  --partitions 10 \
  --replication-factor 1 \
  --if-not-exists
```

### 6. Topic mặc định (default)
```bash
docker exec project_topo_kafka /usr/bin/kafka-topics \
  --bootstrap-server localhost:29092 \
  --create \
  --topic realtime \
  --partitions 10 \
  --replication-factor 1 \
  --if-not-exists
```

## Tạo tất cả topics cùng lúc

Sử dụng script:
```bash
./docker/kafka/create-topics.sh
```

Hoặc chạy tất cả lệnh:
```bash
# Tạo tất cả topics
docker exec project_topo_kafka /usr/bin/kafka-topics --bootstrap-server localhost:29092 --create --topic orders.events --partitions 10 --replication-factor 1 --if-not-exists
docker exec project_topo_kafka /usr/bin/kafka-topics --bootstrap-server localhost:29092 --create --topic realtime.user --partitions 10 --replication-factor 1 --if-not-exists
docker exec project_topo_kafka /usr/bin/kafka-topics --bootstrap-server localhost:29092 --create --topic realtime.public --partitions 3 --replication-factor 1 --if-not-exists
docker exec project_topo_kafka /usr/bin/kafka-topics --bootstrap-server localhost:29092 --create --topic realtime.presence --partitions 5 --replication-factor 1 --if-not-exists
docker exec project_topo_kafka /usr/bin/kafka-topics --bootstrap-server localhost:29092 --create --topic realtime.chat --partitions 10 --replication-factor 1 --if-not-exists
docker exec project_topo_kafka /usr/bin/kafka-topics --bootstrap-server localhost:29092 --create --topic realtime --partitions 10 --replication-factor 1 --if-not-exists
```

## Kiểm tra topics đã tạo

```bash
# Liệt kê tất cả topics
docker exec project_topo_kafka /usr/bin/kafka-topics \
  --bootstrap-server localhost:29092 \
  --list

# Xem chi tiết một topic
docker exec project_topo_kafka /usr/bin/kafka-topics \
  --bootstrap-server localhost:29092 \
  --describe \
  --topic orders.events
```

## Lưu ý

- Đảm bảo Kafka container đang chạy trước khi tạo topics
- `--if-not-exists` sẽ không báo lỗi nếu topic đã tồn tại
- Số partitions có thể tăng sau (không thể giảm)
- Replication factor = 1 phù hợp cho development (single broker)

