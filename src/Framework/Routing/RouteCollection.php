<?php

declare(strict_types=1);

namespace Toporia\Framework\Routing;

/**
 * Collection of routes with efficient lookup.
 */
final class RouteCollection implements RouteCollectionInterface
{
    /**
     * @var array<RouteInterface>
     */
    private array $routes = [];

    /**
     * @var array<string, RouteInterface> Named routes.
     */
    private array $namedRoutes = [];

    /**
     * {@inheritdoc}
     */
    public function add(RouteInterface $route): void
    {
        $this->routes[] = $route;

        // Index by name if available
        if ($route->getName() !== null) {
            $this->namedRoutes[$route->getName()] = $route;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function match(string $method, string $uri): ?array
    {
        foreach ($this->routes as $route) {
            $parameters = $route->matches($method, $uri);

            if ($parameters !== null) {
                return [
                    'route' => $route,
                    'parameters' => $parameters
                ];
            }
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function getByName(string $name): ?RouteInterface
    {
        return $this->namedRoutes[$name] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function all(): array
    {
        return $this->routes;
    }
}
