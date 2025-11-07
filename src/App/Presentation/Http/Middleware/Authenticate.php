<?php
namespace App\Presentation\Http\Middleware;

use Framework\Http\Request;
use Framework\Http\Response;

final class Authenticate
{
    public function handle(Request $request, Response $response, callable $next)
    {
        if (!auth()->check()) {
            $response->setStatus(401);
            $response->html('<h1>401 Unauthorized</h1><p><a href="/login">Login</a></p>');
            return null;
        }
        return $next($request, $response);
    }
}
