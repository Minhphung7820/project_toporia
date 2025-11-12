# RabbitMQ Dashboard - Hướng dẫn truy cập

## 🚀 Truy cập Dashboard

### URL
```
http://localhost:15672
```

### Default Credentials
- **Username:** `guest`
- **Password:** `guest`

## 📋 Các bước

### 1. Enable Management Plugin (nếu chưa enable)

```bash
sudo rabbitmq-plugins enable rabbitmq_management
```

### 2. Restart RabbitMQ (nếu cần)

```bash
sudo systemctl restart rabbitmq-server
```

### 3. Mở trình duyệt

Truy cập: **http://localhost:15672**

## 🔐 Tạo user mới (Optional)

Nếu muốn tạo user riêng thay vì dùng `guest`:

```bash
# Tạo user
sudo rabbitmqctl add_user admin your_password

# Set quyền administrator
sudo rabbitmqctl set_user_tags admin administrator

# Set permissions
sudo rabbitmqctl set_permissions -p / admin ".*" ".*" ".*"

# Xóa user guest (optional, không khuyến khích)
# sudo rabbitmqctl delete_user guest
```

## 📊 Các tính năng trong Dashboard

### Overview Tab
- Tổng quan về RabbitMQ
- Connection, Channel, Queue statistics
- Message rates

### Connections Tab
- Xem các connections đang active
- Connection details (client, IP, port)

### Channels Tab
- Xem các channels
- Channel details và throughput

### Exchanges Tab
- Xem tất cả exchanges
- Exchange types và bindings

### Queues Tab ⭐ (Quan trọng nhất)
- Xem tất cả queues
- Số messages trong queue
- Message rates
- Consumer count
- Queue details

### Admin Tab
- User management
- Virtual host management
- Policy management

## 🔍 Kiểm tra Queue trong Dashboard

1. Mở http://localhost:15672
2. Login với `guest`/`guest`
3. Vào tab **Queues**
4. Tìm queue `default` (hoặc queue name bạn đã config)
5. Xem:
   - **Ready:** Messages đang chờ xử lý
   - **Unacked:** Messages đang được xử lý
   - **Total:** Tổng số messages

## 🐛 Troubleshooting

### Không truy cập được dashboard

```bash
# Kiểm tra plugin đã enable chưa
sudo rabbitmq-plugins list | grep management

# Enable nếu chưa
sudo rabbitmq-plugins enable rabbitmq_management

# Kiểm tra port 15672
sudo netstat -tuln | grep 15672
# hoặc
sudo ss -tuln | grep 15672

# Restart RabbitMQ
sudo systemctl restart rabbitmq-server
```

### Lỗi "Access denied"

- Kiểm tra username/password
- Default: `guest`/`guest`
- Hoặc tạo user mới với quyền administrator

### Port không listen

```bash
# Kiểm tra RabbitMQ đang chạy
sudo systemctl status rabbitmq-server

# Start nếu chưa chạy
sudo systemctl start rabbitmq-server
```

## 📝 Quick Commands

```bash
# Enable management
sudo rabbitmq-plugins enable rabbitmq_management

# List plugins
sudo rabbitmq-plugins list

# Check status
sudo rabbitmqctl status

# List queues
sudo rabbitmqctl list_queues

# List exchanges
sudo rabbitmqctl list_exchanges

# List connections
sudo rabbitmqctl list_connections
```

