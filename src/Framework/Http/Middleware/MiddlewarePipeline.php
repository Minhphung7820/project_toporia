<?php

declare(strict_types=1);

namespace Toporia\Framework\Http\Middleware;

use Toporia\Framework\Container\ContainerInterface;
use Toporia\Framework\Http\Request;
use Toporia\Framework\Http\Response;

/**
 * Middleware Pipeline - Builds and executes middleware chains.
 *
 * Implements the Chain of Responsibility pattern for HTTP middleware.
 * Follows Single Responsibility Principle by focusing only on pipeline building.
 *
 * Features:
 * - Lazy middleware resolution from container
 * - Alias resolution for short names
 * - Proper execution order (declaration order)
 * - Type-safe middleware instantiation
 *
 * Usage:
 * ```php
 * $pipeline = new MiddlewarePipeline($container, ['auth' => Authenticate::class]);
 * $handler = $pipeline->build(['auth', RateLimiter::class], $coreHandler);
 * $handler($request, $response);
 * ```
 */
final class MiddlewarePipeline
{
    /**
     * @var array<string, string> Middleware aliases (short name => class name)
     */
    private array $aliases = [];

    /**
     * @param ContainerInterface $container DI container for resolving middleware.
     * @param array<string, string> $aliases Middleware aliases configuration.
     */
    public function __construct(
        private ContainerInterface $container,
        array $aliases = []
    ) {
        $this->aliases = $aliases;
    }

    /**
     * Build middleware pipeline around a core handler.
     *
     * Processes middleware in reverse order so they execute in declaration order.
     * Uses the Onion pattern - each middleware wraps the next layer.
     *
     * @param array<string> $middlewareStack Middleware class names or aliases.
     * @param callable $coreHandler Core handler (controller/action).
     * @return callable Composed pipeline function.
     */
    public function build(array $middlewareStack, callable $coreHandler): callable
    {
        $pipeline = $coreHandler;

        // Build in reverse order for correct execution sequence
        foreach (array_reverse($middlewareStack) as $middlewareIdentifier) {
            $pipeline = $this->wrapMiddleware($middlewareIdentifier, $pipeline);
        }

        return $pipeline;
    }

    /**
     * Wrap a single middleware around the next handler.
     *
     * @param string $middlewareIdentifier Middleware class name or alias.
     * @param callable $next Next handler in the pipeline.
     * @return callable Wrapped handler.
     */
    private function wrapMiddleware(string $middlewareIdentifier, callable $next): callable
    {
        $middlewareClass = $this->resolveMiddleware($middlewareIdentifier);

        return function (Request $request, Response $response) use ($middlewareClass, $next) {
            $middleware = $this->instantiateMiddleware($middlewareClass);
            return $middleware->handle($request, $response, $next);
        };
    }

    /**
     * Resolve middleware identifier to full class name or callable.
     *
     * @param string $identifier Middleware alias or class name.
     * @return string|callable Full middleware class name or callable factory.
     */
    private function resolveMiddleware(string $identifier): string|callable
    {
        return $this->aliases[$identifier] ?? $identifier;
    }

    /**
     * Instantiate middleware from container with auto-wiring.
     *
     * @param string|callable $middlewareClass Middleware class name or callable factory.
     * @return MiddlewareInterface Middleware instance.
     * @throws \RuntimeException If middleware doesn't implement MiddlewareInterface.
     */
    private function instantiateMiddleware(string|callable $middlewareClass): MiddlewareInterface
    {
        // If it's a callable (closure), call it to get the instance
        if (is_callable($middlewareClass)) {
            $middleware = $middlewareClass($this->container);
        } else {
            // Otherwise, resolve from container
            $middleware = $this->container->get($middlewareClass);
        }

        if (!$middleware instanceof MiddlewareInterface) {
            throw new \RuntimeException(
                sprintf(
                    'Middleware must implement %s, got %s',
                    MiddlewareInterface::class,
                    is_object($middleware) ? get_class($middleware) : gettype($middleware)
                )
            );
        }

        return $middleware;
    }

    /**
     * Add or update middleware aliases.
     *
     * @param array<string, string> $aliases Middleware aliases to add/update.
     * @return self
     */
    public function addAliases(array $aliases): self
    {
        $this->aliases = array_merge($this->aliases, $aliases);
        return $this;
    }

    /**
     * Get all registered aliases.
     *
     * @return array<string, string>
     */
    public function getAliases(): array
    {
        return $this->aliases;
    }
}
