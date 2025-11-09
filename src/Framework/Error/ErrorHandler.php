<?php

declare(strict_types=1);

namespace Toporia\Framework\Error;

use Throwable;
use ErrorException;

/**
 * Error Handler
 *
 * Beautiful error handling inspired by Whoops/Ignition.
 *
 * Features:
 * - Beautiful HTML error pages with syntax highlighting
 * - JSON responses for API requests
 * - Stack trace with code context
 * - Environment-aware (dev/production)
 * - PSR-3 logging support
 *
 * Clean Architecture:
 * - Single Responsibility: Only handles errors
 * - Open/Closed: Extensible via renderers
 * - Dependency Inversion: Depends on renderer abstraction
 *
 * Performance:
 * - O(1) in production (simple error page)
 * - O(N) in development where N = stack frames (acceptable for debugging)
 *
 * @package Toporia\Framework\Error
 */
final class ErrorHandler implements ErrorHandlerInterface
{
    /**
     * @param bool $debug Enable debug mode (show detailed errors)
     * @param ErrorRendererInterface|null $renderer Custom error renderer
     */
    public function __construct(
        private bool $debug = true,
        private ?ErrorRendererInterface $renderer = null
    ) {
        // Default to HTML renderer if none provided
        $this->renderer ??= new HtmlErrorRenderer($this->debug);
    }

    /**
     * {@inheritdoc}
     */
    public function register(): void
    {
        // Convert PHP errors to exceptions
        set_error_handler([$this, 'handleError']);

        // Handle uncaught exceptions
        set_exception_handler([$this, 'handle']);

        // Handle fatal errors
        register_shutdown_function([$this, 'handleShutdown']);

        // Don't display errors directly (we'll handle them)
        ini_set('display_errors', '0');

        // Report all errors
        error_reporting(E_ALL);
    }

    /**
     * Handle PHP errors by converting them to ErrorException.
     *
     * @param int $level Error level
     * @param string $message Error message
     * @param string $file File where error occurred
     * @param int $line Line number
     * @return bool
     * @throws ErrorException
     */
    public function handleError(int $level, string $message, string $file = '', int $line = 0): bool
    {
        if (error_reporting() & $level) {
            throw new ErrorException($message, 0, $level, $file, $line);
        }

        return false;
    }

    /**
     * Handle fatal errors on shutdown.
     *
     * @return void
     */
    public function handleShutdown(): void
    {
        $error = error_get_last();

        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $this->handle(
                new ErrorException(
                    $error['message'],
                    0,
                    $error['type'],
                    $error['file'],
                    $error['line']
                )
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function handle(Throwable $exception): void
    {
        // Report to logs/monitoring
        $this->report($exception);

        // Render response
        $this->render($exception);
    }

    /**
     * {@inheritdoc}
     */
    public function report(Throwable $exception): void
    {
        // In production, log to file/monitoring service
        // For now, just error_log
        error_log(sprintf(
            "[%s] %s: %s in %s:%d\n%s",
            date('Y-m-d H:i:s'),
            get_class($exception),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTraceAsString()
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function render(Throwable $exception): void
    {
        // Determine if request expects JSON
        $expectsJson = $this->expectsJson();

        // Use appropriate renderer
        if ($expectsJson) {
            $renderer = new JsonErrorRenderer($this->debug);
        } else {
            $renderer = $this->renderer;
        }

        // Render error response
        $renderer->render($exception);
    }

    /**
     * Determine if the request expects JSON response.
     *
     * @return bool
     */
    private function expectsJson(): bool
    {
        // Check Accept header
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        if (str_contains($accept, 'application/json')) {
            return true;
        }

        // Check if it's an AJAX request
        $xhr = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
        if (strtolower($xhr) === 'xmlhttprequest') {
            return true;
        }

        // Check if path starts with /api
        $path = $_SERVER['REQUEST_URI'] ?? '';
        if (str_starts_with($path, '/api')) {
            return true;
        }

        return false;
    }
}
