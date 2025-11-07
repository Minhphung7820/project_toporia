<?php

declare(strict_types=1);

namespace Framework\Presentation\Responder;

use Framework\Http\Response;

/**
 * Responder interface for ADR pattern.
 *
 * Responders are responsible for formatting and sending HTTP responses.
 * They separate presentation logic from business logic in Actions.
 *
 * Benefits:
 * - Actions focus on business logic
 * - Responders focus on response formatting
 * - Easier to test and maintain
 * - Response formats can be changed independently
 *
 * Common implementations:
 * - JsonResponder (API responses)
 * - HtmlResponder (Web pages)
 * - XmlResponder (XML APIs)
 */
interface ResponderInterface
{
    /**
     * Send a successful response.
     *
     * @param Response $response HTTP response object.
     * @param mixed $data Data to send.
     * @param int $status HTTP status code.
     * @return void
     */
    public function success(Response $response, mixed $data, int $status = 200): void;

    /**
     * Send an error response.
     *
     * @param Response $response HTTP response object.
     * @param string $message Error message.
     * @param int $status HTTP status code.
     * @param array $details Additional error details.
     * @return void
     */
    public function error(Response $response, string $message, int $status = 400, array $details = []): void;
}
