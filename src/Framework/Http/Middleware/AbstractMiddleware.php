<?php

declare(strict_types=1);

namespace Framework\Http\Middleware;

use Framework\Http\Request;
use Framework\Http\Response;

/**
 * Abstract base middleware.
 *
 * Provides a convenient base class for creating middleware with
 * optional before/after hooks around the core handle logic.
 */
abstract class AbstractMiddleware implements MiddlewareInterface
{
    /**
     * Make the middleware invokable.
     *
     * @param Request $request
     * @param Response $response
     * @param callable $next
     * @return mixed
     */
    final public function __invoke(Request $request, Response $response, callable $next): mixed
    {
        return $this->handle($request, $response, $next);
    }

    /**
     * {@inheritdoc}
     */
    abstract public function handle(Request $request, Response $response, callable $next): mixed;

    /**
     * Execute logic before passing to next middleware.
     *
     * @param Request $request
     * @param Response $response
     * @return void
     */
    protected function before(Request $request, Response $response): void
    {
        // Override in child class if needed
    }

    /**
     * Execute logic after next middleware has run.
     *
     * @param Request $request
     * @param Response $response
     * @param mixed $result Result from next middleware.
     * @return void
     */
    protected function after(Request $request, Response $response, mixed $result): void
    {
        // Override in child class if needed
    }
}
