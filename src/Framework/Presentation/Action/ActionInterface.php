<?php

declare(strict_types=1);

namespace Framework\Presentation\Action;

use Framework\Http\Request;
use Framework\Http\Response;

/**
 * Action interface for ADR (Action-Domain-Responder) pattern.
 *
 * Actions are single-purpose request handlers that:
 * - Accept a Request and Response
 * - Execute domain logic (via Use Cases)
 * - Return a Response
 *
 * Unlike MVC Controllers, Actions handle only ONE HTTP endpoint.
 * This promotes Single Responsibility Principle.
 *
 * Example:
 * - CreateProductAction handles POST /products
 * - GetProductAction handles GET /products/{id}
 * - UpdateProductAction handles PUT /products/{id}
 */
interface ActionInterface
{
    /**
     * Handle the HTTP request.
     *
     * @param Request $request HTTP request.
     * @param Response $response HTTP response.
     * @param mixed ...$vars Route parameters (e.g., ID from /products/{id}).
     * @return mixed Response result.
     */
    public function __invoke(Request $request, Response $response, mixed ...$vars): mixed;
}
