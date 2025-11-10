<?php

declare(strict_types=1);

namespace Toporia\Framework\Routing;

use Toporia\Framework\Container\ContainerInterface;
use Toporia\Framework\Http\Middleware\MiddlewarePipeline;
use Toporia\Framework\Http\Request;
use Toporia\Framework\Http\Response;

/**
 * HTTP Router with middleware support and dependency injection.
 *
 * Features:
 * - RESTful route registration (GET, POST, PUT, PATCH, DELETE, ANY)
 * - Route parameter extraction ({id} syntax)
 * - Middleware pipeline
 * - Named routes
 * - Dependency injection for controllers
 */
final class Router implements RouterInterface
{
    /**
     * @var RouteCollectionInterface Route collection.
     */
    private RouteCollectionInterface $routes;

    /**
     * @var MiddlewarePipeline Middleware pipeline builder.
     */
    private MiddlewarePipeline $middlewarePipeline;

    /**
     * @var string Current prefix for route groups
     */
    private string $currentPrefix = '';

    /**
     * @var array<string> Current middleware stack for route groups
     */
    private array $currentMiddleware = [];

    /**
     * @var string|null Current namespace for route groups
     */
    private ?string $currentNamespace = null;

    /**
     * @var string Current name prefix for route groups
     */
    private string $currentNamePrefix = '';

    /**
     * @param Request $request Current HTTP request.
     * @param Response $response HTTP response handler.
     * @param ContainerInterface $container Dependency injection container.
     * @param RouteCollectionInterface|null $routes Optional custom route collection.
     */
    public function __construct(
        private Request $request,
        private Response $response,
        private ContainerInterface $container,
        ?RouteCollectionInterface $routes = null
    ) {
        $this->routes = $routes ?? new RouteCollection();
        $this->middlewarePipeline = new MiddlewarePipeline($container);
    }

    /**
     * Set middleware aliases for resolving short names.
     *
     * @param array<string, string> $aliases
     * @return self
     */
    public function setMiddlewareAliases(array $aliases): self
    {
        $this->middlewarePipeline->addAliases($aliases);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $path, mixed $handler, array $middleware = []): RouteInterface
    {
        return $this->addRoute('GET', $path, $handler, $middleware);
    }

    /**
     * {@inheritdoc}
     */
    public function post(string $path, mixed $handler, array $middleware = []): RouteInterface
    {
        return $this->addRoute('POST', $path, $handler, $middleware);
    }

    /**
     * {@inheritdoc}
     */
    public function put(string $path, mixed $handler, array $middleware = []): RouteInterface
    {
        return $this->addRoute('PUT', $path, $handler, $middleware);
    }

    /**
     * {@inheritdoc}
     */
    public function patch(string $path, mixed $handler, array $middleware = []): RouteInterface
    {
        return $this->addRoute('PATCH', $path, $handler, $middleware);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $path, mixed $handler, array $middleware = []): RouteInterface
    {
        return $this->addRoute('DELETE', $path, $handler, $middleware);
    }

