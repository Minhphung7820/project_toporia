<?php

declare(strict_types=1);

namespace Toporia\Framework\Http\Middleware;

use Toporia\Framework\Http\Request;
use Toporia\Framework\Http\Response;
use Toporia\Framework\Security\CsrfTokenManagerInterface;

/**
 * CSRF Protection Middleware
 *
 * Validates CSRF tokens for state-changing requests (POST, PUT, PATCH, DELETE).
 * Automatically skips validation for safe methods (GET, HEAD, OPTIONS).
 */
final class CsrfProtection implements MiddlewareInterface
{
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];
    private const TOKEN_FIELDS = ['_token', '_csrf', 'csrf_token'];

    public function __construct(
        private CsrfTokenManagerInterface $tokenManager
    ) {}

    public function handle(Request $request, Response $response, callable $next): mixed
    {
        // Skip CSRF validation for safe methods
        if ($this->isSafeMethod($request->method())) {
            return $next($request, $response);
        }

        // Get token from request
        $token = $this->getTokenFromRequest($request);

        // Validate token
        if (!$this->validateToken($token)) {
            $response->setStatus(403);
            $response->json([
                'error' => 'CSRF token mismatch',
                'message' => 'The CSRF token is invalid or has expired. Please reload the page and try again.'
            ], 403);
            return null; // Short-circuit
        }

        return $next($request, $response);
    }

    /**
     * Check if the HTTP method is safe (doesn't require CSRF protection)
     *
     * @param string $method
     * @return bool
     */
    private function isSafeMethod(string $method): bool
    {
        return in_array(strtoupper($method), self::SAFE_METHODS, true);
    }

    /**
     * Get CSRF token from request
     *
     * Checks multiple locations:
     * 1. Request body/input
     * 2. X-CSRF-TOKEN header
     * 3. X-XSRF-TOKEN header (common with frontend frameworks)
     *
     * @param Request $request
     * @return string|null
     */
    private function getTokenFromRequest(Request $request): ?string
    {
        // Try input fields first
        foreach (self::TOKEN_FIELDS as $field) {
            $token = $request->input($field);
            if ($token !== null) {
                return $token;
            }
        }

        // Try headers
        $headerToken = $request->header('X-CSRF-TOKEN')
            ?? $request->header('X-XSRF-TOKEN');

        return $headerToken;
    }

    /**
     * Validate the CSRF token
     *
     * @param string|null $token
     * @return bool
     */
    private function validateToken(?string $token): bool
    {
        if ($token === null) {
            return false;
        }

        // Try to validate with default key
        if ($this->tokenManager->validate($token)) {
            return true;
        }

        // Try to validate with alternative key names
        foreach (self::TOKEN_FIELDS as $key) {
            if ($this->tokenManager->validate($token, $key)) {
                return true;
            }
        }

        return false;
    }
}
