<?php

declare(strict_types=1);

namespace Toporia\Framework\Domain;

/**
 * Value Object interface.
 *
 * Value Objects are immutable objects that are defined by their attributes,
 * not by a unique identity. Two value objects with the same attributes
 * are considered equal.
 */
interface ValueObjectInterface
{
    /**
     * Check if this value object equals another.
     *
     * Value objects are equal if all their attributes are equal.
     *
     * @param ValueObjectInterface $other Another value object.
     * @return bool
     */
    public function equals(ValueObjectInterface $other): bool;

    /**
     * Get string representation of the value object.
     *
     * @return string
     */
    public function __toString(): string;

    /**
     * Get the value object as an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
