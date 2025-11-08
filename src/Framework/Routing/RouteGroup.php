<?php

declare(strict_types=1);

namespace Toporia\Framework\Routing;

/**
 * Route Group
 *
 * Allows grouping routes with shared attributes (prefix, middleware, namespace).
 * Follows Builder pattern for fluent API.
 */
final class RouteGroup
{
    private string $prefix = '';
    private array $middleware = [];
    private ?string $namespace = null;
    private ?string $name = null;

    public function __construct(
        private readonly RouterInterface $router
    ) {}

    /**
     * Set prefix for all routes in group
     *
     * @param string $prefix
     * @return self
     */
    public function prefix(string $prefix): self
    {
        $this->prefix = '/' . trim($prefix, '/');
        return $this;
    }

    /**
     * Set middleware for all routes in group
     *
     * @param array<string> $middleware
     * @return self
     */
    public function middleware(array $middleware): self
    {
        $this->middleware = array_merge($this->middleware, $middleware);
        return $this;
    }

    /**
     * Set namespace for controllers in group
     *
     * @param string $namespace
     * @return self
     */
    public function namespace(string $namespace): self
    {
        $this->namespace = rtrim($namespace, '\\');
        return $this;
    }

    /**
     * Set name prefix for routes in group
     *
     * @param string $name
     * @return self
     */
    public function name(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Execute callback with group attributes
     *
     * @param callable $callback
     * @return void
     */
    public function group(callable $callback): void
    {
        // Store current group state
        $previousPrefix = $this->router->getCurrentPrefix();
        $previousMiddleware = $this->router->getCurrentMiddleware();
        $previousNamespace = $this->router->getCurrentNamespace();
        $previousNamePrefix = $this->router->getCurrentNamePrefix();

        // Apply group attributes
        $this->router->setCurrentPrefix($previousPrefix . $this->prefix);
        $this->router->setCurrentMiddleware(array_merge($previousMiddleware, $this->middleware));

        if ($this->namespace !== null) {
            $this->router->setCurrentNamespace($this->namespace);
        }

        if ($this->name !== null) {
            $this->router->setCurrentNamePrefix($previousNamePrefix . $this->name);
        }

        // Execute callback
        $callback($this->router);

        // Restore previous state
        $this->router->setCurrentPrefix($previousPrefix);
        $this->router->setCurrentMiddleware($previousMiddleware);
        $this->router->setCurrentNamespace($previousNamespace);
        $this->router->setCurrentNamePrefix($previousNamePrefix);
    }
}
