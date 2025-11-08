# Installation Guide

## Requirements

- PHP >= 8.1
- Composer

## Optional PHP Extensions

The framework supports multiple drivers for various features. Install the extensions you need:

### Redis (for Cache and Queue)

**Ubuntu/Debian:**
```bash
sudo apt-get install php-redis
# or
sudo pecl install redis
```

**macOS:**
```bash
pecl install redis
```

**Enable the extension** in `php.ini`:
```ini
extension=redis.so
```

**Verify installation:**
```bash
php -m | grep redis
```

### PDO Extensions (for Database)

**MySQL:**
```bash
sudo apt-get install php-mysql
```

**PostgreSQL:**
```bash
sudo apt-get install php-pgsql
```

**SQLite:**
```bash
sudo apt-get install php-sqlite3
```

## Setup

1. **Install dependencies:**
```bash
composer install
```

2. **Generate autoload files:**
```bash
composer dump-autoload
```

3. **Create configuration:**
```bash
cp .env.example .env
```

4. **Configure your database** in `.env`:
```env
DB_HOST=localhost
DB_NAME=myapp
DB_USER=root
DB_PASS=secret

REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

5. **Set application key** (for cookie encryption):
```bash
# Generate a random 32-character key
php -r "echo bin2hex(random_bytes(16)) . PHP_EOL;"
```

Add to `.env`:
```env
APP_KEY=your-generated-key-here
```

## Development Server

```bash
php -S localhost:8000 -t public
```

Visit: http://localhost:8000

## Database Setup

### Create Database Tables

```sql
-- Users table (for authentication)
CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Jobs table (for queue system)
CREATE TABLE jobs (
    id VARCHAR(255) PRIMARY KEY,
    queue VARCHAR(255) NOT NULL,
    payload TEXT NOT NULL,
    attempts INTEGER NOT NULL DEFAULT 0,
    available_at INTEGER NOT NULL,
    created_at INTEGER NOT NULL,
    INDEX idx_queue_available (queue, available_at)
);

-- Failed jobs table
CREATE TABLE failed_jobs (
    id VARCHAR(255) PRIMARY KEY,
    queue VARCHAR(255) NOT NULL,
    payload TEXT NOT NULL,
    exception TEXT NOT NULL,
    failed_at INTEGER NOT NULL
);
```

## IDE Setup

### PhpStorm

1. **Enable Composer support:**
   - Settings → PHP → Composer
   - Set path to composer.json

2. **Configure PHP interpreter:**
   - Settings → PHP
   - Set PHP language level to 8.1+

3. **Install PHPStorm stubs** (for better Redis support):
```bash
composer require --dev jetbrains/phpstorm-stubs
```

### VS Code

1. **Install PHP Intelephense extension**

2. **Add to settings.json:**
```json
{
    "intelephense.stubs": [
        "redis",
        "pdo",
        "pdo_mysql",
        "pdo_pgsql"
    ]
}
```

## Running Queue Worker

```bash
php worker.php
```

Or create `worker.php`:
```php
<?php

require __DIR__ . '/bootstrap/app.php';

use Toporia\Framework\Queue\Worker;

$queue = app('queue')->driver('database');
$worker = new Worker($queue, maxJobs: 100, sleep: 3);

echo "Starting queue worker...\n";
$worker->work('default');
```

## Running Task Scheduler

Add to crontab (`crontab -e`):
```bash
* * * * * php /path/to/project/schedule.php >> /dev/null 2>&1
```

Create `schedule.php`:
```php
<?php

require __DIR__ . '/bootstrap/app.php';

$schedule = app('schedule');

// Define your tasks here...
$schedule->call(function () {
    // Your task
})->daily();

// Run due tasks
$schedule->runDueTasks();
```

## Docker Setup (Optional)

Create `docker-compose.yml`:
```yaml
version: '3.8'

services:
  app:
    image: php:8.1-fpm
    volumes:
      - .:/var/www/html
    depends_on:
      - mysql
      - redis

  nginx:
    image: nginx:alpine
    ports:
      - "8000:80"
    volumes:
      - .:/var/www/html
      - ./nginx.conf:/etc/nginx/conf.d/default.conf
    depends_on:
      - app

  mysql:
    image: mysql:8
    environment:
      MYSQL_ROOT_PASSWORD: secret
      MYSQL_DATABASE: myapp
    volumes:
      - mysql_data:/var/lib/mysql

  redis:
    image: redis:alpine
    ports:
      - "6379:6379"

volumes:
  mysql_data:
```

Run with:
```bash
docker-compose up -d
```

## Troubleshooting

### Redis connection failed

Make sure Redis server is running:
```bash
# Ubuntu/Debian
sudo systemctl start redis-server

# macOS
brew services start redis
```

Test connection:
```bash
redis-cli ping
# Should return: PONG
```

### Database connection failed

1. Check database credentials in `.env`
2. Verify database exists:
```bash
mysql -u root -p -e "SHOW DATABASES;"
```

3. Grant permissions:
```sql
GRANT ALL PRIVILEGES ON myapp.* TO 'user'@'localhost' IDENTIFIED BY 'password';
FLUSH PRIVILEGES;
```

### Permission denied errors

Make sure storage directories are writable:
```bash
mkdir -p storage/cache storage/logs
chmod -R 775 storage
```

### Composer autoload not working

Regenerate autoload files:
```bash
composer dump-autoload
```

## Production Deployment

1. **Set environment to production:**
```env
APP_ENV=production
```

2. **Enable HTTPS security:**
```env
APP_SECURE=true
```

3. **Optimize autoloader:**
```bash
composer dump-autoload --optimize
```

4. **Configure web server** (Nginx example):
```nginx
server {
    listen 80;
    server_name example.com;
    root /var/www/html/public;

    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

5. **Set up supervisor for queue worker:**
```ini
[program:queue-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/html/worker.php
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/html/storage/logs/worker.log
```

Reload supervisor:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start queue-worker:*
```
