# Error Handling - Beautiful Error Pages

Professional error handling system inspired by **Whoops** and **Ignition** (Laravel).

## Features

✅ **Beautiful HTML Error Pages**
- Syntax-highlighted code context
- Full stack trace with file links
- Request/server information
- Modern, clean UI

✅ **JSON API Responses**
- Automatic JSON detection
- Debug vs production modes
- Clean error format

✅ **Environment-Aware**
- Detailed errors in development
- Simple messages in production
- Configurable via `APP_DEBUG`

✅ **Performance Optimized**
- O(1) in production (simple page)
- O(N) in development where N = stack frames (acceptable for debugging)
- Minimal overhead

✅ **Clean Architecture**
- Interface-based design (SOLID)
- Pluggable renderers
- PSR-compatible structure

---

## Configuration

### Enable/Disable Debug Mode

Edit `.env`:

```env
# Development - Show detailed errors
APP_DEBUG=true

# Production - Hide error details
APP_DEBUG=false
```

---

## Error Page Examples

### Development Mode (HTML)

Beautiful error page with:

1. **Exception Header**
   - Exception class name
   - Error message
   - File and line number

2. **Code Context**
   - 10 lines before/after error
   - Syntax highlighting
   - Error line highlighted in red
   - Line numbers

3. **Stack Trace**
   - All stack frames
   - File locations with line numbers
   - Function/method calls
   - Expandable frames

4. **Request Information**
   - HTTP method (GET, POST, etc.)
   - Request URI
   - IP address
   - User agent

**Example:**

```
╔════════════════════════════════════════════════════════╗
║  ErrorException                                        ║
║  Undefined variable: products                          ║
║  at /path/to/HomeController.php:42                     ║
╚════════════════════════════════════════════════════════╝

┌─ Code Context ────────────────────────────────────────┐
│ 37 │     public function index(Request $request)      │
│ 38 │     {                                             │
│ 39 │         $query = DB::table('products');          │
│ 40 │                                                   │
│ 41 │         // Oops, typo here!                      │
│ 42 │ ❌      return $this->json(['data' => $produts]); │
│ 43 │     }                                             │
│ 44 │ }                                                 │
└───────────────────────────────────────────────────────┘

Stack Trace:
1. HomeController->index()
   at /path/to/HomeController.php:42

2. Router->dispatch()
   at /path/to/Router.php:235

3. Application->run()
   at /path/to/index.php:10
```

### Production Mode (HTML)

Simple, secure error page:

```html
500 - Server Error
Oops! Something went wrong on our end.
```

**No sensitive information exposed!**

### API Errors (JSON)

#### Development Mode:

```json
{
  "error": {
    "message": "Undefined variable: products",
    "exception": "ErrorException",
    "file": "/path/to/HomeController.php",
    "line": 42,
    "trace": [
      {
        "file": "/path/to/HomeController.php",
        "line": 42,
        "function": "HomeController->index"
      },
      {
        "file": "/path/to/Router.php",
        "line": 235,
        "function": "Router->dispatch"
      }
    ]
  }
}
```

#### Production Mode:

```json
{
  "error": {
    "message": "Internal Server Error",
    "code": 500
  }
}
```

---

## How It Works

### 1. Error Handler Registration

In [bootstrap/app.php](../bootstrap/app.php:49-51):

```php
$debug = ($_ENV['APP_DEBUG'] ?? 'true') === 'true';
$errorHandler = new \Toporia\Framework\Error\ErrorHandler($debug);
$errorHandler->register();
```

### 2. Automatic Error Detection

The handler automatically detects request type and renders appropriate response:

**HTML for Web Requests:**
- User-Agent: Browser
- Accept: `text/html`
- URL: Not starting with `/api`

**JSON for API Requests:**
- Accept: `application/json`
- Header: `X-Requested-With: XMLHttpRequest`
- URL: Starting with `/api`

### 3. Error Conversion

All PHP errors are converted to exceptions:

```php
// PHP Error (old style)
$undefinedVariable;

// Converted to:
ErrorException: Undefined variable: undefinedVariable
```

### 4. Fatal Error Handling

Fatal errors are caught via shutdown handler:

```php
register_shutdown_function([$errorHandler, 'handleShutdown']);
```

---

## Architecture

### Class Diagram

```
ErrorHandlerInterface
    ↑
    │ implements
    │
ErrorHandler ──────► ErrorRendererInterface
                         ↑
                         │ implements
                    ┌────┴────┐
                    │         │
            HtmlErrorRenderer  JsonErrorRenderer
```

### SOLID Principles

**Single Responsibility:**
- `ErrorHandler`: Catches and routes errors
- `HtmlErrorRenderer`: Renders HTML
- `JsonErrorRenderer`: Renders JSON

**Open/Closed:**
- Extensible: Create custom renderers
- Closed for modification: Core logic unchanged

**Liskov Substitution:**
- All renderers implement `ErrorRendererInterface`
- Interchangeable without breaking system

**Interface Segregation:**
- Small, focused interfaces
- `ErrorHandlerInterface`: 4 methods
- `ErrorRendererInterface`: 1 method

**Dependency Inversion:**
- `ErrorHandler` depends on `ErrorRendererInterface` (abstraction)
- Not on concrete `HtmlErrorRenderer` or `JsonErrorRenderer`

---

## Custom Error Rendering

