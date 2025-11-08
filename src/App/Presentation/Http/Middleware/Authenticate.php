<?php

declare(strict_types=1);

namespace App\Presentation\Http\Middleware;

use Toporia\Framework\Http\Middleware\MiddlewareInterface;
use Toporia\Framework\Http\Request;
use Toporia\Framework\Http\Response;

/**
 * Authentication middleware.
 *
 * Ensures the user is authenticated before accessing protected routes.
 * Redirects to login page if not authenticated.
 */
final class Authenticate implements MiddlewareInterface
{
    /**
     * Handle authentication check.
     *
     * @param Request $request
     * @param Response $response
     * @param callable $next
     * @return mixed
     */
    public function handle(Request $request, Response $response, callable $next): mixed
    {
        if (!auth()->check()) {
            $response->setStatus(401);
            $response->html('<h1>401 Unauthorized</h1><p><a href="/login">Login</a></p>');
            return null;
        }

        return $next($request, $response);
    }
}
