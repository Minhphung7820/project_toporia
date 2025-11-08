<?php

declare(strict_types=1);

namespace Toporia\Framework\Routing;

/**
 * Collection of routes.
 */
interface RouteCollectionInterface
{
    /**
     * Add a route to the collection.
     *
     * @param RouteInterface $route
     * @return void
     */
    public function add(RouteInterface $route): void;

    /**
     * Find a route matching the given method and URI.
     *
     * @param string $method HTTP method.
     * @param string $uri Request URI.
     * @return array{route: RouteInterface, parameters: array}|null
     */
    public function match(string $method, string $uri): ?array;

    /**
     * Get a route by its name.
     *
     * @param string $name Route name.
     * @return RouteInterface|null
     */
    public function getByName(string $name): ?RouteInterface;

    /**
     * Get all routes.
     *
     * @return array<RouteInterface>
     */
    public function all(): array;
}
