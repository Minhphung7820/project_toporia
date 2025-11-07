<?php

declare(strict_types=1);

namespace Framework\Http\Middleware;

use Framework\Http\Request;
use Framework\Http\Response;

/**
 * Middleware interface.
 *
 * Middleware provides a mechanism to filter/modify HTTP requests
 * and responses in a pipeline pattern.
 */
interface MiddlewareInterface
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request The HTTP request.
     * @param Response $response The HTTP response.
     * @param callable $next Next middleware/handler in the pipeline.
     * @return mixed
     */
    public function handle(Request $request, Response $response, callable $next): mixed;
}
