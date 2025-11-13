# Hướng dẫn cài đặt Kafka Client - Giải quyết lỗi

## Lỗi thường gặp

```
Cannot use enqueue/rdkafka's latest version 0.10.26 as it requires
ext-rdkafka ^4.0|^5.0|^6.0 which is missing from your platform.
```

**Nguyên nhân:** Thiếu `ext-rdkafka` C extension.

---

## Giải pháp 1: Cài ext-rdkafka (Khuyến nghị cho Production)

### Bước 1: Cài đặt librdkafka (C library)

#### Ubuntu/Debian:
```bash
sudo apt-get update
sudo apt-get install -y librdkafka-dev
```

#### CentOS/RHEL:
```bash
sudo yum install librdkafka-devel
```

#### macOS:
```bash
brew install librdkafka
```

#### Windows:
- Download từ: https://github.com/edenhill/librdkafka/releases
- Hoặc dùng WSL/Linux

### Bước 2: Cài ext-rdkafka PHP extension

```bash
# Cài qua PECL
pecl install rdkafka

# Nếu gặp lỗi, thử:
pecl install rdkafka-6.0.0

# Enable extension
echo "extension=rdkafka.so" | sudo tee -a /etc/php/8.3/cli/php.ini
echo "extension=rdkafka.so" | sudo tee -a /etc/php/8.3/fpm/php.ini  # Nếu dùng FPM
```

### Bước 3: Verify installation

```bash
php -m | grep rdkafka
# Should output: rdkafka
```

### Bước 4: Cài enqueue/rdkafka

```bash
composer require enqueue/rdkafka
```

---

## Giải pháp 2: Dùng nmred/kafka-php (Không cần C extension)

Nếu không thể cài `ext-rdkafka`, dùng pure PHP library:

```bash
# Cài trực tiếp, không cần extension
composer require nmred/kafka-php
```

**Ưu điểm:**
- ✅ Không cần compile C extension
- ✅ Hoạt động ngay lập tức
- ✅ Cross-platform

**Nhược điểm:**
- ⚠️ Chậm hơn ~7x so với rdkafka
- ⚠️ Memory usage cao hơn

---

## Giải pháp 3: Docker (Khuyến nghị)

Nếu dùng Docker, tạo custom image:

### Dockerfile:
```dockerfile
FROM php:8.3-fpm

# Cài librdkafka
RUN apt-get update && \
    apt-get install -y librdkafka-dev && \
    rm -rf /var/lib/apt/lists/*

# Cài rdkafka extension
RUN pecl install rdkafka && \
    docker-php-ext-enable rdkafka

# Cài dependencies
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader
```

### docker-compose.yml:
```yaml
services:
  app:
    build: .
    volumes:
      - .:/var/www/html
```

---

## Troubleshooting

### Lỗi: "pecl: command not found"

**Giải pháp:**
```bash
# Ubuntu/Debian
sudo apt-get install php-pear php-dev

# CentOS/RHEL
sudo yum install php-pear php-devel

# macOS
brew install php
```

### Lỗi: "librdkafka not found"

**Giải pháp:**
```bash
# Ubuntu/Debian
sudo apt-get install librdkafka-dev

# CentOS/RHEL
sudo yum install librdkafka-devel

# macOS
brew install librdkafka
```

### Lỗi: "PHP version not satisfied"

**Kiểm tra PHP version:**
```bash
php -v
```

**Yêu cầu:**
- `enqueue/rdkafka`: PHP >= 7.3
- `nmred/kafka-php`: PHP >= 7.1

Bạn đang dùng PHP 8.3.6 ✅ (OK)

### Lỗi: "Cannot find php.h"

**Giải pháp:**
```bash
# Ubuntu/Debian
sudo apt-get install php-dev

# CentOS/RHEL
sudo yum install php-devel
```

---

## Quick Start (Development)

Nếu chỉ cần test nhanh, dùng `nmred/kafka-php`:

```bash
# Bỏ qua việc cài rdkafka, dùng pure PHP
composer require nmred/kafka-php
```

Code sẽ tự động detect và dùng `kafka-php` nếu `rdkafka` không có.

---

## Production Setup

### Option A: Cài rdkafka (Tối ưu)

```bash
# 1. Cài librdkafka
sudo apt-get install librdkafka-dev

# 2. Cài extension
pecl install rdkafka
echo "extension=rdkafka.so" >> /etc/php/8.3/cli/php.ini

# 3. Cài library
composer require enqueue/rdkafka
```

### Option B: Dùng Docker (Khuyến nghị)

```bash
# Build image với rdkafka pre-installed
docker build -t toporia-kafka .

# Run
docker-compose up -d
```

---

## Verify Setup

Sau khi cài xong, verify:

```bash
# Check extension
php -m | grep rdkafka

# Check library
composer show | grep kafka

# Test code
php -r "var_dump(class_exists('RdKafka\Producer'));"
# Should output: bool(true)
```

---

## Khuyến nghị

### Development:
```bash
composer require nmred/kafka-php
```
- Dễ setup
- Không cần compile
- Đủ cho development

### Production:
```bash
# Cài rdkafka extension trước
pecl install rdkafka
composer require enqueue/rdkafka
```
- Hiệu năng cao
- Production-ready
- Đáng đầu tư setup

---

## Next Steps

1. **Nếu có thể cài extension** → Làm theo Giải pháp 1
2. **Nếu không thể cài extension** → Dùng Giải pháp 2 (`nmred/kafka-php`)
3. **Nếu dùng Docker** → Dùng Giải pháp 3

Sau khi cài xong, chạy:
```bash
php console realtime:kafka:consume --channels=test
```

