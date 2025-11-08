<?php

declare(strict_types=1);

namespace App\Presentation\Http\Middleware;

use Toporia\Framework\Http\Middleware\AbstractMiddleware;
use Toporia\Framework\Http\Request;
use Toporia\Framework\Http\Response;

/**
 * Request logging middleware.
 *
 * Logs request details before processing and response status after processing.
 * Demonstrates use of AbstractMiddleware before/after hooks.
 */
final class LogRequest extends AbstractMiddleware
{
    private float $startTime;

    /**
     * Log request start.
     *
     * @param Request $request
     * @param Response $response
     * @return void
     */
    protected function before(Request $request, Response $response): void
    {
        $this->startTime = microtime(true);

        error_log(sprintf(
            '[REQUEST] %s %s | IP: %s',
            $request->method(),
            $request->path(),
            $request->ip()
        ));
    }

    /**
     * Log response completion.
     *
     * @param Request $request
     * @param Response $response
     * @param mixed $result
     * @return void
     */
    protected function after(Request $request, Response $response, mixed $result): void
    {
        $duration = round((microtime(true) - $this->startTime) * 1000, 2);

        error_log(sprintf(
            '[RESPONSE] %s %s | Status: %d | Duration: %sms',
            $request->method(),
            $request->path(),
            $response->getStatus(),
            $duration
        ));
    }
}
