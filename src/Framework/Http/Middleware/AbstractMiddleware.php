<?php
namespace Framework\Http\Middleware;

use Framework\Http\Request;
use Framework\Http\Response;

abstract class AbstractMiddleware
{
    final public function __invoke(Request $request, Response $response, callable $next)
    {
        return $this->handle($request, $response, $next);
    }

    abstract public function handle(Request $request, Response $response, callable $next);
}
