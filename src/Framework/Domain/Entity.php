<?php

declare(strict_types=1);

namespace Toporia\Framework\Domain;

/**
 * Base Entity class for Domain-Driven Design.
 *
 * Entities are objects that have a unique identity that runs through time
 * and different states. The identity is typically an ID field.
 *
 * Key characteristics:
 * - Has unique identity (ID)
 * - Equality based on ID, not attributes
 * - Mutable (can change state over time)
 * - Contains business logic
 *
 * @property-read mixed $id Entity identifier
 */
abstract class Entity implements EntityInterface
{
    /**
     * @var array<string, mixed> Entity attributes.
     */
    protected array $attributes = [];

    /**
     * {@inheritdoc}
     */
    abstract public function getId(): mixed;

    /**
     * {@inheritdoc}
     */
    public function equals(EntityInterface $other): bool
    {
        // Entities are equal if they are of the same class and have the same ID
        if (!($other instanceof static)) {
            return false;
        }

        return $this->getId() === $other->getId() && $this->getId() !== null;
    }

    /**
     * {@inheritdoc}
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    /**
     * Get an attribute value.
     *
     * @param string $key Attribute key.
     * @return mixed
     */
    protected function getAttribute(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }

    /**
     * Set an attribute value.
     *
     * @param string $key Attribute key.
     * @param mixed $value Attribute value.
     * @return void
     */
    protected function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    /**
     * Check if attribute exists.
     *
     * @param string $key Attribute key.
     * @return bool
     */
    protected function hasAttribute(string $key): bool
    {
        return isset($this->attributes[$key]);
    }

    /**
     * Magic getter for attributes.
     *
     * @param string $key Attribute key.
     * @return mixed
     */
    public function __get(string $key): mixed
    {
        return $this->getAttribute($key);
    }

    /**
     * Magic isset for attributes.
     *
     * @param string $key Attribute key.
     * @return bool
     */
    public function __isset(string $key): bool
    {
        return $this->hasAttribute($key);
    }
}
