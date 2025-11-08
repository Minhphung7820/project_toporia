<?php

declare(strict_types=1);

namespace Toporia\Framework\Routing;

/**
 * HTTP Router interface.
 */
interface RouterInterface
{
    /**
     * Register a GET route.
     *
     * @param string $path URI pattern.
     * @param mixed $handler Route handler.
     * @param array<string> $middleware Middleware classes.
     * @return RouteInterface
     */
    public function get(string $path, mixed $handler, array $middleware = []): RouteInterface;

    /**
     * Register a POST route.
     *
     * @param string $path URI pattern.
     * @param mixed $handler Route handler.
     * @param array<string> $middleware Middleware classes.
     * @return RouteInterface
     */
    public function post(string $path, mixed $handler, array $middleware = []): RouteInterface;

    /**
     * Register a PUT route.
     *
     * @param string $path URI pattern.
     * @param mixed $handler Route handler.
     * @param array<string> $middleware Middleware classes.
     * @return RouteInterface
     */
    public function put(string $path, mixed $handler, array $middleware = []): RouteInterface;

    /**
     * Register a PATCH route.
     *
     * @param string $path URI pattern.
     * @param mixed $handler Route handler.
     * @param array<string> $middleware Middleware classes.
     * @return RouteInterface
     */
    public function patch(string $path, mixed $handler, array $middleware = []): RouteInterface;

    /**
     * Register a DELETE route.
     *
     * @param string $path URI pattern.
     * @param mixed $handler Route handler.
     * @param array<string> $middleware Middleware classes.
     * @return RouteInterface
     */
    public function delete(string $path, mixed $handler, array $middleware = []): RouteInterface;

    /**
     * Register a route for any HTTP method.
     *
     * @param string $path URI pattern.
     * @param mixed $handler Route handler.
     * @param array<string> $middleware Middleware classes.
     * @return RouteInterface
     */
    public function any(string $path, mixed $handler, array $middleware = []): RouteInterface;

    /**
     * Dispatch the current request and execute the matched route.
     *
     * @return void
     */
    public function dispatch(): void;

    /**
     * Create a route group with shared attributes
     *
     * @param array<string, mixed> $attributes Group attributes (prefix, middleware, namespace, name)
     * @param callable $callback Callback to define routes
     * @return void
     */
    public function group(array $attributes, callable $callback): void;

    /**
     * Get current prefix (for group nesting)
     *
     * @return string
     */
    public function getCurrentPrefix(): string;

    /**
     * Set current prefix (for group nesting)
     *
     * @param string $prefix
     * @return void
     */
    public function setCurrentPrefix(string $prefix): void;

    /**
     * Get current middleware stack (for group nesting)
     *
     * @return array<string>
     */
    public function getCurrentMiddleware(): array;

    /**
     * Set current middleware stack (for group nesting)
     *
     * @param array<string> $middleware
     * @return void
     */
    public function setCurrentMiddleware(array $middleware): void;

    /**
     * Get current namespace (for group nesting)
     *
     * @return string|null
     */
    public function getCurrentNamespace(): ?string;

    /**
     * Set current namespace (for group nesting)
     *
     * @param string|null $namespace
     * @return void
     */
    public function setCurrentNamespace(?string $namespace): void;

    /**
     * Get current name prefix (for group nesting)
     *
     * @return string
     */
    public function getCurrentNamePrefix(): string;

    /**
     * Set current name prefix (for group nesting)
     *
     * @param string $prefix
     * @return void
     */
    public function setCurrentNamePrefix(string $prefix): void;
}
