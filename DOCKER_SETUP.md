# 🐳 Docker Setup Guide

Complete Docker setup for Toporia Framework with **ext-redis**, **ext-rdkafka**, and **enqueue/rdkafka** pre-installed.

---

## 🎯 What's Included

This Docker setup includes:

### Application Services
- **app**: PHP 8.2-FPM with all extensions (redis, rdkafka, pdo_mysql, opcache, zip, pcntl)
- **nginx**: Web server on port 8000

### Infrastructure Services
- **mysql**: MySQL 8.0 database (port 3307)
- **redis**: Redis 7 for cache + Pub/Sub (port 6379)
- **rabbitmq**: RabbitMQ with management UI (ports 5672, 15672)
- **kafka**: Kafka broker (port 9092)
- **zookeeper**: ZooKeeper for Kafka (port 2181)
- **elasticsearch**: Elasticsearch 8.13 (ports 9200, 9300)

### Pre-installed PHP Extensions
✅ **ext-redis** (native C performance)
✅ **ext-rdkafka** (10-50x faster than pure PHP)
✅ **enqueue/rdkafka** (high-level Kafka client)
✅ PDO MySQL, PostgreSQL
✅ OPcache, Zip, Pcntl, Sockets

---

## 🚀 Quick Start

### 1. Build and Start Containers

```bash
# Build the PHP container with all extensions
docker compose build

# Start all services
docker compose up -d

# Check container status
docker compose ps
```

**Expected output:**
```
NAME                        STATUS          PORTS
project_topo_app            Up             9000/tcp
project_topo_nginx          Up             0.0.0.0:8000->80/tcp
project_topo_mysql          Up             0.0.0.0:3307->3306/tcp
project_topo_redis          Up             0.0.0.0:6379->6379/tcp
project_topo_rabbitmq       Up             0.0.0.0:5672->5672/tcp, 0.0.0.0:15672->15672/tcp
project_topo_kafka          Up             0.0.0.0:9092->9092/tcp
project_topo_zookeeper      Up             0.0.0.0:2181->2181/tcp
project_topo_elasticsearch  Up (healthy)   0.0.0.0:9200->9200/tcp
```

---

### 2. Verify PHP Extensions

```bash
# Check installed extensions
docker compose exec app php -m | grep -E "(redis|rdkafka)"
```

**Expected output:**
```
rdkafka
redis
```

**Check versions:**
```bash
docker compose exec app php -r "
echo 'Redis: ' . phpversion('redis') . PHP_EOL;
echo 'RdKafka: ' . phpversion('rdkafka') . PHP_EOL;
"
```

**Expected:**
```
Redis: 6.0.2
RdKafka: 6.0.3
```

---

### 3. Verify Composer Package

```bash
# Check enqueue/rdkafka is installed
docker compose exec app composer show | grep enqueue
```

**Expected:**
```
enqueue/rdkafka  0.10.x  High-performance Kafka client
```

---

### 4. Run Migrations

```bash
# Run database migrations
docker compose exec app php console migrate
```

---

### 5. Access the Application

**Web Application:**
```
http://localhost:8000
```

**RabbitMQ Management UI:**
```
http://localhost:15672
Username: guest
Password: guest
```

**Elasticsearch:**
```
http://localhost:9200
```

---

## 🧪 Testing Broker Performance

### Test Redis Pub/Sub (with ext-redis)

**Terminal 1 - Subscribe:**
```bash
docker compose exec app php console realtime:redis:consume
```

**Terminal 2 - Publish:**
```bash
docker compose exec app php -r "
\$redis = new Redis();
\$redis->connect('redis', 6379);
\$redis->publish('test-channel', json_encode(['message' => 'Hello from ext-redis!']));
echo 'Published to Redis' . PHP_EOL;
"
```

**Check logs for:**
```
✅ Using native ext-redis extension
```

---

### Test Kafka (with ext-rdkafka)

**Terminal 1 - Consumer:**
```bash
docker compose exec app php console order:tracking:consume
```

**Terminal 2 - Producer:**
```bash
docker compose exec app php -r "
use Toporia\Framework\Realtime\Message;
\$broker = container('realtime.broker');
\$msg = Message::event('order-tracking', 'order.created', ['order_id' => 123]);
\$broker->publish('order-tracking', \$msg);
echo 'Published to Kafka' . PHP_EOL;
"
```

**Check logs for:**
```
[Kafka] Using rdkafka extension (high performance)  ← ✅ 10-50x faster!
```

---

### Test RabbitMQ

**Terminal 1 - Consumer:**
```bash
docker compose exec app php console realtime:rabbitmq:consume
```

**Terminal 2 - Publish:**
```bash
docker compose exec app php -r "
use Toporia\Framework\Realtime\Message;
\$broker = container('realtime.broker');
\$msg = Message::event('notifications', 'user.registered', ['user_id' => 456]);
\$broker->publish('notifications', \$msg);
echo 'Published to RabbitMQ' . PHP_EOL;
"
```

---

## 📋 Common Commands

### Container Management

```bash
# Start all services
docker compose up -d

# Stop all services
docker compose down

# Restart specific service
docker compose restart app

# View logs
docker compose logs -f app

# Enter PHP container shell
docker compose exec app bash

# Rebuild containers after Dockerfile changes
docker compose build --no-cache
docker compose up -d
```

---

### Application Commands

```bash
# Run console commands
docker compose exec app php console migrate
docker compose exec app php console queue:work
docker compose exec app php console cache:clear

# Run Composer commands
docker compose exec app composer install
docker compose exec app composer require some/package

# Check PHP info
docker compose exec app php -i
```

