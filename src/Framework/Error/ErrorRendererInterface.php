<?php

declare(strict_types=1);

namespace Toporia\Framework\Error;

use Throwable;

/**
 * Error Renderer Interface
 *
 * Defines contract for rendering exceptions as HTTP responses.
 *
 * SOLID Principles:
 * - Single Responsibility: Only renders errors
 * - Open/Closed: Multiple implementations (HTML, JSON, CLI)
 * - Dependency Inversion: ErrorHandler depends on this abstraction
 *
 * @package Toporia\Framework\Error
 */
interface ErrorRendererInterface
{
    /**
     * Render an exception as HTTP response.
     *
     * @param Throwable $exception The exception to render
     * @return void
     */
    public function render(Throwable $exception): void;
}
