<?php

declare(strict_types=1);

namespace Toporia\Framework\Error;

use Throwable;

/**
 * Error Handler Interface
 *
 * Defines contract for handling exceptions and errors in the application.
 *
 * SOLID Principles:
 * - Interface Segregation: Focused interface for error handling
 * - Dependency Inversion: Depend on abstraction, not concrete handler
 *
 * @package Toporia\Framework\Error
 */
interface ErrorHandlerInterface
{
    /**
     * Handle an exception.
     *
     * @param Throwable $exception The exception to handle
     * @return void
     */
    public function handle(Throwable $exception): void;

    /**
     * Report an exception to logging/monitoring system.
     *
     * @param Throwable $exception The exception to report
     * @return void
     */
    public function report(Throwable $exception): void;

    /**
     * Render an exception as HTTP response.
     *
     * @param Throwable $exception The exception to render
     * @return void
     */
    public function render(Throwable $exception): void;

    /**
     * Register the error handler.
     *
     * @return void
     */
    public function register(): void;
}
