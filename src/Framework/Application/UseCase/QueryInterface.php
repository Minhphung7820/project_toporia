<?php

declare(strict_types=1);

namespace Framework\Application\UseCase;

/**
 * Query interface (CQRS pattern).
 *
 * Queries represent read operations that don't change system state.
 * They fetch data from the system without side effects.
 *
 * Characteristics:
 * - Read-only operation
 * - No side effects
 * - Should be named as questions (GetProductById, FindActiveUsers)
 * - Contains criteria/filters for the query
 * - Immutable (readonly properties)
 */
interface QueryInterface
{
    /**
     * Validate the query parameters.
     *
     * @return void
     * @throws \InvalidArgumentException If validation fails.
     */
    public function validate(): void;
}
