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
 * Headers must be added in before() hook since Response sends output immediately.
 */
final class AddSecurityHeaders extends AbstractMiddleware
{
    /**
     * Add security headers to response before handler executes.
     *
     * @param Request $request
     * @param Response $response
     * @return void
     */
    protected function before(Request $request, Response $response): void
    {
        $response->header('X-Content-Type-Options', 'nosniff');
        $response->header('X-Frame-Options', 'SAMEORIGIN');
        $response->header('X-XSS-Protection', '1; mode=block');
        $response->header('Referrer-Policy', 'strict-origin-when-cross-origin');
    }
}
