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
        $route = new Route($methods, $path, $handler, $middleware);
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
            // Array handler [ControllerClass::class, 'method']
            if (is_array($handler) && is_string($handler[0])) {
                // Temporarily bind Request and Response in container for auto-wiring
                $this->container->instance(Request::class, $req);
                $this->container->instance(Response::class, $res);

                // Auto-wire controller with all dependencies
                $controller = $this->container->get($handler[0]);
                $method = $handler[1];

                return $controller->{$method}(...array_values($parameters));
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
}
