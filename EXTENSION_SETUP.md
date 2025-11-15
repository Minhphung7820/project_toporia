# PHP Extensions Setup Guide

This guide explains how to install the required PHP extensions for optimal performance with Toporia Framework's broker system.

## 🚀 Quick Installation (Recommended)

We've created an automated installation script for you:

```bash
# Run the installation script (requires sudo)
bash install-extensions.sh
```

This script will:
1. ✅ Install `ext-redis` for high-performance Redis broker
2. ✅ Install `librdkafka` and `ext-rdkafka` for high-performance Kafka
3. ✅ Install `enqueue/rdkafka` Composer package
4. ✅ Verify all installations

**Expected performance gains:**
- **Redis**: Native C performance (vs pure PHP)
- **Kafka**: 10-50x faster throughput (vs nmred/kafka-php)

---

## 📋 What Gets Installed

| Extension | Purpose | Performance Impact |
|-----------|---------|-------------------|
| `ext-redis` | Redis Pub/Sub broker + Cache driver | ⚡ Native C performance |
| `ext-rdkafka` | Kafka producer/consumer | ⚡⚡⚡ 10-50x faster |
| `librdkafka` | C library for rdkafka | Required dependency |
| `enqueue/rdkafka` | High-level Kafka client | Better API + auto-reconnect |

---

## 🔧 Manual Installation (Alternative)

If you prefer to install manually or the script doesn't work:

### 1. Install ext-redis

**Ubuntu/Debian:**
```bash
sudo apt-get update
sudo apt-get install php-redis
```

**macOS:**
```bash
pecl install redis
```

**Verify:**
```bash
php -m | grep redis
# Output: redis
```

---

### 2. Install librdkafka + ext-rdkafka

**Ubuntu/Debian:**
```bash
# Install librdkafka development library
sudo apt-get install librdkafka-dev

# Install PHP extension via PECL
sudo pecl install rdkafka

# Enable extension
echo "extension=rdkafka.so" | sudo tee /etc/php/$(php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;")/cli/conf.d/20-rdkafka.ini
```

**macOS:**
```bash
# Install librdkafka
brew install librdkafka

# Install PHP extension
pecl install rdkafka
```

**Verify:**
```bash
php -m | grep rdkafka
# Output: rdkafka
```

---

### 3. Install enqueue/rdkafka Composer Package

**After ext-rdkafka is installed:**
```bash
composer require enqueue/rdkafka
```

**Verify:**
```bash
composer show | grep enqueue
# Output: enqueue/rdkafka  0.10.x  ...
```

---

## ✅ Verification

Check all extensions are loaded:

```bash
php -r "
echo 'Redis: ' . (extension_loaded('redis') ? '✅ Installed' : '❌ Missing') . PHP_EOL;
echo 'RdKafka: ' . (extension_loaded('rdkafka') ? '✅ Installed' : '❌ Missing') . PHP_EOL;
echo 'Enqueue: ' . (class_exists('Enqueue\\RdKafka\\RdKafkaConnectionFactory') ? '✅ Installed' : '❌ Missing') . PHP_EOL;
"
```

**Expected output:**
```
Redis: ✅ Installed
RdKafka: ✅ Installed
Enqueue: ✅ Installed
```

---

## 🧪 Testing

### Test Redis Broker

```bash
# Publish a message
php toporia broker:publish redis test.topic '{"message":"Hello Redis!"}'

# Subscribe (in another terminal)
php toporia broker:subscribe redis test.topic
```

### Test Kafka Broker (High Performance)

```bash
# Publish with rdkafka extension
php toporia broker:publish kafka order.created '{"order_id":123}'

# Consume with consumer group
php toporia broker:consume kafka order-tracking order-processor-group
```

**Check logs for performance mode:**
```
[Kafka] Using rdkafka extension (high performance)  ← ✅ Good!
```

---

## 🔍 Troubleshooting

### ext-redis not loading

**Check PHP version:**
```bash
php -v  # Must be 8.1+
```

**Install for correct PHP version:**
```bash
sudo apt-get install php8.1-redis
# or
sudo apt-get install php8.2-redis
```

---

### ext-rdkafka install fails

**Error: "librdkafka not found"**

Solution:
```bash
# Install development headers
sudo apt-get install librdkafka-dev build-essential
```

**Error: "pecl command not found"**

Solution:
```bash
# Install PHP development tools
sudo apt-get install php-pear php-dev
```

---

### enqueue/rdkafka requires ext-rdkafka

**Error during `composer require`:**
```
Package enqueue/rdkafka requires ext-rdkafka which is missing
```

**Solution:** Install ext-rdkafka first (see step 2 above), then retry:
```bash
composer require enqueue/rdkafka
```

---

## 📊 Performance Comparison

### Before (Pure PHP)

```php
// Using nmred/kafka-php (pure PHP)
Throughput: ~1,000 messages/second
CPU usage: High (PHP interpretation overhead)
Memory: Higher (PHP objects)
```

### After (Native Extensions)

```php
// Using ext-rdkafka (native C)
Throughput: ~10,000-50,000 messages/second
CPU usage: Low (native code)
Memory: Lower (C structs)
```

**10-50x performance improvement!** 🚀

---

## 🎯 Production Checklist

Before deploying to production, ensure:

- [x] `ext-redis` installed and loaded
- [x] `ext-rdkafka` installed and loaded
- [x] `librdkafka` version >= 1.0
- [x] `enqueue/rdkafka` in composer.json
- [x] Tested Redis Pub/Sub with `broker:publish`
- [x] Tested Kafka with consumer groups
- [x] Verified logs show "Using rdkafka extension"

---

## 📚 Additional Resources

- **ext-redis**: https://github.com/phpredis/phpredis
- **ext-rdkafka**: https://github.com/arnaud-lb/php-rdkafka
- **librdkafka**: https://github.com/confluentinc/librdkafka
- **enqueue/rdkafka**: https://php-enqueue.github.io/transport/rdkafka/

---

## 🆘 Need Help?

If you encounter issues:

1. Check PHP version: `php -v` (must be >= 8.1)
2. Check loaded extensions: `php -m`
3. Check error logs: `/var/log/php-error.log`
4. See framework logs: `storage/logs/app.log`

For more details, see the main documentation in [CLAUDE.md](CLAUDE.md).
