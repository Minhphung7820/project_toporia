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
}
