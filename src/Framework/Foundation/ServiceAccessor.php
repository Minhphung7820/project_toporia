<?php

declare(strict_types=1);

namespace Toporia\Framework\Foundation;

use RuntimeException;
use Toporia\Framework\Container\ContainerInterface;

/**
 * Service Accessor - Facade Pattern for IoC Container Services
 *
 * Provides a convenient static-like interface to access services from the IoC container.
 * This is NOT a true static class - it forwards calls to actual instances in the container.
 *
 * Performance Characteristics:
 * - O(1) instance lookup after first resolution (cached per accessor)
 * - Zero overhead method forwarding via __callStatic
 * - No reflection after initial resolution
 * - Minimal memory footprint (one instance per accessor class)
 *
 * Benefits:
 * - Clean, expressive syntax: Cache::get('key') vs app('cache')->get('key')
 * - IDE autocomplete support via concrete accessor classes
 * - Type safety through concrete accessor methods
 * - Fully testable (swap implementations via swap())
 * - No global state (all state in container)
 * - Hot path optimization (cached instances)
 *
 * SOLID Principles Applied:
 * - Single Responsibility: Only forwards calls to container services
 * - Open/Closed: Extend via new accessor classes, don't modify base
 * - Liskov Substitution: All accessors behave consistently
 * - Interface Segregation: Each accessor provides specific service interface
 * - Dependency Inversion: Depends on ContainerInterface abstraction
 *
 * Architecture Pattern: Facade + Service Locator hybrid
 * - Facade: Provides simplified interface to complex subsystem
 * - Service Locator: Resolves dependencies from container
 * - Lazy Loading: Services resolved only when first accessed
 *
 * @example Basic Usage
 * // Instead of:
 * $cache = app('cache');
 * $cache->get('key');
 *
 * // Use accessor:
 * Cache::get('key');
 *
 * @example Testing
 * $mock = new MemoryCacheMock();
 * Cache::swap($mock);
 * Cache::set('key', 'value'); // Uses mock
 * Cache::clearResolved(); // Cleanup
 *
 * @example Performance Monitoring
 * echo ServiceAccessor::getResolvedCount(); // Check memory usage
 *
 * @internal Flow:
 * Cache::get('key') → __callStatic('get', ['key'])
 *                   → resolveService() [cached after first call]
 *                   → $instance->get('key')
 */
abstract class ServiceAccessor
{
    /**
     * Container instance.
     */
    private static ?ContainerInterface $container = null;

    /**
     * Resolved service instances (per accessor class).
     *
     * @var array<string, object>
     */
    private static array $resolvedInstances = [];

    /**
     * Set the container instance.
     *
     * This should be called once during application bootstrap.
     *
     * @param ContainerInterface $container
     * @return void
     */
    public static function setContainer(ContainerInterface $container): void
    {
        self::$container = $container;
    }

    /**
     * Get the container instance.
     *
     * @return ContainerInterface
     * @throws RuntimeException If container not set
     */
    public static function getContainer(): ContainerInterface
    {
        if (self::$container === null) {
            throw new RuntimeException(
                'Container not set. Call ServiceAccessor::setContainer() during bootstrap.'
            );
        }

        return self::$container;
    }

    /**
     * Get the service accessor name (container binding key).
     *
     * Each concrete accessor must implement this to specify which service it accesses.
     *
     * @return string Service name in container (e.g., 'cache', 'events', 'db')
     */
    abstract protected static function getServiceName(): string;

    /**
     * Get the underlying service instance from container.
     *
     * Resolves once and caches the instance per request.
     *
     * Performance: O(1) lookup after first resolution (cached)
     *
     * @return object Service instance
     * @throws RuntimeException If service not found in container
     */
    protected static function resolveService(): object
    {
        $accessorClass = static::class;

        // Fast path: Return cached instance if already resolved (O(1))
        if (isset(self::$resolvedInstances[$accessorClass])) {
            return self::$resolvedInstances[$accessorClass];
        }

        // Slow path: Resolve from container (only happens once per accessor)
        $container = self::getContainer();
        $serviceName = static::getServiceName();

        if (!$container->has($serviceName)) {
            throw new RuntimeException(
                "Service '{$serviceName}' not found in container. "
                . "Register it in a ServiceProvider first."
            );
        }

        // Resolve and cache for future calls
        $instance = $container->get($serviceName);
        self::$resolvedInstances[$accessorClass] = $instance;

        return $instance;
    }

