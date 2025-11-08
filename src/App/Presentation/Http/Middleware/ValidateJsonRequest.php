<?php

declare(strict_types=1);

namespace App\Presentation\Http\Middleware;

use Toporia\Framework\Http\Middleware\AbstractMiddleware;
use Toporia\Framework\Http\Request;
use Toporia\Framework\Http\Response;

/**
 * JSON request validation middleware.
 *
 * Validates that JSON requests have valid Content-Type and body.
 * Demonstrates use of AbstractMiddleware process() with short-circuit.
 */
final class ValidateJsonRequest extends AbstractMiddleware
{
    /**
     * Validate JSON request format.
     *
     * @param Request $request
     * @param Response $response
     * @return mixed|null
     */
    protected function process(Request $request, Response $response): mixed
    {
        // Only validate for JSON requests
        if (!$request->expectsJson()) {
            return null; // Continue to next middleware
        }

        // Check Content-Type header
        $contentType = $request->header('content-type');
        if ($contentType && !str_contains($contentType, 'application/json')) {
            $response->json([
                'error' => 'Invalid Content-Type. Expected application/json'
            ], 400);
            return null; // Short-circuit
        }

        // Validate JSON body for POST/PUT/PATCH
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH'])) {
            $raw = $request->raw();
            if (!empty($raw)) {
                json_decode($raw);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $response->json([
                        'error' => 'Invalid JSON: ' . json_last_error_msg()
                    ], 400);
                    return null; // Short-circuit
                }
            }
        }

        return null; // Continue to next middleware
    }
}
