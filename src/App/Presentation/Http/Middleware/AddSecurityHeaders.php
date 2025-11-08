<?php

declare(strict_types=1);

namespace App\Presentation\Http\Middleware;

use Toporia\Framework\Http\Middleware\AbstractMiddleware;
use Toporia\Framework\Http\Request;
use Toporia\Framework\Http\Response;

/**
 * Security headers middleware.
 *
 * Adds common security headers to all responses.
 * Demonstrates use of AbstractMiddleware after hook.
 */
final class AddSecurityHeaders extends AbstractMiddleware
{
    /**
     * Add security headers to response.
     *
     * @param Request $request
     * @param Response $response
     * @param mixed $result
     * @return void
     */
    protected function after(Request $request, Response $response, mixed $result): void
    {
        $response->header('X-Content-Type-Options', 'nosniff');
        $response->header('X-Frame-Options', 'SAMEORIGIN');
        $response->header('X-XSS-Protection', '1; mode=block');
        $response->header('Referrer-Policy', 'strict-origin-when-cross-origin');
    }
}