    /**
     * Get the underlying service instance (alias for resolveService).
     *
     * Provides backward compatibility and cleaner API for child classes.
     *
     * Performance: O(1) - delegates to resolveService() with caching
     *
     * @return object Service instance
     */
    protected static function getService(): object
    {
        return static::resolveService();
    }

    /**
     * Handle dynamic static method calls.
     *
     * Forwards all static method calls to the underlying service instance.
     *
     * Performance:
     * - O(1) instance lookup (cached)
     * - Direct method call forwarding (no overhead)
     * - Lazy method_exists check only on first call per method
     *
     * @param string $method Method name
     * @param array<mixed> $arguments Method arguments
     * @return mixed Method result
     * @throws RuntimeException If method doesn't exist on service
     */
    public static function __callStatic(string $method, array $arguments): mixed
    {
        // Get cached service instance (O(1))
        $instance = static::resolveService();

        // Direct method call - fastest path
        // Let PHP handle method_exists check naturally via error
        // This is faster than explicit method_exists() call
        return $instance->$method(...$arguments);
    }

    /**
     * Get the underlying service instance directly.
     *
     * Useful when you need the actual instance (e.g., for type hints, instanceof checks).
     *
     * Performance: O(1) - uses cached instance
     *
     * @return object Service instance
     *
     * @example
     * $cache = Cache::getInstance();
     * if ($cache instanceof RedisCache) {
     *     $cache->someRedisSpecificMethod();
     * }
     */
    public static function getInstance(): object
    {
        return static::resolveService();
    }

    /**
     * Get the service name this accessor is bound to.
     *
     * Exposes the protected method for debugging/introspection.
     *
     * @return string Service name in container
     */
    public static function getFacadeAccessor(): string
    {
        return static::getServiceName();
    }

    /**
     * Clear all resolved instances across all accessors.
     *
     * Useful for testing - forces re-resolution from container.
     *
     * Performance: O(1) - just resets array
     *
     * @return void
     *
     * @example
     * // Between tests:
     * ServiceAccessor::clearResolvedInstances();
     */
    public static function clearResolvedInstances(): void
    {
        self::$resolvedInstances = [];
    }

    /**
     * Clear resolved instance for this specific accessor only.
     *
     * More granular than clearResolvedInstances() - only affects one accessor.
     *
     * Performance: O(1)
     *
     * @return void
     */
    public static function clearResolved(): void
    {
        unset(self::$resolvedInstances[static::class]);
    }

    /**
     * Swap the underlying service implementation.
     *
     * Useful for testing - temporarily replace service with mock/stub.
     *
     * Performance: O(1) - direct array assignment
     *
     * @param object $mock Mock/stub instance
     * @return void
     *
     * @example
     * // In tests:
     * $mockCache = new MemoryCache();
     * Cache::swap($mockCache);
     * Cache::set('key', 'value'); // Uses mock
     *
     * // Cleanup after test:
     * Cache::clearResolved();
     */
    public static function swap(object $mock): void
    {
        self::$resolvedInstances[static::class] = $mock;
    }

    /**
     * Check if service is currently resolved.
     *
     * Performance: O(1) - array key check
     *
     * @return bool True if resolved and cached
     */
    public static function isResolved(): bool
    {
        return isset(self::$resolvedInstances[static::class]);
    }

    /**
     * Get count of resolved accessor instances.
     *
     * Useful for debugging memory usage and performance monitoring.
     *
     * @return int Number of resolved accessors
     */
    public static function getResolvedCount(): int
    {
        return count(self::$resolvedInstances);
    }
}
