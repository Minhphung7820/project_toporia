<?php

declare(strict_types=1);

namespace Toporia\Framework\Error;

use Throwable;

/**
 * JSON Error Renderer
 *
 * Renders exceptions as JSON responses for API requests.
 *
 * Features:
 * - Clean JSON format
 * - Stack trace in debug mode
 * - Simple message in production
 * - PSR-7 compatible structure
 *
 * Performance: O(N) where N = stack frames (only in debug mode)
 *
 * @package Toporia\Framework\Error
 */
final class JsonErrorRenderer implements ErrorRendererInterface
{
    public function __construct(
        private bool $debug = true
    ) {}

    /**
     * {@inheritdoc}
     */
    public function render(Throwable $exception): void
    {
        http_response_code($this->getStatusCode($exception));
        header('Content-Type: application/json; charset=UTF-8');

        echo json_encode($this->formatException($exception), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Format exception as array.
     *
     * @param Throwable $exception
     * @return array
     */
    private function formatException(Throwable $exception): array
    {
        if ($this->debug) {
            return [
                'error' => [
                    'message' => $exception->getMessage(),
                    'exception' => get_class($exception),
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                    'trace' => $this->formatTrace($exception->getTrace())
                ]
            ];
        }

        // Production: minimal information
        return [
            'error' => [
                'message' => 'Internal Server Error',
                'code' => 500
            ]
        ];
    }

    /**
     * Format stack trace.
     *
     * @param array $trace
     * @return array
     */
    private function formatTrace(array $trace): array
    {
        return array_map(function ($frame) {
            return [
                'file' => $frame['file'] ?? 'unknown',
                'line' => $frame['line'] ?? 0,
                'function' => ($frame['class'] ?? '') . ($frame['type'] ?? '') . ($frame['function'] ?? ''),
            ];
        }, $trace);
    }

    /**
     * Get HTTP status code for exception.
     *
     * @param Throwable $exception
     * @return int
     */
    private function getStatusCode(Throwable $exception): int
    {
        // You can implement custom logic here
        // For example, check if exception implements HttpExceptionInterface
        return 500;
    }
}
