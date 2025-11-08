<?php

declare(strict_types=1);

namespace Toporia\Framework\Presentation\Action;

use Toporia\Framework\Http\Request;
use Toporia\Framework\Http\Response;

/**
 * Base Action for ADR (Action-Domain-Responder) pattern.
 *
 * Provides lifecycle hooks (before/after) around the main handler logic.
 * Actions should be single-purpose and handle one HTTP endpoint.
 *
 * Lifecycle:
 * 1. before() - Setup, validation, authorization
 * 2. handle() - Main business logic
 * 3. after() - Cleanup, logging, metrics
 *
 * Usage:
 * ```php
 * class CreateProductAction extends AbstractAction
 * {
 *     public function __construct(
 *         private CreateProductHandler $handler,
 *         private ProductResponder $responder
 *     ) {}
 *
 *     protected function handle(Request $request, Response $response, ...$vars): mixed
 *     {
 *         $command = new CreateProductCommand(
 *             $request->input('title'),
 *             $request->input('price')
 *         );
 *
 *         $product = ($this->handler)($command);
 *
 *         return $this->responder->created($response, $product);
 *     }
 *
 *     protected function before(Request $request, Response $response): void
 *     {
 *         // Authorization check
 *         if (!auth()->can('create-product')) {
 *             throw new UnauthorizedException();
 *         }
 *     }
 * }
 * ```
 */
abstract class AbstractAction implements ActionInterface
{
    /**
     * {@inheritdoc}
     */
    final public function __invoke(Request $request, Response $response, mixed ...$vars): mixed
    {
        $this->before($request, $response);
        $result = $this->handle($request, $response, ...$vars);
        $this->after($request, $response, $result);
        return $result;
    }

    /**
     * Handle the main action logic.
     *
     * This is where you:
     * - Extract data from Request
     * - Call Use Case Handlers
     * - Use Responders to format output
     *
     * @param Request $request HTTP request.
     * @param Response $response HTTP response.
     * @param mixed ...$vars Route parameters.
     * @return mixed Response result.
     */
    abstract protected function handle(Request $request, Response $response, mixed ...$vars): mixed;

    /**
     * Execute logic before handling the request.
     *
     * Use this for:
     * - Input validation
     * - Authorization checks
     * - Setting up context
     * - Rate limiting
     *
     * @param Request $request HTTP request.
     * @param Response $response HTTP response.
     * @return void
     * @throws \Exception To stop execution and return error response.
     */
    protected function before(Request $request, Response $response): void
    {
        // Override in child classes if needed
    }

    /**
     * Execute logic after handling the request.
     *
     * Use this for:
     * - Logging
     * - Metrics collection
     * - Cleanup
     * - Cache invalidation
     *
     * @param Request $request HTTP request.
     * @param Response $response HTTP response.
     * @param mixed $result Result from handle().
     * @return void
     */
    protected function after(Request $request, Response $response, mixed $result): void
    {
        // Override in child classes if needed
    }
}
