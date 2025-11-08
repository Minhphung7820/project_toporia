<?php

declare(strict_types=1);

namespace Toporia\Framework\Http\Middleware;

use Toporia\Framework\Auth\AuthorizationException;
use Toporia\Framework\Auth\GateInterface;
use Toporia\Framework\Http\Request;
use Toporia\Framework\Http\Response;

/**
 * Authorization Middleware
 *
 * Checks if the authenticated user is authorized to perform an action.
 * Uses Gate for authorization checks.
 */
final class Authorize implements MiddlewareInterface
{
    public function __construct(
        private GateInterface $gate,
        private string $ability,
        private array $arguments = []
    ) {}

    public function handle(Request $request, Response $response, callable $next): mixed
    {
        try {
            $this->gate->authorize($this->ability, ...$this->arguments);
        } catch (AuthorizationException $e) {
            $response->setStatus(403);

            if ($request->expectsJson()) {
                $response->json([
                    'error' => 'Forbidden',
                    'message' => $e->getMessage(),
                ], 403);
            } else {
                $response->html('<h1>403 Forbidden</h1><p>' . htmlspecialchars($e->getMessage()) . '</p>', 403);
            }

            return null; // Short-circuit
        }

        return $next($request, $response);
    }

    /**
     * Create middleware for a specific ability
     *
     * @param GateInterface $gate
     * @param string $ability
     * @param mixed ...$arguments
     * @return self
     */
    public static function can(GateInterface $gate, string $ability, mixed ...$arguments): self
    {
        return new self($gate, $ability, $arguments);
    }
}
