<?php

declare(strict_types=1);

namespace Framework\Domain;

/**
 * Base Value Object class for Domain-Driven Design.
 *
 * Value Objects are immutable objects defined by their attributes.
 * They have no unique identity - equality is based on attribute values.
 *
 * Key characteristics:
 * - Immutable (cannot change after creation)
 * - No unique identity
 * - Equality based on all attributes
 * - Side-effect free behavior
 * - Validates itself on construction
 *
 * Examples: Email, Money, DateRange, Address, Coordinate
 */
abstract class ValueObject implements ValueObjectInterface
{
    /**
     * {@inheritdoc}
     */
    public function equals(ValueObjectInterface $other): bool
    {
        // Value objects are equal if they are of the same class
        // and have the same string representation
        if (!($other instanceof static)) {
            return false;
        }

        return $this->__toString() === $other->__toString();
    }

    /**
     * {@inheritdoc}
     */
    abstract public function __toString(): string;

    /**
     * {@inheritdoc}
     */
    public function toArray(): array
    {
        // Default implementation using reflection
        $reflection = new \ReflectionClass($this);
        $properties = $reflection->getProperties(\ReflectionProperty::IS_PUBLIC | \ReflectionProperty::IS_PROTECTED);

        $array = [];
        foreach ($properties as $property) {
            $property->setAccessible(true);
            $array[$property->getName()] = $property->getValue($this);
        }

        return $array;
    }

    /**
     * Validate the value object.
     *
     * Override this method to add validation logic.
     * Throw an exception if validation fails.
     *
     * @return void
     * @throws \InvalidArgumentException
     */
    protected function validate(): void
    {
        // Override in child classes
    }
}
