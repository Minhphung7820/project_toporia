# Manual Installation Steps (Requires Your Password)

Detected PHP version: **8.2**

Please run these commands **one by one** in your terminal (they will ask for your sudo password):

---

## Step 1: Install ext-redis

```bash
sudo apt-get update
sudo apt-get install -y php8.2-redis
```

**Verify:**
```bash
php -m | grep redis
# Should output: redis
```

---

## Step 2: Install librdkafka (C library)

```bash
sudo apt-get install -y librdkafka-dev build-essential
```

**Verify:**
```bash
ldconfig -p | grep librdkafka
# Should show librdkafka.so paths
```

---

## Step 3: Install PHP development tools (for PECL)

```bash
sudo apt-get install -y php-pear php8.2-dev
```

**Verify:**
```bash
pecl version
# Should show PECL version
```

---

## Step 4: Install ext-rdkafka via PECL

```bash
sudo pecl install rdkafka
```

**Enable the extension:**
```bash
echo "extension=rdkafka.so" | sudo tee /etc/php/8.2/cli/conf.d/20-rdkafka.ini
echo "extension=rdkafka.so" | sudo tee /etc/php/8.2/fpm/conf.d/20-rdkafka.ini  # If using PHP-FPM
```

**Verify:**
```bash
php -m | grep rdkafka
# Should output: rdkafka
```

---

## Step 5: Install enqueue/rdkafka via Composer

**No sudo needed for this:**
```bash
cd /home/it-sidcorp/Downloads/test/toporia
composer require enqueue/rdkafka
```

**Verify:**
```bash
composer show | grep enqueue
# Should show: enqueue/rdkafka
```

---

## Final Verification

Run this to check everything:

```bash
php -r "
echo '========================================' . PHP_EOL;
echo 'PHP Extension Status' . PHP_EOL;
echo '========================================' . PHP_EOL;
echo 'Redis:    ' . (extension_loaded('redis') ? '✅ Installed' : '❌ Missing') . PHP_EOL;
echo 'RdKafka:  ' . (extension_loaded('rdkafka') ? '✅ Installed' : '❌ Missing') . PHP_EOL;
echo 'Enqueue:  ' . (class_exists('Enqueue\\\\RdKafka\\\\RdKafkaConnectionFactory') ? '✅ Installed' : '❌ Missing') . PHP_EOL;
echo '========================================' . PHP_EOL;

if (extension_loaded('redis')) {
    echo 'Redis version: ' . phpversion('redis') . PHP_EOL;
}
if (extension_loaded('rdkafka')) {
    echo 'RdKafka version: ' . phpversion('rdkafka') . PHP_EOL;
}
"
```

**Expected output:**
```
========================================
PHP Extension Status
========================================
Redis:    ✅ Installed
RdKafka:  ✅ Installed
Enqueue:  ✅ Installed
========================================
Redis version: 6.x.x
RdKafka version: 6.x.x
```

---

## After Installation

Once all extensions are installed, test the broker system:

```bash
# Test Redis
php toporia broker:publish redis test.topic '{"message":"Hello"}'

# Test Kafka (should use rdkafka extension now)
php toporia broker:publish kafka order.created '{"order_id":123}'
```

Check logs for:
```
[Kafka] Using rdkafka extension (high performance)  ← ✅ You want to see this!
```

---

## Alternative: One-Line Install (All Steps Combined)

If you want to paste everything at once:

```bash
sudo apt-get update && \
sudo apt-get install -y php8.2-redis librdkafka-dev build-essential php-pear php8.2-dev && \
sudo pecl install rdkafka && \
echo "extension=rdkafka.so" | sudo tee /etc/php/8.2/cli/conf.d/20-rdkafka.ini && \
cd /home/it-sidcorp/Downloads/test/toporia && \
composer require enqueue/rdkafka
```

Then verify:
```bash
php -m | grep -E "(redis|rdkafka)"
```

You should see:
```
rdkafka
redis
```

🎉 **Done!**
