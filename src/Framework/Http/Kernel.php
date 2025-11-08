<?php

declare(strict_types=1);

namespace Toporia\Framework\Http;

use Toporia\Framework\Container\ContainerInterface;
use Toporia\Framework\Routing\RouterInterface;

/**
 * HTTP Kernel
 *
 * The HTTP Kernel handles incoming HTTP requests.
 * It manages global middleware and delegates routing to the router.
 */
class Kernel
{
    /**
     * Global middleware stack.
     * These middleware are run on every request.
     *
     * @var array<string>
     */
    protected array $middleware = [];

    /**
     * Middleware aliases for route-specific middleware.
     * Maps short names to middleware class names.
     *
     * @var array<string, string>
     */
    protected array $middlewareAliases = [];

    /**
     * @param ContainerInterface $container
     * @param RouterInterface $router
     */
    public function __construct(
        protected ContainerInterface $container,
        protected RouterInterface $router
    ) {}

    /**
     * Handle an incoming HTTP request.
     *
     * @param RequestInterface $request
     * @return void
     */
    public function handle(RequestInterface $request): void
    {
        // In future: Run global middleware here
        // For now, delegate directly to router
        $this->router->dispatch();
    }

    /**
     * Set global middleware.
     *
     * @param array<string> $middleware
     * @return self
     */
    public function setMiddleware(array $middleware): self
    {
        $this->middleware = $middleware;
        return $this;
    }

    /**
     * Get global middleware.
     *
     * @return array<string>
     */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    /**
     * Set middleware aliases.
     *
     * @param array<string, string> $aliases
     * @return self
     */
    public function setMiddlewareAliases(array $aliases): self
    {
        $this->middlewareAliases = $aliases;
        return $this;
    }

    /**
     * Get middleware aliases.
     *
     * @return array<string, string>
     */
    public function getMiddlewareAliases(): array
    {
        return $this->middlewareAliases;
    }

    /**
     * Resolve middleware alias to class name.
     *
     * @param string $alias
     * @return string
     */
    public function resolveMiddleware(string $alias): string
    {
        return $this->middlewareAliases[$alias] ?? $alias;
    }
}