    /**
     * {@inheritdoc}
     */
    public function any(string $path, mixed $handler, array $middleware = []): RouteInterface
    {
        return $this->addRoute(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], $path, $handler, $middleware);
    }

    /**
     * {@inheritdoc}
     */
    public function dispatch(): void
    {
        $match = $this->routes->match($this->request->method(), $this->request->path());

        if ($match === null) {
            $this->handleNotFound();
            return;
        }

        ['route' => $route, 'parameters' => $parameters] = $match;

        $this->executeRoute($route, $parameters);
    }

    /**
     * Add a route to the collection.
     *
     * @param string|array $methods HTTP method(s).
     * @param string $path URI pattern.
     * @param mixed $handler Route handler.
     * @param array<string> $middleware Middleware classes.
     * @return RouteInterface
     */
    private function addRoute(
        string|array $methods,
        string $path,
        mixed $handler,
        array $middleware
    ): RouteInterface {
        // Apply current prefix from groups
        $fullPath = $this->currentPrefix . '/' . ltrim($path, '/');
        $fullPath = '/' . trim($fullPath, '/');
        if ($fullPath !== '/') {
            $fullPath = rtrim($fullPath, '/');
        }

        // Merge group middleware with route middleware
        $fullMiddleware = array_merge($this->currentMiddleware, $middleware);

        // Apply namespace to handler if it's an array with class string
        if (is_array($handler) && isset($handler[0]) && is_string($handler[0])) {
            if ($this->currentNamespace && !str_contains($handler[0], '\\')) {
                $handler[0] = $this->currentNamespace . '\\' . $handler[0];
            }
        }

        $route = new Route($methods, $fullPath, $handler, $fullMiddleware);

        // Apply name prefix if set
        if ($this->currentNamePrefix) {
            // Store the name prefix for later use when name() is called on route
            // This will be handled by Route class
        }

        $this->routes->add($route);
        return $route;
    }

    /**
     * Execute a matched route with middleware pipeline.
     *
     * @param RouteInterface $route Matched route.
     * @param array $parameters Extracted route parameters.
     * @return void
     */
    private function executeRoute(RouteInterface $route, array $parameters): void
    {
        $handler = $route->getHandler();

        // Build the core handler
        $coreHandler = $this->buildCoreHandler($handler, $parameters);

        // Build middleware pipeline using MiddlewarePipeline class
        $pipeline = $this->middlewarePipeline->build($route->getMiddleware(), $coreHandler);

        // Execute pipeline
        $pipeline($this->request, $this->response);
    }

    /**
     * Build the core route handler.
     *
     * @param mixed $handler Route handler definition.
     * @param array $parameters Route parameters.
     * @return callable
     */
    private function buildCoreHandler(mixed $handler, array $parameters): callable
    {
        return function (Request $req, Response $res) use ($handler, $parameters) {
            // Temporarily bind Request and Response in container for auto-wiring
            // This allows controllers/actions to inject Request/Response in method parameters
            $this->container->instance(Request::class, $req);
            $this->container->instance(Response::class, $res);

            // Array handler [ControllerClass::class, 'method']
            if (is_array($handler) && is_string($handler[0])) {
                // Auto-wire controller with all dependencies
                $controller = $this->container->get($handler[0]);
                $method = $handler[1];

                // Use container->call() for method parameter injection
                // This allows Request/Response to be auto-injected into controller methods
                return $this->container->call([$controller, $method], $parameters);
            }

            // Callable handler
            if (is_callable($handler)) {
                return $this->container->call($handler, array_merge(
                    ['request' => $req, 'response' => $res],
                    $parameters
                ));
            }

            // Invokable class
            if (is_string($handler) && class_exists($handler)) {
                $instance = $this->container->get($handler);
                return $this->container->call([$instance, '__invoke'], array_merge(
                    ['request' => $req, 'response' => $res],
                    $parameters
                ));
            }

            throw new \RuntimeException('Invalid route handler');
        };
    }


    /**
     * Handle 404 Not Found response.
     *
     * @return void
     */
    private function handleNotFound(): void
    {
        $this->response->html('<h1>404 Not Found</h1>', 404);
    }

    /**
     * Get the route collection.
     *
     * @return RouteCollectionInterface
     */
    public function getRoutes(): RouteCollectionInterface
    {
        return $this->routes;
    }

    // ============================================================================
    // Route Grouping Support
    // ============================================================================

    /**
     * {@inheritdoc}
     */
    public function group(array $attributes, callable $callback): void
    {
        $previousPrefix = $this->currentPrefix;
        $previousMiddleware = $this->currentMiddleware;
        $previousNamespace = $this->currentNamespace;
        $previousNamePrefix = $this->currentNamePrefix;

        // Apply group attributes
        if (isset($attributes['prefix'])) {
            $this->currentPrefix = $previousPrefix . '/' . trim($attributes['prefix'], '/');
        }

        if (isset($attributes['middleware'])) {
            $this->currentMiddleware = array_merge(
                $previousMiddleware,
                (array) $attributes['middleware']
            );
        }

        if (isset($attributes['namespace'])) {
            $this->currentNamespace = rtrim($attributes['namespace'], '\\');
        }

        if (isset($attributes['name'])) {
            $this->currentNamePrefix = $previousNamePrefix . $attributes['name'];
        }

        // Execute callback
        $callback($this);

        // Restore previous state
        $this->currentPrefix = $previousPrefix;
        $this->currentMiddleware = $previousMiddleware;
        $this->currentNamespace = $previousNamespace;
        $this->currentNamePrefix = $previousNamePrefix;
    }

    /**
     * {@inheritdoc}
     */
    public function getCurrentPrefix(): string
    {
        return $this->currentPrefix;
    }

    /**
     * {@inheritdoc}
     */
    public function setCurrentPrefix(string $prefix): void
    {
        $this->currentPrefix = $prefix;
    }

    /**
     * {@inheritdoc}
     */
    public function getCurrentMiddleware(): array
    {
        return $this->currentMiddleware;
    }

    /**
     * {@inheritdoc}
     */
    public function setCurrentMiddleware(array $middleware): void
    {
        $this->currentMiddleware = $middleware;
    }

    /**
     * {@inheritdoc}
     */
    public function getCurrentNamespace(): ?string
    {
        return $this->currentNamespace;
    }

    /**
     * {@inheritdoc}
     */
    public function setCurrentNamespace(?string $namespace): void
    {
        $this->currentNamespace = $namespace;
    }

    /**
     * {@inheritdoc}
     */
    public function getCurrentNamePrefix(): string
    {
        return $this->currentNamePrefix;
    }

    /**
     * {@inheritdoc}
     */
    public function setCurrentNamePrefix(string $prefix): void
    {
        $this->currentNamePrefix = $prefix;
    }

    /**
     * Compile routes for caching.
     *
     * Returns optimized array structure for O(1) lookup.
     *
     * Performance: Pre-compiles regex patterns, flattens structure.
     *
     * @return array Compiled routes data
     */
    public function compileRoutes(): array
    {
        $compiled = [];

        foreach ($this->routes->all() as $route) {
            $method = $route->getMethod();
            $path = $route->getPath();
            $pattern = $route->getPattern();

            $compiled[] = [
                'method' => $method,
                'path' => $path,
                'pattern' => $pattern,
                'handler' => $this->serializeHandler($route->getHandler()),
                'middleware' => $route->getMiddleware(),
                'name' => $route->getName(),
            ];
        }

        return $compiled;
    }

    /**
     * Load routes from cache.
     *
     * Reconstructs Route objects from cached data.
     *
     * @param array $cached Cached routes data
     * @return void
     */
    public function loadCachedRoutes(array $cached): void
    {
        $collection = new RouteCollection();

        foreach ($cached as $data) {
            $route = new Route(
                $data['method'],
                $data['path'],
                $this->unserializeHandler($data['handler'])
            );

            $route->setPattern($data['pattern']);
            $route->setMiddleware($data['middleware']);

            if ($data['name']) {
                $route->name($data['name']);
            }

            $collection->add($route);
        }

        $this->routes = $collection;
    }

    /**
     * Serialize handler for caching.
     *
     * @param mixed $handler Route handler
     * @return array Serializable handler data
     */
    private function serializeHandler(mixed $handler): array
    {
        if (is_array($handler)) {
            return ['type' => 'array', 'value' => $handler];
        }

        if (is_string($handler)) {
            return ['type' => 'string', 'value' => $handler];
        }

        // Closures cannot be cached
        return ['type' => 'closure', 'value' => null];
    }

    /**
     * Unserialize handler from cache.
     *
     * @param array $data Serialized handler data
     * @return mixed Handler
     */
    private function unserializeHandler(array $data): mixed
    {
        return match ($data['type']) {
            'array', 'string' => $data['value'],
            default => fn() => null // Closure placeholder
        };
    }
}
