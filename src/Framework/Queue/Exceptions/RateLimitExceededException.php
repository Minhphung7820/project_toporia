<?php

declare(strict_types=1);

namespace Toporia\Framework\Queue\Exceptions;

/**
 * Rate Limit Exceeded Exception
 *
 * Thrown when job execution rate limit is exceeded.
 * Contains retry delay information.
 *
 * @package Toporia\Framework\Queue\Exceptions
 */
class RateLimitExceededException extends \RuntimeException
{
    public function __construct(
        string $message = 'Rate limit exceeded',
        private int $retryAfter = 60,
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get number of seconds until retry is allowed.
     *
     * @return int
     */
    public function getRetryAfter(): int
    {
        return $this->retryAfter;
    }
}
