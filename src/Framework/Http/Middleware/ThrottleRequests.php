<?php

declare(strict_types=1);

namespace Toporia\Framework\Http\Middleware;

use Toporia\Framework\Http\Request;
use Toporia\Framework\Http\Response;
use Toporia\Framework\RateLimit\RateLimiterInterface;

/**
 * Throttle Requests Middleware
 *
 * Rate limits HTTP requests based on configurable criteria.
 * Supports per-user, per-IP, and custom key-based limiting.
 */
final class ThrottleRequests implements MiddlewareInterface
{
    public function __construct(
        private RateLimiterInterface $limiter,
        private int $maxAttempts = 60,
        private int $decayMinutes = 1,
        private ?string $prefix = null
    ) {}

    public function handle(Request $request, Response $response, callable $next): mixed
    {
        $key = $this->resolveRequestSignature($request);

        if ($this->limiter->tooManyAttempts($key, $this->maxAttempts)) {
            return $this->buildRateLimitResponse($response, $key);
        }

        $this->limiter->attempt($key, $this->maxAttempts, $this->decayMinutes * 60);

        $result = $next($request, $response);

        // Add rate limit headers
        $this->addHeaders($response, $key);

        return $result;
    }

    /**
     * Build rate limit exceeded response
     *
     * @param Response $response
     * @param string $key
     * @return null
     */
    private function buildRateLimitResponse(Response $response, string $key): null
    {
        $retryAfter = $this->limiter->availableIn($key);

        $response->setStatus(429);
        $response->header('Retry-After', (string)$retryAfter);
        $response->header('X-RateLimit-Limit', (string)$this->maxAttempts);
        $response->header('X-RateLimit-Remaining', '0');
        $response->header('X-RateLimit-Reset', (string)(time() + $retryAfter));

        $response->json([
            'error' => 'Too Many Requests',
            'message' => 'Rate limit exceeded. Please try again in ' . $retryAfter . ' seconds.',
            'retry_after' => $retryAfter,
        ], 429);

        return null; // Short-circuit
    }

    /**
     * Add rate limit headers to response
     *
     * @param Response $response
     * @param string $key
     * @return void
     */
    private function addHeaders(Response $response, string $key): void
    {
        $response->header('X-RateLimit-Limit', (string)$this->maxAttempts);
        $response->header('X-RateLimit-Remaining', (string)$this->limiter->remaining($key, $this->maxAttempts));

        $resetTime = time() + $this->limiter->availableIn($key);
        $response->header('X-RateLimit-Reset', (string)$resetTime);
    }

    /**
     * Resolve the request signature for rate limiting
     *
     * @param Request $request
     * @return string
     */
    private function resolveRequestSignature(Request $request): string
    {
        $parts = [
            $this->prefix ?? 'throttle',
            $this->getUserIdentifier($request),
            $request->path(),
        ];

        return implode(':', $parts);
    }

    /**
     * Get user identifier for rate limiting
     *
     * Uses authenticated user ID if available, falls back to IP address.
     *
     * @param Request $request
     * @return string
     */
    private function getUserIdentifier(Request $request): string
    {
        // Try to get authenticated user ID
        try {
            $user = auth()->user();
            if ($user && method_exists($user, 'getId')) {
                return 'user:' . $user->getId();
            }
        } catch (\Throwable $e) {
            // Auth not available or user not authenticated
        }

        // Fall back to IP address
        return 'ip:' . $request->ip();
    }

    /**
     * Create a throttle middleware with specific limits
     *
     * @param RateLimiterInterface $limiter
     * @param int $maxAttempts
     * @param int $decayMinutes
     * @param string|null $prefix
     * @return self
     */
    public static function with(
        RateLimiterInterface $limiter,
        int $maxAttempts = 60,
        int $decayMinutes = 1,
        ?string $prefix = null
    ): self {
        return new self($limiter, $maxAttempts, $decayMinutes, $prefix);
    }
}
