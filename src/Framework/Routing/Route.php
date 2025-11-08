<?php

declare(strict_types=1);

namespace Toporia\Framework\Routing;

/**
 * Route implementation with parameter extraction support.
 */
final class Route implements RouteInterface
{
    /**
     * @var array<string> Compiled route pattern.
     */
    private ?string $compiledPattern = null;

    /**
     * @var array<string> Parameter names extracted from URI.
     */
    private array $parameterNames = [];

    /**
     * @var string|null Route name.
     */
    private ?string $name = null;

    /**
     * @param string|array $methods HTTP method(s).
     * @param string $uri URI pattern with optional {param} placeholders.
     * @param mixed $handler Route handler (callable, controller array, etc.).
     * @param array<string> $middleware Middleware classes.
     */
    public function __construct(
        private string|array $methods,
        private string $uri,
        private mixed $handler,
        private array $middleware = []
    ) {
        $this->methods = is_string($methods) ? [$methods] : $methods;
        $this->compileRoute();
    }

    /**
     * {@inheritdoc}
     */
    public function getMethods(): string|array
    {
        return $this->methods;
    }

    /**
     * {@inheritdoc}
     */
    public function getUri(): string
    {
        return $this->uri;
    }

    /**
     * {@inheritdoc}
     */
    public function getHandler(): mixed
    {
        return $this->handler;
    }

    /**
     * {@inheritdoc}
     */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * {@inheritdoc}
     */
    public function name(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function middleware(string|array $middleware): self
    {
        $middlewareArray = is_array($middleware) ? $middleware : [$middleware];
        $this->middleware = array_merge($this->middleware, $middlewareArray);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function matches(string $method, string $uri): ?array
    {
        // Check HTTP method
        if (!in_array($method, $this->methods, true)) {
            return null;
        }

        // Exact match
        if ($this->uri === $uri) {
            return [];
        }

        // Pattern match with parameters
        if ($this->compiledPattern && preg_match($this->compiledPattern, $uri, $matches)) {
            $parameters = [];
            foreach ($this->parameterNames as $name) {
                if (isset($matches[$name])) {
                    $parameters[$name] = $matches[$name];
                }
            }
            return $parameters;
        }

        return null;
    }

    /**
     * Compile the route pattern into a regex.
     */
    private function compileRoute(): void
    {
        // Extract parameter names
        if (preg_match_all('#\{([^/]+)\}#', $this->uri, $matches)) {
            $this->parameterNames = $matches[1];

            // Convert {param} to named capture groups
            $pattern = preg_replace('#\{([^/]+)\}#', '(?P<$1>[^/]+)', $this->uri);
            $this->compiledPattern = '#^' . $pattern . '$#';
        }
    }
}