### Create Custom Renderer

```php
namespace App\Error;

use Toporia\Framework\Error\ErrorRendererInterface;
use Throwable;

class SlackErrorRenderer implements ErrorRendererInterface
{
    public function __construct(
        private string $webhookUrl
    ) {}

    public function render(Throwable $exception): void
    {
        // Send to Slack
        $message = sprintf(
            "*Error:* %s\n*File:* %s:%d",
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine()
        );

        // Post to Slack webhook
        $this->postToSlack($message);

        // Then render HTML
        (new HtmlErrorRenderer(true))->render($exception);
    }

    private function postToSlack(string $message): void
    {
        // Implementation...
    }
}
```

### Use Custom Renderer

```php
$errorHandler = new ErrorHandler(
    debug: true,
    renderer: new SlackErrorRenderer('https://hooks.slack.com/...')
);
```

---

## Performance

### Development Mode

**Time Complexity:**
- Error detection: O(1)
- Code context extraction: O(1) - reads ~20 lines
- Stack trace formatting: O(N) where N = stack depth (typically 5-20)
- Syntax highlighting: O(M) where M = lines in context
- **Total: O(N + M)** - acceptable for debugging

**Memory:**
- Loads ~20 lines of source code
- Stores stack trace (~1-5 KB typically)
- Renders HTML (~20-50 KB)
- **Total: ~50-100 KB** - minimal overhead

### Production Mode

**Time Complexity:**
- Error detection: O(1)
- Simple page render: O(1)
- **Total: O(1)** - no overhead

**Memory:**
- Simple HTML template
- **Total: ~2 KB** - negligible

---

## Error Logging

Errors are automatically logged via `error_log()`:

```php
// Log format
[2025-01-09 14:30:45] ErrorException: Undefined variable: products
in /path/to/HomeController.php:42
Stack trace:
#0 /path/to/Router.php(235): HomeController->index()
#1 /path/to/index.php(10): Router->dispatch()
```

**Log Location:**
- Development: `php://stderr` or error log
- Production: Configure via `error_log` in `php.ini`

---

## Security

### Production Mode

✅ **No sensitive information exposed:**
- No file paths
- No code snippets
- No stack traces
- No environment variables

❌ **Only shows:**
- Generic "500 Server Error" message
- No technical details

### Development Mode

⚠️ **Shows everything** (for debugging):
- Full file paths
- Source code
- Stack traces
- Request details

**Important:** Always set `APP_DEBUG=false` in production!

---

## Testing

### Test Error Handler

Create a test controller:

```php
final class TestController
{
    public function error()
    {
        // Undefined variable
        return response()->json(['data' => $undefinedVariable]);
    }

    public function fatal()
    {
        // Call undefined function
        undefinedFunction();
    }

    public function exception()
    {
        // Throw exception
        throw new \RuntimeException('Test exception');
    }
}
```

### Routes

```php
$router->get('/test/error', [TestController::class, 'error']);
$router->get('/test/fatal', [TestController::class, 'fatal']);
$router->get('/test/exception', [TestController::class, 'exception']);
```

### Test in Browser

```bash
# Start server
php -S localhost:8000 -t public

# Visit:
http://localhost:8000/test/error
http://localhost:8000/test/exception
```

### Test API Errors

```bash
# JSON response
curl -H "Accept: application/json" http://localhost:8000/test/error
```

---

## Comparison with Other Frameworks

| Feature | This Framework | Laravel (Ignition) | Symfony |
|---------|---------------|-------------------|---------|
| Beautiful error pages | ✅ | ✅ | ✅ |
| Syntax highlighting | ✅ | ✅ | ✅ |
| Stack trace | ✅ | ✅ | ✅ |
| Code context | ✅ (20 lines) | ✅ (10 lines) | ✅ |
| JSON API errors | ✅ | ✅ | ✅ |
| Auto-detection | ✅ | ✅ | ✅ |
| Production mode | ✅ | ✅ | ✅ |
| Custom renderers | ✅ | ✅ | ✅ |
| Performance | O(1) prod | O(1) prod | O(1) prod |
| Clean Architecture | ✅ | ✅ | ✅ |
| SOLID compliant | ✅ | ✅ | ✅ |

---

## Files

**Framework:**
- [src/Framework/Error/ErrorHandlerInterface.php](../src/Framework/Error/ErrorHandlerInterface.php)
- [src/Framework/Error/ErrorHandler.php](../src/Framework/Error/ErrorHandler.php)
- [src/Framework/Error/ErrorRendererInterface.php](../src/Framework/Error/ErrorRendererInterface.php)
- [src/Framework/Error/HtmlErrorRenderer.php](../src/Framework/Error/HtmlErrorRenderer.php)
- [src/Framework/Error/JsonErrorRenderer.php](../src/Framework/Error/JsonErrorRenderer.php)

**Bootstrap:**
- [bootstrap/app.php](../bootstrap/app.php:49-51) - Error handler registration

---

## Summary

✅ **Beautiful error pages** like Laravel Ignition
✅ **Clean Architecture** with SOLID principles
✅ **High Performance** - O(1) in production
✅ **Environment-aware** - debug vs production
✅ **API-friendly** - automatic JSON responses
✅ **Secure** - no sensitive info in production
✅ **Extensible** - custom renderers supported

**Happy Debugging! 🐛**
