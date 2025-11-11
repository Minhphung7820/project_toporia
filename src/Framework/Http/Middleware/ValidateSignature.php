<?php

declare(strict_types=1);

namespace Toporia\Framework\Http\Middleware;

use Toporia\Framework\Http\Request;
use Toporia\Framework\Http\Response;
use Toporia\Framework\Routing\UrlGeneratorInterface;

/**
 * Validate signed URL signatures.
 *
 * This middleware ensures that URLs with signatures are valid and not expired.
 * Use this on routes that should only be accessible via signed URLs.
 *
 * Usage:
 * ```php
 * $router->get('/unsubscribe/{email}', [NewsletterController::class, 'unsubscribe'])
 *     ->name('unsubscribe')
 *     ->middleware([ValidateSignature::class]);
 * ```
 */
final class ValidateSignature implements MiddlewareInterface
{
    /**
     * @param UrlGeneratorInterface $url URL generator
     */
    public function __construct(
        private UrlGeneratorInterface $url
    ) {}

    /**
     * Handle the incoming request.
     *
     * @param Request $request
     * @param Response $response
     * @param callable $next
     * @return mixed
     */
    public function handle(Request $request, Response $response, callable $next): mixed
    {
        // Get the full URL with query string
        $fullUrl = $this->url->full();

        // Validate signature
        if (!$this->url->hasValidSignature($fullUrl)) {
            $response->setStatus(403);
            $response->json([
                'error' => 'Invalid or expired signature',
                'message' => 'This URL has an invalid or expired signature.'
            ], 403);
            return null;
        }

        return $next($request, $response);
    }

    /**
     * Create middleware instance for use in routes.
     *
     * @return callable Middleware factory
     */
    public static function middleware(): callable
    {
        return fn($container) => new self($container->get('url'));
    }
}
