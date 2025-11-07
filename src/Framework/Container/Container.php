<?php

declare(strict_types=1);

namespace Framework\Container;

use Framework\Container\Exception\ContainerException;
use Framework\Container\Exception\NotFoundException;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;

/**
 * Dependency Injection Container with auto-wiring support.
 *
 * Features:
 * - Service binding with factory functions
 * - Singleton pattern support
 * - Automatic dependency resolution (auto-wiring)
 * - Constructor injection
 * - Method injection via call()
 */
final class Container implements ContainerInterface
{
    /**
     * @var array<string, callable> Service factory bindings
     */
    private array $bindings = [];

    /**
     * @var array<string, mixed> Resolved singleton instances
     */
    private array $instances = [];

    /**
     * @var array<string, bool> Singleton markers
     */
    private array $singletons = [];

    /**
     * @var array<string, bool> Resolution stack to detect circular dependencies
     */
    private array $resolving = [];

    /**
     * {@inheritdoc}
     */
    public function get(string $id): mixed
    {
        // Check if already resolved as singleton
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        // Detect circular dependencies
        if (isset($this->resolving[$id])) {
            throw new ContainerException("Circular dependency detected while resolving '{$id}'");
        }

        $this->resolving[$id] = true;

        try {
            $instance = $this->resolve($id);

            // Cache if singleton
            if (isset($this->singletons[$id])) {
                $this->instances[$id] = $instance;
            }

            return $instance;
        } finally {
            unset($this->resolving[$id]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $id): bool
    {
        return isset($this->bindings[$id])
            || isset($this->instances[$id])
            || class_exists($id);
    }

    /**
     * {@inheritdoc}
     */
    public function bind(string $id, callable $factory): void
    {
        $this->bindings[$id] = $factory;

        // Remove from singletons and instances if re-binding
        unset($this->singletons[$id], $this->instances[$id]);
    }

    /**
     * {@inheritdoc}
     */
    public function singleton(string $id, callable $factory): void
    {
        $this->bindings[$id] = $factory;
        $this->singletons[$id] = true;

        // Remove existing instance to force recreation
        unset($this->instances[$id]);
    }

    /**
     * {@inheritdoc}
     */
    public function instance(string $id, mixed $instance): void
    {
        $this->instances[$id] = $instance;
        $this->singletons[$id] = true;

        // Remove factory binding as instance takes precedence
        unset($this->bindings[$id]);
    }

    /**
     * {@inheritdoc}
     */
    public function call(callable|array|string $callable, array $parameters = []): mixed
    {
        if (is_string($callable) && str_contains($callable, '::')) {
            $callable = explode('::', $callable, 2);
        }

        if (is_array($callable)) {
            [$class, $method] = $callable;

            // Resolve class if it's a string
            if (is_string($class)) {
                $class = $this->get($class);
            }

            $reflection = new ReflectionMethod($class, $method);
            $dependencies = $this->resolveMethodDependencies($reflection, $parameters);

            return $reflection->invokeArgs($class, $dependencies);
        }

        $reflection = new ReflectionFunction($callable);
        $dependencies = $this->resolveMethodDependencies($reflection, $parameters);

        return $reflection->invokeArgs($dependencies);
    }

    /**
     * Resolve a service from the container.
     *
     * @param string $id Service identifier.
     * @return mixed Resolved service.
     * @throws NotFoundException
     * @throws ContainerException
     */
    private function resolve(string $id): mixed
    {
        // Use factory if bound
        if (isset($this->bindings[$id])) {
            return ($this->bindings[$id])($this);
        }

        // Try auto-wiring
        if (class_exists($id)) {
            return $this->autowire($id);
        }

        throw new NotFoundException("Service '{$id}' not found in container");
    }

    /**
     * Automatically resolve and instantiate a class with its dependencies.
     *
     * @param string $className Full class name.
     * @return object Instantiated object.
     * @throws ContainerException
     */
    private function autowire(string $className): object
    {
        try {
            $reflection = new ReflectionClass($className);
        } catch (ReflectionException $e) {
            throw new ContainerException("Cannot reflect class '{$className}': {$e->getMessage()}", 0, $e);
        }

        if (!$reflection->isInstantiable()) {
            throw new ContainerException("Class '{$className}' is not instantiable");
        }

        $constructor = $reflection->getConstructor();

        // No constructor, just instantiate
        if ($constructor === null) {
            return new $className();
        }

        // Resolve constructor dependencies
        $dependencies = $this->resolveMethodDependencies($constructor, []);

        return $reflection->newInstanceArgs($dependencies);
    }

    /**
     * Resolve method/constructor dependencies.
     *
     * @param ReflectionMethod|ReflectionFunction $reflection
     * @param array $parameters Additional parameters provided by caller.
     * @return array Resolved dependencies.
     * @throws ContainerException
     */
    private function resolveMethodDependencies(
        ReflectionMethod|ReflectionFunction $reflection,
        array $parameters
    ): array {
        $dependencies = [];

        foreach ($reflection->getParameters() as $parameter) {
            $name = $parameter->getName();

            // Use provided parameter if available
            if (array_key_exists($name, $parameters)) {
                $dependencies[] = $parameters[$name];
                continue;
            }

            // Try to resolve by type
            $type = $parameter->getType();

            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $dependencies[] = $this->get($type->getName());
                continue;
            }

            // Use default value if available
            if ($parameter->isDefaultValueAvailable()) {
                $dependencies[] = $parameter->getDefaultValue();
                continue;
            }

            // Cannot resolve
            $context = $reflection instanceof ReflectionMethod
                ? $reflection->getDeclaringClass()->getName() . '::' . $reflection->getName()
                : $reflection->getName();

            throw new ContainerException(
                "Cannot resolve parameter '\${$name}' for {$context}"
            );
        }

        return $dependencies;
    }

    /**
     * Alias for get() method.
     * Provides backward compatibility with previous implementation.
     *
     * @param string $id Service identifier.
     * @return mixed
     * @throws NotFoundException
     * @throws ContainerException
     */
    public function make(string $id): mixed
    {
        return $this->get($id);
    }
}
