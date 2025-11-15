# Toporia Framework - Production-Ready PHP 8.2 with High-Performance Extensions
FROM php:8.2-fpm-alpine

# Maintainer
LABEL maintainer="Toporia Framework"
LABEL description="PHP 8.2 with ext-redis, ext-rdkafka, and all required extensions"

# Install system dependencies and build tools
RUN apk add --no-cache \
    # Build dependencies
    autoconf \
    g++ \
    make \
    pkgconfig \
    # librdkafka (C library for Kafka)
    librdkafka-dev \
    # Redis dependencies
    linux-headers \
    # Database dependencies
    postgresql-dev \
    mysql-dev \
    # Compression
    zlib-dev \
    libzip-dev \
    # Git for Composer
    git \
    unzip \
    # Process manager
    supervisor \
    # Utilities
    bash \
    curl

# Install PHP extensions via docker-php-ext-install
RUN docker-php-ext-install \
    pdo_mysql \
    pdo_pgsql \
    opcache \
    zip \
    pcntl \
    sockets

# Install PECL extensions (Redis + RdKafka)
RUN pecl install redis-6.0.2 && \
    pecl install rdkafka-6.0.3 && \
    docker-php-ext-enable redis rdkafka

# Configure PHP for production
RUN { \
    echo 'opcache.enable=1'; \
    echo 'opcache.memory_consumption=256'; \
    echo 'opcache.interned_strings_buffer=16'; \
    echo 'opcache.max_accelerated_files=10000'; \
    echo 'opcache.validate_timestamps=0'; \
    echo 'opcache.fast_shutdown=1'; \
    } > /usr/local/etc/php/conf.d/opcache.ini

# Configure PHP settings
RUN { \
    echo 'memory_limit=512M'; \
    echo 'upload_max_filesize=50M'; \
    echo 'post_max_size=50M'; \
    echo 'max_execution_time=300'; \
    echo 'date.timezone=UTC'; \
    } > /usr/local/etc/php/conf.d/custom.ini

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files first (for layer caching)
COPY composer.json composer.lock* ./

# Install Composer dependencies (including enqueue/rdkafka)
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist

# Install enqueue/rdkafka specifically
RUN composer require enqueue/rdkafka --no-interaction

# Copy application code
COPY . .

# Generate optimized autoloader
RUN composer dump-autoload --optimize --classmap-authoritative

# Set permissions
RUN mkdir -p /var/www/html/storage /var/www/html/bootstrap/cache \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache


# Expose port 9000 for PHP-FPM
EXPOSE 9000

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=40s \
    CMD php -v || exit 1

# Start PHP-FPM
CMD ["php-fpm"]
