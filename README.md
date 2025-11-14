<div align="center">

<img src=".github/logo.png" alt="Toporia Framework" width="200"/>

# Toporia Framework

**A Professional PHP Framework Built on Clean Architecture Principles**

[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D8.1-blue.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![Version](https://img.shields.io/badge/version-1.0.0-orange.svg)](CHANGELOG.md)

**Version 1.0.0** | Released November 11, 2025 | Built by **TMP DEV**

</div>

---

## 🌟 Overview

**Toporia** is a modern, professional-grade PHP framework designed with **Clean Architecture** and **SOLID principles** at its core. It provides a robust foundation for building scalable web applications while maintaining strict separation of concerns between framework and application layers.

Inspired by Laravel's elegance and Symfony's architecture, Toporia offers a **zero-dependency** core framework with optional integrations, giving you full control over your application's architecture.

## ✨ Key Features

### 🏗️ Architecture & Design

- **Clean Architecture** - Strict layer separation (Domain, Application, Infrastructure, Presentation)
- **SOLID Principles** - Every component follows Single Responsibility, Open/Closed, Liskov Substitution, Interface Segregation, and Dependency Inversion
- **Dependency Injection** - Advanced PSR-11 inspired container with auto-wiring
- **Interface-Based Design** - Program to interfaces, not implementations
- **Service Provider Pattern** - Modular service registration with lazy loading

### 🚀 Core Framework Components

#### **Routing System**
- Fully OOP router with fluent API
- RESTful HTTP verbs (`get`, `post`, `put`, `patch`, `delete`)
- Route parameters with regex support (`{id}`, `{slug}`)
- Named routes for URL generation
- Route groups with shared attributes (prefix, middleware, namespace)
- Middleware pipeline with before/after hooks

#### **HTTP Layer**
- PSR-7 inspired Request/Response abstraction
- JSON detection and response helpers
- File upload handling with validation
- Cookie management with encryption
- Security headers middleware (CSP, HSTS, X-Frame-Options)

#### **Dependency Injection Container**
- Auto-wiring with type-hint resolution
- Constructor and method injection
- Singleton and factory patterns
- Circular dependency detection
- Method invocation with DI via `call()`

#### **Event System**
- Priority-based event dispatchers
- Event propagation control
- Event subscriber pattern
- PSR-14 inspired interface design

#### **Console Framework**
- Professional CLI with Command pattern
- Input parsing (arguments, options, flags)
- Colored output and formatted tables
- Interactive prompts and confirmations
- Built-in commands (cache, queue, schedule)

### 📦 Feature Services

#### **Database & ORM**
- Multi-connection database manager (MySQL, PostgreSQL, SQLite)
- Fluent Query Builder with automatic parameter binding
- Eloquent-style ORM with Active Record pattern
- Model relationships (HasOne, HasMany, BelongsTo, BelongsToMany)
- Eager loading to prevent N+1 queries
- Relationship aggregates (withCount, withSum, withAvg, withMin, withMax)
- Bulk upsert (insert/update) - 100x faster than separate queries
- Migration system with Schema Builder
- Model hooks (creating, created, updating, updated, deleting, deleted)
- Repository pattern support

#### **Logging System** (PSR-3)
- Multi-channel logger (Daily, Single, Stack, Syslog, Stderr)
- **Daily file rotation** with YYYY-MM-DD.log format
- Auto-cleanup of old logs (configurable retention)
- Placeholder interpolation (`{user_id}` syntax)
- Context data as structured JSON
- Thread-safe file locking (LOCK_EX)
- Laravel-compatible API and helpers

#### **Authentication & Authorization**
- Session-based authentication
- Token-based authentication (API)
- Gate system for closure-based authorization
- Policy classes for resource-based authorization
- Password hashing (Bcrypt, Argon2id)
- Automatic hash algorithm migration

#### **Security**
- **CSRF Protection** - Token-based validation for state-changing requests
- **XSS Protection** - Input sanitization and output escaping utilities
- **SQL Injection Prevention** - Parameterized queries by default
- **Security Headers** - CSP, HSTS, X-Frame-Options, X-Content-Type-Options
- **Rate Limiting** - Configurable request throttling
- **Cookie Encryption** - Automatic encryption/decryption

#### **Caching**
- Multi-driver cache system (File, Redis, Memory)
- PSR-16 inspired simple cache interface
- `remember()` pattern for lazy caching
- Increment/decrement operations
- Forever caching with `forever()`

#### **Queue System**
- Asynchronous job processing
- Multiple drivers (Sync, Database, Redis)
- Delayed job execution
- Job retries with exponential backoff
- Failed job handling
- Queue worker with graceful shutdown

#### **Realtime & Broadcasting**
- Multi-transport delivery (WebSocket, SSE, Long-polling, Socket.IO)
- Broker drivers: Redis (fast fan-out), RabbitMQ (durable routing), Kafka (high-throughput replay)
- Auto topic/queue binding via clean Service Provider configuration
- Enterprise-grade performance with batching, QoS, and graceful shutdown consumers

#### **Search & Indexing**
- First-class Elasticsearch module with reusable `SearchManager`
- Bulk indexing, queue-aware sync, and ORM trait for auto document updates
- Fluent query builder + console reindex command (`php console search:reindex`)
- Optimized client (connection pooling, retries, deferred flush) for high-throughput indexing

#### **Task Scheduling**
- Cron-like task scheduler
- Frequency helpers (everyMinute, hourly, daily, weekly, monthly)
- Conditional execution (when, skip)
- Custom cron expressions
- Timezone support

#### **File Storage**
- Multi-driver storage system (Local, S3, DigitalOcean Spaces, MinIO)
- Laravel-style Storage facade
- File upload handling with validation
- Hash-based filenames for security

#### **Email System**
- Multi-driver mailer (SMTP, Log, Array)
- HTML email support
- Queue integration for async sending

#### **Notifications**
- Multi-channel notifications (Mail, Database, SMS, Slack, Broadcast)
- Notifiable trait for models
- Database notification storage
- Real-time WebSocket/SSE broadcast notifications
- Laravel-compatible API

#### **Collections**
- **Collection** - Eager collection with 40+ methods (map, filter, reduce, groupBy, sortBy)
- **LazyCollection** - Generator-based lazy evaluation for large datasets
- Functional programming patterns (flatMap, partition, zip, chunk)
- Statistical operations (avg, median, mode, sum)
- Set operations (diff, intersect, union)

#### **Validation**
- Form request validation with automatic error handling
- 20+ built-in rules (required, email, unique, exists, min, max, regex)
- Database validation rules (unique, exists)
- Custom rule support
- Laravel-compatible API

#### **Error Handling**
- Beautiful error pages with syntax highlighting
- Stack trace with file links
- Request information panel
- JSON error responses for APIs
- Environment-aware (debug vs production)

### 🛠️ Developer Experience

- **Zero Framework Dependencies** - Core framework requires only PHP 8.1+
- **Laravel-Compatible API** - Familiar syntax for Laravel developers
- **Comprehensive Documentation** - 15+ markdown guides in `/docs`
- **Helper Functions** - 50+ global helpers for common tasks
- **Static Facades** - Convenient static access via ServiceAccessor
- **Type Safety** - Full PHP 8.1+ type hints and strict types
- **PHPDoc Comments** - Complete API documentation
- **Clean Code** - PSR-12 coding standards

## 📋 Requirements

- **PHP** >= 8.1
- **Composer** for dependency management

### Optional PHP Extensions

- `ext-redis` - Required for Redis cache and queue drivers
- `ext-pdo_mysql` - Required for MySQL database support
- `ext-pdo_pgsql` - Required for PostgreSQL database support
- `ext-pdo_sqlite` - Required for SQLite database support

## 🚀 Quick Start

### Installation

```bash
# Clone the repository
git clone https://github.com/tmp-dev/toporia.git
cd toporia

# Install dependencies
composer install

# Copy environment file
cp .env.example .env

# Generate application key
php console key:generate

# Set up database (optional)
# Edit .env with your database credentials

# Run migrations (optional)
php console migrate

# Start development server
php -S localhost:8000 -t public
```

Visit `http://localhost:8000` in your browser.

### Local Kafka (Order Tracking Demo)

The order tracking consumer/producer example requires a Kafka cluster. A
ready-to-use Docker Compose setup is included:

```bash
# Start ZooKeeper + Kafka
docker compose up -d zookeeper kafka

# Configure environment (after copying .env)
echo "REALTIME_BROKER=kafka" >> .env
echo "KAFKA_BROKERS=localhost:9092" >> .env

# Create topic (optional, auto-create is enabled by default)
docker exec -it project_topo_kafka kafka-topics.sh \
  --create --topic orders.events \
  --bootstrap-server localhost:9092 \
  --partitions 10 --replication-factor 1

# Run producer & consumer
php console order:tracking:consume --max-messages=10
curl "http://localhost:8081/api/orders/produce?event=order.created&order_id=123&user_id=456"
```

Logs for the consumer will appear under `storage/logs/<date>.log`.

### Hello World Example

**routes/web.php:**
```php
$router->get('/', function() {
    return response()->json(['message' => 'Hello, Toporia!']);
});
```

**Controller example:**
```php
namespace App\Presentation\Http\Controllers;

class HomeController extends BaseController
{
    public function index()
    {
        return $this->view('home', [
            'title' => 'Welcome to Toporia'
        ]);
    }
}
```

**ORM example:**
```php
use App\Domain\Product\ProductModel;

// Create
$product = ProductModel::create([
    'title' => 'Laptop',
    'price' => 999.99
]);

// Query
$products = ProductModel::where('price', '>', 500)
    ->orderBy('created_at', 'DESC')
    ->limit(10)
    ->get();

// Relationships with eager loading
$users = UserModel::with(['posts', 'profile'])->get();
```

**Logging example:**
```php
use Toporia\Framework\Support\Accessors\Log;

// Simple logging
Log::info('User logged in', ['user_id' => 123]);
Log::error('Database connection failed');

// Or use helpers
log_info('Application started');
logger('Debug message', ['context' => 'data'], 'debug');
```

## 📚 Documentation

Comprehensive documentation is available in the `/docs` directory:

- [Installation Guide](INSTALLATION.md)
- [Architecture Overview](docs/ARCHITECTURE.md)
- [Database & ORM](docs/ORM.md)
- [Logging System](docs/LOGGING.md)
- [Form Validation](docs/FORM_VALIDATION.md)
- [Security Features](docs/SECURITY.md)
- [Storage & File Uploads](docs/STORAGE.md)
- [Notification System](docs/NOTIFICATION_SYSTEM.md)
- [Error Handling](docs/ERROR_HANDLING.md)
- [Collections](docs/COLLECTIONS.md)
- [And more...](docs/)

## 🏛️ Architecture

Toporia follows **Clean Architecture** principles with strict layer separation:

```
┌─────────────────────────────────────────────────────────┐
│                  Presentation Layer                     │
│  (Controllers, Actions, Middleware, Views, API)         │
└─────────────────────────────────────────────────────────┘
                           ↓
┌─────────────────────────────────────────────────────────┐
│                  Application Layer                      │
│     (Use Cases, Commands, Handlers, DTOs)               │
└─────────────────────────────────────────────────────────┘
                           ↓
┌─────────────────────────────────────────────────────────┐
│                    Domain Layer                         │
│  (Entities, Value Objects, Repository Interfaces)       │
└─────────────────────────────────────────────────────────┘
                           ↓
┌─────────────────────────────────────────────────────────┐
│                 Infrastructure Layer                    │
│   (Repository Implementations, External Services)       │
└─────────────────────────────────────────────────────────┘
                           ↓
┌─────────────────────────────────────────────────────────┐
│                   Framework Layer                       │
│  (HTTP, Routing, Container, Events, Database, etc.)     │
└─────────────────────────────────────────────────────────┘
```

**Key Principles:**
- **Dependency Inversion** - High-level modules don't depend on low-level modules
- **Interface Segregation** - Small, focused interfaces
- **Single Responsibility** - Each class has one reason to change
- **Open/Closed** - Open for extension, closed for modification

## 🧪 Testing

```bash
# Run PHPUnit tests
composer test

# Run with coverage
composer test:coverage

# Run PHPStan static analysis
composer phpstan
```

## 📊 Performance

Toporia is designed for performance:

- **O(1) Container Resolution** - Cached singleton bindings
- **O(1) Route Matching** - Optimized regex compilation
- **Lazy Loading** - Services created only when needed
- **Query Optimization** - Eager loading prevents N+1 queries
- **File Locking** - Thread-safe concurrent operations
- **Opcode Caching** - Compatible with OPcache/JIT

**Benchmarks** (on modest hardware):
- **Logger**: ~0.5ms per write (2000 writes/sec)
- **Router**: ~0.1ms per route match
- **Container**: ~0.05ms per resolution
- **ORM Query**: ~1-5ms per database query
- **Upsert**: 100x faster than separate insert/update

## 🤝 Contributing

Contributions are welcome! Please read our [Contributing Guide](CONTRIBUTING.md) for details on:

- Code of Conduct
- Development process
- Submitting pull requests
- Coding standards

## 📄 License

Toporia Framework is open-source software licensed under the [MIT license](LICENSE).

## 👥 Credits

**Developed by TMP DEV**

- Framework Architecture: Clean Architecture + SOLID Principles
- Inspired by: Laravel, Symfony, PSR Standards
- Built with: PHP 8.1+, Composer

## 🔗 Links

- **Documentation**: [docs/](docs/)
- **GitHub**: [https://github.com/tmp-dev/toporia](https://github.com/tmp-dev/toporia)
- **Issues**: [https://github.com/tmp-dev/toporia/issues](https://github.com/tmp-dev/toporia/issues)
- **Changelog**: [CHANGELOG.md](CHANGELOG.md)

---

<div align="center">

**Made with ❤️ by TMP DEV**

*Building professional PHP applications with Clean Architecture*

**Version 1.0.0** | November 11, 2025

</div>
