<?php

declare(strict_types=1);

namespace Framework\Routing;

/**
 * Represents a single route definition.
 */
interface RouteInterface
{
    /**
     * Get the HTTP method(s) for this route.
     *
     * @return string|array Single method or array of methods.
     */
    public function getMethods(): string|array;

    /**
     * Get the URI pattern for this route.
     *
     * @return string
     */
    public function getUri(): string;

    /**
     * Get the route handler (callable, controller action, etc.).
     *
     * @return mixed
     */
    public function getHandler(): mixed;

    /**
     * Get middleware assigned to this route.
     *
     * @return array<string>
     */
    public function getMiddleware(): array;

    /**
     * Get the route name if set.
     *
     * @return string|null
     */
    public function getName(): ?string;

    /**
     * Set the route name for later reference.
     *
     * @param string $name
     * @return self
     */
    public function name(string $name): self;

    /**
     * Add middleware to this route.
     *
     * @param string|array $middleware
     * @return self
     */
    public function middleware(string|array $middleware): self;

    /**
     * Check if this route matches the given method and URI.
     *
     * @param string $method HTTP method.
     * @param string $uri Request URI.
     * @return array|null Array with parameters if matched, null otherwise.
     */
    public function matches(string $method, string $uri): ?array;
}
