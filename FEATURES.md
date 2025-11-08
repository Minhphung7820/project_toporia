# Framework Features

Comprehensive list of features implemented in this Clean Architecture PHP framework.

## Security Features

### ✅ CSRF Protection
- Session-based token management
- Automatic validation middleware
- Helper functions: `csrf_token()`, `csrf_field()`
- Supports multiple token fields and headers
- **Files**:
  - [src/Framework/Security/CsrfTokenManagerInterface.php](src/Framework/Security/CsrfTokenManagerInterface.php)
  - [src/Framework/Security/SessionCsrfTokenManager.php](src/Framework/Security/SessionCsrfTokenManager.php)
  - [src/Framework/Http/Middleware/CsrfProtection.php](src/Framework/Http/Middleware/CsrfProtection.php)

### ✅ XSS Protection
- HTML escaping utilities
- Tag sanitization (allow specific tags)
- Rich text purification
- Context-specific escaping (JS, URL)
- Security headers middleware
- **Files**:
  - [src/Framework/Security/XssProtection.php](src/Framework/Security/XssProtection.php)
  - [src/Framework/Http/Middleware/AddSecurityHeaders.php](src/Framework/Http/Middleware/AddSecurityHeaders.php)

### ✅ Authorization System
- Gates (closure-based authorization)
- Policies (class-based authorization)
- Multiple ability checks
- Authorization middleware
- **Files**:
  - [src/Framework/Auth/GateInterface.php](src/Framework/Auth/GateInterface.php)
  - [src/Framework/Auth/Gate.php](src/Framework/Auth/Gate.php)
  - [src/Framework/Auth/AuthorizationException.php](src/Framework/Auth/AuthorizationException.php)
  - [src/Framework/Http/Middleware/Authorize.php](src/Framework/Http/Middleware/Authorize.php)

### ✅ SQL Injection Prevention
- Query Builder with automatic parameter binding
- ORM with prepared statements
- No raw SQL concatenation

## Infrastructure Features

### ✅ Cache System
**Multiple Drivers:**
- **FileCache** - Filesystem-based (no dependencies)
- **RedisCache** - High-performance (requires ext-redis)
- **MemoryCache** - In-memory for testing

**Features:**
- PSR-16 inspired interface
- Remember pattern (get or generate)
- Increment/Decrement operations
- Multiple operations (getMultiple, setMultiple)
- TTL support
- **Files**:
  - [src/Framework/Cache/CacheInterface.php](src/Framework/Cache/CacheInterface.php)
  - [src/Framework/Cache/FileCache.php](src/Framework/Cache/FileCache.php)
  - [src/Framework/Cache/RedisCache.php](src/Framework/Cache/RedisCache.php)
  - [src/Framework/Cache/MemoryCache.php](src/Framework/Cache/MemoryCache.php)
  - [src/Framework/Cache/CacheManager.php](src/Framework/Cache/CacheManager.php)

### ✅ Cookie Management
- Immutable Cookie value object
- Encrypted cookie support (AES-256-CBC)
- Security options (HttpOnly, Secure, SameSite)
- Cookie Jar for queuing
- **Files**:
  - [src/Framework/Http/Cookie.php](src/Framework/Http/Cookie.php)
  - [src/Framework/Http/CookieJar.php](src/Framework/Http/CookieJar.php)

### ✅ Rate Limiting
- Cache-based rate limiter
- Sliding window algorithm
- Throttle middleware
- Automatic user identification (auth or IP)
- Response headers (X-RateLimit-*)
- **Files**:
  - [src/Framework/RateLimit/RateLimiterInterface.php](src/Framework/RateLimit/RateLimiterInterface.php)
  - [src/Framework/RateLimit/CacheRateLimiter.php](src/Framework/RateLimit/CacheRateLimiter.php)
  - [src/Framework/Http/Middleware/ThrottleRequests.php](src/Framework/Http/Middleware/ThrottleRequests.php)

### ✅ Queue System
**Multiple Drivers:**
- **SyncQueue** - Immediate execution (testing)
- **DatabaseQueue** - Database-backed persistence
- **RedisQueue** - High-performance with delayed jobs

