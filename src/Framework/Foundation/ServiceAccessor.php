<?php

declare(strict_types=1);

namespace Toporia\Framework\Foundation;

use RuntimeException;
use Toporia\Framework\Container\ContainerInterface;

/**
 * Service Accessor - Base class for service access pattern.
 *
 * Provides a convenient static-like interface to access services from the IoC container.
 * This is NOT a true static class - it forwards calls to actual instances in the container.
 *
 * Benefits:
 * - Clean, expressive syntax: Cache::get('key') instead of app('cache')->get('key')
 * - IDE autocomplete support (via concrete accessor classes)
 * - Type safety through concrete accessor methods
 * - Testable (can swap implementations via container)
 * - No global state (uses container, not static properties)
 *
 * Following SOLID principles:
 * - Single Responsibility: Only forwards calls to container services
 * - Open/Closed: Extend via new accessor classes, don't modify base
 * - Liskov Substitution: All accessors behave consistently
 * - Interface Segregation: Each accessor provides specific service interface
 * - Dependency Inversion: Depends on ContainerInterface abstraction
 *
 * @example
 * // Instead of:
 * $cache = app('cache');
 * $cache->get('key');
 *
 * // Use:
 * Cache::get('key');
 *
 * // Behind the scenes:
 * Cache::get('key') → ServiceAccessor::__callStatic('get', ['key'])
 *                   → container()->get('cache')->get('key')
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
     * @return object Service instance
     * @throws RuntimeException If service not found in container
     */
    protected static function resolveService(): object
    {
        $accessorClass = static::class;

        // Return cached instance if already resolved
        if (isset(self::$resolvedInstances[$accessorClass])) {
            return self::$resolvedInstances[$accessorClass];
        }

        $container = self::getContainer();
        $serviceName = static::getServiceName();

        if (!$container->has($serviceName)) {
            throw new RuntimeException(
                "Service '{$serviceName}' not found in container. "
                . "Register it in a ServiceProvider first."
            );
        }

        // Resolve and cache
        $instance = $container->get($serviceName);
        self::$resolvedInstances[$accessorClass] = $instance;

        return $instance;
    }

    /**
     * Handle dynamic static method calls.
     *
     * Forwards all static method calls to the underlying service instance.
     *
     * @param string $method Method name
     * @param array<mixed> $arguments Method arguments
     * @return mixed Method result
     * @throws RuntimeException If method doesn't exist on service
     */
    public static function __callStatic(string $method, array $arguments): mixed
    {
        $instance = static::resolveService();

        if (!method_exists($instance, $method)) {
            $serviceName = static::getServiceName();
            $instanceClass = get_class($instance);

            throw new RuntimeException(
                "Method '{$method}' does not exist on service '{$serviceName}' ({$instanceClass})"
            );
        }

        return $instance->$method(...$arguments);
    }

    /**
     * Get the underlying service instance directly.
     *
     * Useful when you need the actual instance (e.g., for type hints, instanceof checks).
     *
     * @return object Service instance
     *
     * @example
     * $cache = Cache::getInstance();
     * if ($cache instanceof RedisCache) { ... }
     */
    public static function getInstance(): object
    {
        return static::resolveService();
    }

    /**
     * Clear all resolved instances.
     *
     * Useful for testing - forces re-resolution from container.
     *
     * @return void
     */
    public static function clearResolvedInstances(): void
    {
        self::$resolvedInstances = [];
    }

    /**
     * Swap the underlying service implementation.
     *
     * Useful for testing - temporarily replace service with mock/stub.
     *
     * @param object $mock Mock/stub instance
     * @return void
     *
     * @example
     * // In tests:
     * $mockCache = new MemoryCache();
     * Cache::swap($mockCache);
     * Cache::set('key', 'value'); // Uses mock
     */
    public static function swap(object $mock): void
    {
        self::$resolvedInstances[static::class] = $mock;
    }

    /**
     * Check if service is currently resolved.
     *
     * @return bool True if resolved
     */
    public static function isResolved(): bool
    {
        return isset(self::$resolvedInstances[static::class]);
    }
}
