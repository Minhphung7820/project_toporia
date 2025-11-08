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
 *
 * Supports multiple guards via guard() method.
 */
final class Authenticate implements MiddlewareInterface
{
    /**
     * @param string $guard Guard name to use (default: 'web')
     */
    public function __construct(
        private string $guard = 'web'
    ) {
    }

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
        /** @var \Toporia\Framework\Auth\AuthManagerInterface $auth */
        $auth = auth();

        if (!$auth->guard($this->guard)->check()) {
            // Check if request expects JSON (API)
            if ($request->expectsJson()) {
                $response->json(['error' => 'Unauthenticated'], 401);
                return null;
            }

            // Redirect to login for web requests
            $response->redirect('/login');
            return null;
        }

        return $next($request, $response);
    }
}
