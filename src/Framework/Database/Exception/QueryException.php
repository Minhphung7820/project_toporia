<?php

declare(strict_types=1);

namespace Framework\Database\Exception;

use RuntimeException;
use Throwable;

/**
 * Exception thrown when a database query fails.
 */
class QueryException extends RuntimeException
{
    /**
     * @param string $message Error message.
     * @param string $query SQL query that failed.
     * @param array $bindings Query parameter bindings.
     * @param Throwable|null $previous Previous exception.
     */
    public function __construct(
        string $message,
        private string $query,
        private array $bindings = [],
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * Get the SQL query that failed.
     *
     * @return string
     */
    public function getQuery(): string
    {
        return $this->query;
    }

    /**
     * Get the query bindings.
     *
     * @return array
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }
}
