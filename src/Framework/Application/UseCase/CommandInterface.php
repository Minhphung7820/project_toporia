<?php

declare(strict_types=1);

namespace Framework\Application\UseCase;

/**
 * Command interface (CQRS pattern).
 *
 * Commands represent write operations that change system state.
 * They are intent-revealing objects that encapsulate a user's action.
 *
 * Characteristics:
 * - Represents an intent to change state
 * - Should be named in imperative mood (CreateProduct, UpdateUser)
 * - Contains only data needed for the operation
 * - Immutable (readonly properties)
 * - No business logic
 */
interface CommandInterface
{
    /**
     * Validate the command data.
     *
     * @return void
     * @throws \InvalidArgumentException If validation fails.
     */
    public function validate(): void;
}