**Features:**
- Job classes with lifecycle hooks
- Retry mechanism
- Failed job handling
- Queue worker
- Delayed job execution
- **Files**:
  - [src/Framework/Queue/JobInterface.php](src/Framework/Queue/JobInterface.php)
  - [src/Framework/Queue/Job.php](src/Framework/Queue/Job.php)
  - [src/Framework/Queue/QueueInterface.php](src/Framework/Queue/QueueInterface.php)
  - [src/Framework/Queue/SyncQueue.php](src/Framework/Queue/SyncQueue.php)
  - [src/Framework/Queue/DatabaseQueue.php](src/Framework/Queue/DatabaseQueue.php)
  - [src/Framework/Queue/RedisQueue.php](src/Framework/Queue/RedisQueue.php)
  - [src/Framework/Queue/QueueManager.php](src/Framework/Queue/QueueManager.php)
  - [src/Framework/Queue/Worker.php](src/Framework/Queue/Worker.php)

### ✅ Task Scheduler
- Cron-like task scheduling
- Fluent frequency API
- Conditional execution
- Timezone support
- Job queueing integration
- **Files**:
  - [src/Framework/Schedule/ScheduledTask.php](src/Framework/Schedule/ScheduledTask.php)
  - [src/Framework/Schedule/Scheduler.php](src/Framework/Schedule/Scheduler.php)

## Core Framework Features

### ✅ Dependency Injection Container
- PSR-11 inspired
- Auto-wiring with reflection
- Singleton and factory patterns
- Circular dependency detection
- Method injection

### ✅ HTTP Layer
- Request/Response abstraction
- Full interface-based design
- Input validation helpers
- Multiple response types (HTML, JSON, redirect, download)

### ✅ Routing System
- RESTful verbs
- Route parameters with regex
- Named routes
- Middleware support (global + per-route)
- Automatic dependency injection

### ✅ Middleware Pipeline
- Interface-based design
- AbstractMiddleware with hooks
- Before/After hooks
- Short-circuit support
- Middleware aliases

### ✅ Event System
- Priority-based execution
- Event propagation control
- Event objects and generic events
- Subscriber pattern

### ✅ Database & ORM
- Query Builder (fluent interface)
- Active Record ORM
- Model relationships (hasOne, hasMany, belongsTo, belongsToMany)
- Eager loading (prevent N+1)
- Migrations & Schema Builder
- Multiple database drivers (MySQL, PostgreSQL, SQLite)

### ✅ Collections
- **Collection** (eager) - Full dataset in memory
- **LazyCollection** (lazy) - Generator-based for large datasets
- Rich functional API (map, filter, reduce, etc.)
- Statistical operations
- Set operations

### ✅ Authentication
- Multi-guard support (Session, Token)
- User provider abstraction
- Authenticatable trait

### ✅ Support Utilities
- Result monad for error handling
- String manipulation (Str, Stringable)
- Helper functions

### ✅ Service Accessor Pattern
- Static-like interface to IoC container services
- Not true static - forwards to container instances
- Full IDE autocomplete support
- Testable (can swap implementations)
- SOLID compliant architecture
- **Available Accessors:**
  - `Cache` - CacheManager access
  - `Event` - EventDispatcher access
  - `DB` - Database Connection access
  - `Auth` - AuthManager access
  - `Gate` - Authorization Gate access
  - `Queue` - QueueManager access
  - `Schedule` - Scheduler access
- **Files:**
  - [src/Framework/Foundation/ServiceAccessor.php](src/Framework/Foundation/ServiceAccessor.php)
  - [src/Framework/Support/Accessors/](src/Framework/Support/Accessors/)
  - [docs/SERVICE_ACCESSOR.md](docs/SERVICE_ACCESSOR.md)

## Service Providers

### ✅ Framework Providers
- ConfigServiceProvider
- HttpServiceProvider
- EventServiceProvider
- RoutingServiceProvider
- DatabaseServiceProvider
- AuthServiceProvider
- **SecurityServiceProvider** (NEW)
- **CacheServiceProvider** (NEW)
- **QueueServiceProvider** (NEW)
- **ScheduleServiceProvider** (NEW)

### ✅ Application Providers
- AppServiceProvider
- RepositoryServiceProvider
- EventServiceProvider
- RouteServiceProvider

