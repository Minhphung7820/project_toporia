# Example Pipeline Pipes

This directory contains example pipe implementations for the Pipeline system.

**These are APPLICATION-LEVEL examples, not part of the framework core.**

## Purpose

These examples demonstrate:
- How to implement `PipeInterface`
- How to use dependency injection in pipes
- Different pipe patterns (validation, transformation, enrichment)
- Best practices for pipe design

## Example Pipes

### ValidateUser
Validates user data (email format, name length).
Demonstrates validation pipe with error throwing.

### NormalizeData
Normalizes user data (lowercase email, capitalize name).
Demonstrates transformation pipe.

### EnrichUserData
Adds timestamps, default role, and verified flag.
Demonstrates enrichment pipe with constructor injection.

## Usage

```php
use App\Pipes\{ValidateUser, NormalizeData, EnrichUserData};

$user = pipeline($userData)
    ->through([
        ValidateUser::class,
        NormalizeData::class,
        EnrichUserData::class
    ])
    ->thenReturn();
```

## Creating Your Own Pipes

1. Implement `PipeInterface`:
```php
use Toporia\Framework\Pipeline\Contracts\PipeInterface;

class MyPipe implements PipeInterface
{
    public function handle(mixed $data, Closure $next): mixed
    {
        // Process data
        return $next($data);
    }
}
```

2. Use in pipeline:
```php
pipeline($data)->through([MyPipe::class])->thenReturn();
```

See [docs/PIPELINE.md](../../../../docs/PIPELINE.md) for complete documentation.