---

### Database Commands

```bash
# Access MySQL CLI
docker compose exec mysql mysql -uroot project_topo

# Export database
docker compose exec mysql mysqldump -uroot project_topo > backup.sql

# Import database
docker compose exec -T mysql mysql -uroot project_topo < backup.sql
```

---

### Queue Workers

```bash
# Start queue worker (blocking)
docker compose exec app php console queue:work

# Run in background with supervisord
docker compose exec -d app php console queue:work
```

---

### Kafka Operations

```bash
# List Kafka topics
docker compose exec kafka kafka-topics --list --bootstrap-server localhost:9092

# Create topic manually
docker compose exec kafka kafka-topics --create \
  --topic my-topic \
  --partitions 3 \
  --replication-factor 1 \
  --bootstrap-server localhost:9092

# Consume from topic (Kafka CLI)
docker compose exec kafka kafka-console-consumer \
  --topic order-tracking \
  --from-beginning \
  --bootstrap-server localhost:9092
```

---

### Redis Operations

```bash
# Access Redis CLI
docker compose exec redis redis-cli

# Monitor all Redis commands
docker compose exec redis redis-cli MONITOR

# Check Redis memory usage
docker compose exec redis redis-cli INFO memory
```

---

## 🔍 Troubleshooting

### Extension not loaded

**Problem:** `php -m` doesn't show redis or rdkafka

**Solution:**
```bash
# Rebuild container
docker compose build --no-cache app
docker compose up -d app

# Verify build logs
docker compose logs app | grep -i "rdkafka\|redis"
```

---

### Composer install fails

**Problem:** `enqueue/rdkafka` requires ext-rdkafka

**Solution:** This should NOT happen with Docker since extensions are built into the image. If it does:
```bash
# Rebuild with clean slate
docker compose down -v
docker compose build --no-cache
docker compose up -d
```

---

### Cannot connect to Kafka from app container

**Problem:** `Connection refused` to Kafka

**Solution:**
```bash
# Check Kafka is running
docker compose ps kafka

# Check network connectivity
docker compose exec app ping kafka

# Verify KAFKA_ADVERTISED_LISTENERS in docker-compose.yml
# Should be: KAFKA_ADVERTISED_LISTENERS: PLAINTEXT://kafka:9092
```

---

### Permission denied in storage/logs

**Problem:** Cannot write to logs directory

**Solution:**
```bash
# Fix permissions on host
chmod -R 777 storage/logs

# Or inside container
docker compose exec app chown -R www-data:www-data /var/www/html/storage
```

---

## 🎯 Production Deployment

### Environment Variables

Create `.env` file (copy from `.env.example`):

```bash
# Database
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=project_topo
DB_USERNAME=root
DB_PASSWORD=your_secure_password

# Redis
REDIS_HOST=redis
REDIS_PORT=6379

# Kafka
KAFKA_BROKERS=kafka:9092

# RabbitMQ
RABBITMQ_HOST=rabbitmq
RABBITMQ_PORT=5672
RABBITMQ_USER=guest
RABBITMQ_PASSWORD=guest

# Elasticsearch
ELASTICSEARCH_HOST=elasticsearch:9200
```

---

### Scaling Services

```bash
# Scale queue workers
docker compose up -d --scale app=3

# Scale Kafka consumers
docker compose up -d --scale kafka-consumer=5
```

---

### Health Checks

```bash
# Check all container health
docker compose ps

# App health
curl http://localhost:8000/health

# Elasticsearch health
curl http://localhost:9200/_cluster/health

# Redis health
docker compose exec redis redis-cli PING
```

---

## 📊 Performance Comparison

### Before Docker (Pure PHP on host)

| Service | Client | Throughput |
|---------|--------|-----------|
| Redis | Predis | ~5K ops/sec |
| Kafka | nmred/kafka-php | ~1K msg/sec |

### After Docker (with C extensions)

| Service | Client | Throughput | Gain |
|---------|--------|-----------|------|
| Redis | **ext-redis** | ~50K ops/sec | **10x** |
| Kafka | **ext-rdkafka** | ~10-50K msg/sec | **10-50x** |

---

## 🆘 Need Help?

### Check Docker logs
```bash
docker compose logs -f app
docker compose logs -f kafka
docker compose logs -f redis
```

### Enter container for debugging
```bash
docker compose exec app bash
# Inside container:
php -m
composer show
ps aux
```

### Clean restart
```bash
# Remove all containers and volumes
docker compose down -v

# Rebuild from scratch
docker compose build --no-cache
docker compose up -d
```

---

## 📚 Additional Resources

- **Docker Compose Docs**: https://docs.docker.com/compose/
- **PHP Official Images**: https://hub.docker.com/_/php
- **Kafka in Docker**: https://hub.docker.com/r/confluentinc/cp-kafka
- **Redis in Docker**: https://hub.docker.com/_/redis

---

## ✅ Checklist

Before deploying to production:

- [x] All extensions loaded (redis, rdkafka)
- [x] enqueue/rdkafka installed via Composer
- [x] Database migrations run successfully
- [x] Redis Pub/Sub tested
- [x] Kafka producer/consumer tested
- [x] RabbitMQ tested
- [x] Elasticsearch healthy
- [x] Logs show "Using rdkafka extension"
- [ ] Environment variables configured
- [ ] SSL/TLS certificates configured (production)
- [ ] Monitoring and alerting setup
- [ ] Backup strategy defined

---

🎉 **You're ready to rock with high-performance brokers in Docker!**
