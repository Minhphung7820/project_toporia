# PHP Extensions Setup Guide

This guide helps you install optional PHP extensions for enhanced performance and features.

## Redis Extension (Recommended for Redis Broker)

The Redis extension provides native PHP support for Redis Pub/Sub and caching.

### Check if Installed

```bash
php -m | grep redis
```

If no output, the extension is not installed.

### Installation

#### Ubuntu/Debian

```bash
# Install via apt (recommended)
sudo apt-get update
sudo apt-get install php-redis

# Or via PECL
sudo apt-get install php-dev
sudo pecl install redis
echo "extension=redis.so" | sudo tee /etc/php/8.2/mods-available/redis.ini
sudo phpenmod redis
```

#### macOS (Homebrew)

```bash
# Install via PECL
pecl install redis

# Add to php.ini
echo "extension=redis.so" >> $(php --ini | grep "Loaded Configuration" | sed -e "s|.*:\s*||")
```

#### Verify Installation

```bash
php -m | grep redis
# Should output: redis

php -r "echo extension_loaded('redis') ? 'Redis extension is loaded' : 'Not loaded';"
```

### Test Redis Broker

```bash
# Start Redis server
docker run -d -p 6379:6379 redis:latest

# Test consumer
php console realtime:redis:consume --channels=test
```

---

## librdkafka + enqueue/rdkafka (Optional - High Performance Kafka)

The rdkafka extension provides **10-50x better performance** than pure PHP Kafka clients.

### Performance Comparison

| Client | Throughput | Latency | CPU Usage |
|--------|-----------|---------|-----------|
| nmred/kafka-php (current) | ~1k msg/sec | ~50ms | High |
| rdkafka extension | ~50k msg/sec | ~1ms | Low |

### Check if Installed

```bash
php -m | grep rdkafka
```

### Installation

#### Ubuntu/Debian

```bash
# Install librdkafka C library
sudo apt-get install librdkafka-dev

# Install PHP extension via PECL
sudo pecl install rdkafka
echo "extension=rdkafka.so" | sudo tee /etc/php/8.2/mods-available/rdkafka.ini
sudo phpenmod rdkafka

# Install enqueue/rdkafka PHP wrapper
composer require enqueue/rdkafka
```

#### macOS (Homebrew)

```bash
# Install librdkafka
brew install librdkafka

# Install PHP extension
pecl install rdkafka
echo "extension=rdkafka.so" >> $(php --ini | grep "Loaded Configuration" | sed -e "s|.*:\s*||")

# Install enqueue/rdkafka PHP wrapper
composer require enqueue/rdkafka
```

#### Verify Installation

```bash
php -m | grep rdkafka
# Should output: rdkafka

php -r "echo extension_loaded('rdkafka') ? 'rdkafka extension is loaded' : 'Not loaded';"
```

### Enable in Framework

The framework will **automatically use rdkafka** if detected:

```php
// config/realtime.php
'kafka' => [
    'client' => 'auto',  // Auto-detect: rdkafka > kafka-php
    // Or force specific client:
    // 'client' => 'rdkafka',  // Use rdkafka
    // 'client' => 'php',      // Use nmred/kafka-php
]
```

### Test Kafka with rdkafka

```bash
# Start Kafka
docker compose up -d zookeeper kafka

# Consumer will auto-use rdkafka if available
php console realtime:kafka:consume --channels=test

# Check logs - should see "Using rdkafka client"
```

---

## AMQP Extension (Optional - RabbitMQ Performance)

**Note**: The framework uses `php-amqplib` (pure PHP), which works well. The AMQP extension is optional for extreme performance.

### Installation

```bash
# Ubuntu/Debian
sudo apt-get install librabbitmq-dev
sudo pecl install amqp
echo "extension=amqp.so" | sudo tee /etc/php/8.2/mods-available/amqp.ini
sudo phpenmod amqp

# Verify
php -m | grep amqp
```

---

## Extension Summary

| Extension | Status | Performance Impact | Required? |
|-----------|--------|-------------------|-----------|
| **redis** | ⚠️ Not installed | High (native C) | Recommended for Redis broker |
| **rdkafka** | ⚠️ Not installed | Very High (10-50x) | Optional but recommended for production Kafka |
| **amqp** | ❌ Not needed | Medium | Optional (php-amqplib works fine) |

---

## Quick Install (All Extensions)

### Ubuntu/Debian

```bash
# Install all recommended extensions
sudo apt-get update
sudo apt-get install -y \
    php-redis \
    librdkafka-dev

sudo pecl install rdkafka
echo "extension=rdkafka.so" | sudo tee /etc/php/8.2/mods-available/rdkafka.ini
sudo phpenmod rdkafka

# Install rdkafka PHP wrapper
cd /home/it-sidcorp/Downloads/test/toporia
composer require enqueue/rdkafka

# Verify all
php -m | grep -E "(redis|rdkafka)"
```

### macOS

```bash
# Install all recommended extensions
brew install librdkafka

pecl install redis rdkafka

echo "extension=redis.so" >> $(php --ini | grep "Loaded Configuration" | sed -e "s|.*:\s*||")
echo "extension=rdkafka.so" >> $(php --ini | grep "Loaded Configuration" | sed -e "s|.*:\s*||")

# Install rdkafka PHP wrapper
cd /home/it-sidcorp/Downloads/test/toporia
composer require enqueue/rdkafka

# Verify all
php -m | grep -E "(redis|rdkafka)"
```

---

## Troubleshooting

### Redis extension not loading

```bash
# Check if extension file exists
php -i | grep extension_dir
ls -la $(php -i | grep extension_dir | awk '{print $3}')/redis.so

# Check php.ini
php --ini
# Edit the Loaded Configuration File and add: extension=redis.so
```

### rdkafka extension not loading

```bash
# Check if librdkafka is installed
ldconfig -p | grep rdkafka

# If missing, install librdkafka first
sudo apt-get install librdkafka-dev  # Ubuntu
brew install librdkafka              # macOS

# Then reinstall PHP extension
sudo pecl install rdkafka
```

### Permission issues with PECL

```bash
# Use sudo for PECL installations
sudo pecl install redis
sudo pecl install rdkafka
```

---

## After Installation

1. **Restart PHP-FPM** (if using):
   ```bash
   sudo systemctl restart php8.2-fpm
   ```

2. **Verify extensions**:
   ```bash
   php -m | grep -E "(redis|rdkafka)"
   ```

3. **Test brokers**:
   ```bash
   # Test Redis
   php console realtime:redis:consume --channels=test

   # Test Kafka (will auto-use rdkafka if available)
   php console realtime:kafka:consume --channels=test
   ```

4. **Check framework detection**:
   ```bash
   # The framework logs which client it's using
   php console realtime:kafka:consume --channels=test
   # Look for: "Using rdkafka client" or "Using kafka-php client"
   ```