## Configuration Files

- [config/app.php](config/app.php) - Application settings
- [config/auth.php](config/auth.php) - Authentication
- [config/database.php](config/database.php) - Database connections
- [config/middleware.php](config/middleware.php) - Middleware aliases
- **[config/security.php](config/security.php)** - Security settings (NEW)
- **[config/cache.php](config/cache.php)** - Cache drivers (NEW)
- **[config/queue.php](config/queue.php)** - Queue connections (NEW)

## Helper Functions

### Existing
- `app()` - Get app instance or resolve service
- `event()` - Dispatch events
- `auth()` - Get auth service
- `container()` - Get container or resolve service
- `config()` - Get config value
- `env()` - Get environment variable

### New
- `csrf_token()` - Generate CSRF token
- `csrf_field()` - Generate CSRF hidden input
- `e()` - Escape HTML
- `clean()` - Remove all HTML tags
- `cache()` - Get cache instance or cached value

## Design Patterns Used

1. **Dependency Injection** - Container with auto-wiring
2. **Repository Pattern** - Domain interfaces, Infrastructure implementations
3. **Service Provider Pattern** - Two-phase registration (register/boot)
4. **Strategy Pattern** - Multiple drivers (Cache, Queue, Database)
5. **Factory Pattern** - Manager classes (CacheManager, QueueManager)
6. **Command Pattern** - Queue jobs
7. **Observer Pattern** - Event system
8. **Middleware Pattern** - HTTP request pipeline
9. **Active Record** - ORM models
10. **Value Object** - Cookie, Response, Request
11. **Monad Pattern** - Result type

## SOLID Principles

✅ **Single Responsibility Principle**
- Each class has one reason to change
- Focused interfaces and implementations

✅ **Open/Closed Principle**
- Extend via interfaces and inheritance
- Strategy pattern for drivers

✅ **Liskov Substitution Principle**
- All implementations fulfill contracts
- Polymorphic driver usage

✅ **Interface Segregation Principle**
- Small, focused interfaces
- No fat interfaces

✅ **Dependency Inversion Principle**
- Depend on abstractions, not concretions
- All dependencies injected via interfaces

## Architecture Layers

1. **Domain Layer** (`src/App/Domain/`)
   - Pure business entities
   - Repository interfaces
   - No framework dependencies

2. **Application Layer** (`src/App/Application/`)
   - Use cases (Command/Handler)
   - Business logic
   - Framework-independent

3. **Infrastructure Layer** (`src/App/Infrastructure/`)
   - Repository implementations
   - External services
   - Framework adapters

4. **Presentation Layer** (`src/App/Presentation/`)
   - Controllers (MVC)
   - Actions (ADR)
   - Middleware
   - Views

5. **Framework Layer** (`src/Framework/`)
   - Reusable components
   - No application logic
   - Highly testable

## Testing Support

- **MemoryCache** - In-memory cache for tests
- **SyncQueue** - Immediate execution for tests
- Repository pattern - Easy to mock
- Dependency injection - Easy to swap implementations
- Interface-based design - Test doubles

## Documentation

- **[CLAUDE.md](CLAUDE.md)** - Comprehensive framework guide
- **[EXAMPLES.md](EXAMPLES.md)** - Code examples for all features
- **[INSTALLATION.md](INSTALLATION.md)** - Setup and deployment guide
- **[FEATURES.md](FEATURES.md)** - This file

## Performance Features

- **Lazy collections** - Memory-efficient for large datasets
- **Query caching** - Cache database results
- **Redis support** - High-performance cache and queue
- **Connection pooling** - Database connections
- **Singleton services** - Expensive objects cached

## Security Features Summary

✅ CSRF Protection
✅ XSS Prevention
✅ SQL Injection Prevention (automatic)
✅ Authorization (Gates & Policies)
✅ Rate Limiting
✅ Secure Headers (CSP, HSTS, X-Frame-Options)
✅ Cookie Encryption
✅ Input Validation
✅ Output Escaping

## Production Ready

✅ Clean Architecture
✅ SOLID Principles
✅ PSR-inspired interfaces
✅ Comprehensive error handling
✅ Security best practices
✅ Performance optimizations
✅ Extensive documentation
✅ Testable design
