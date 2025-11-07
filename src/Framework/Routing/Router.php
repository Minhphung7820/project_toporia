<?php
namespace Framework\Routing;

use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Container\Container;

final class Router
{
    private Request $request;
    private Response $response;
    private Container $container;
    private array $routes = [
        'GET' => [], 'POST' => [], 'PUT' => [], 'PATCH' => [], 'DELETE' => []
    ];

    public function __construct(Request $req, Response $res, Container $c)
    {
        $this->request = $req;
        $this->response = $res;
        $this->container = $c;
    }

    public function get(string $path, $handler, array $middleware = []): void { $this->routes['GET'][$path] = [$handler, $middleware]; }
    public function post(string $path, $handler, array $middleware = []): void { $this->routes['POST'][$path] = [$handler, $middleware]; }
    public function put(string $path, $handler, array $middleware = []): void { $this->routes['PUT'][$path] = [$handler, $middleware]; }
    public function patch(string $path, $handler, array $middleware = []): void { $this->routes['PATCH'][$path] = [$handler, $middleware]; }
    public function delete(string $path, $handler, array $middleware = []): void { $this->routes['DELETE'][$path] = [$handler, $middleware]; }

    private function match(string $method, string $path): array
    {
        if (isset($this->routes[$method][$path])) {
            return [$this->routes[$method][$path], []];
        }
        foreach ($this->routes[$method] as $route => $handler) {
            $pattern = preg_replace('#\{([^/]+)\}#', '(?P<$1>[^/]+)', $route);
            if (preg_match('#^' . $pattern . '$#', $path, $m)) {
                $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
                return [$handler, $params];
            }
        }
        return [null, []];
    }

    public function dispatch(): void
    {
        [$entry, $params] = $this->match($this->request->method(), $this->request->path());
        if (!$entry) {
            $this->response->html('<h1>404</h1>', 404);
            return;
        }
        [$handler, $middlewares] = $entry;

        $coreHandler = function ($req, $res) use ($handler, $params) {
            if (is_array($handler) && is_string($handler[0])) {
                $controller = new $handler[0]($req, $res);
                return $controller->{$handler[1]}(...array_values($params));
            }
            if (is_callable($handler)) {
                return $handler($req, $res, ...array_values($params));
            }
            throw new \RuntimeException('Invalid route handler');
        };

        $next = $coreHandler;
        for ($i = count($middlewares) - 1; $i >= 0; $i--) {
            $mwClass = $middlewares[$i];
            $mw = new $mwClass();
            $currentNext = $next;
            $next = function ($req, $res) use ($mw, $currentNext) {
                return $mw->handle($req, $res, $currentNext);
            };
        }
        $next($this->request, $this->response);
    }
}
