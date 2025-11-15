# Kafka Auto-Reset for Cluster ID Mismatch

## Vấn đề

Khi Zookeeper restart (hoặc bị reset), nó tạo Cluster ID mới. Nhưng Kafka data volume vẫn giữ Cluster ID cũ trong `meta.properties`. Khi Kafka start, nó phát hiện mismatch → crash với `InconsistentClusterIdException` → Connection refused.

## Giải pháp

Entrypoint script tự động phát hiện và reset Kafka data khi cluster ID mismatch:

1. **Entrypoint script** (`entrypoint.sh`):
   - Chờ Zookeeper sẵn sàng
   - Kiểm tra marker file `.cluster_id_mismatch_detected`
   - Nếu marker tồn tại → xóa Kafka data → fresh start
   - Start Kafka bình thường

2. **Detect script** (`detect-mismatch.sh`):
   - Manual script để phát hiện mismatch
   - Tạo marker file để trigger auto-reset

3. **Restart policy**:
   - `unless-stopped`: Tự động restart nếu crash
   - Healthcheck: Monitor Kafka health

## Cách sử dụng

### Tự động (đã cấu hình)

Khi Zookeeper restart:
1. Kafka detect marker file (nếu có) → auto-reset
2. Hoặc Kafka crash với mismatch → manual chạy detect script → restart

### Manual reset nếu cần

```bash
# Option 1: Sử dụng detect script
./docker/kafka/detect-mismatch.sh
docker compose restart kafka

# Option 2: Manual reset
docker compose stop kafka
docker volume rm toporia_kafka_data
docker compose up -d kafka

# Option 3: Reset cả Zookeeper và Kafka
docker compose stop kafka zookeeper
docker volume rm toporia_kafka_data toporia_zookeeper_data
docker compose up -d zookeeper kafka
```

## Files

- `entrypoint.sh`: Auto-reset script (được mount vào container)
- `detect-mismatch.sh`: Manual detection script
- `kafka-wrapper.sh`: Alternative wrapper (unused)

## Lưu ý

- Kafka data sẽ bị xóa khi reset → topics và messages sẽ mất
- Nên backup data quan trọng trước khi reset
- Chỉ reset khi thực sự cần thiết (cluster ID mismatch)

