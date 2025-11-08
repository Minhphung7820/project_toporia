<?php

declare(strict_types=1);

namespace Toporia\Framework\Domain;

/**
 * Entity interface.
 *
 * Entities are domain objects with unique identity.
 * Two entities with the same ID are considered the same entity,
 * even if their attributes differ.
 */
interface EntityInterface
{
    /**
     * Get the entity's unique identifier.
     *
     * @return mixed Entity ID (int, string, UUID, etc.)
     */
    public function getId(): mixed;

    /**
     * Check if this entity is the same as another.
     *
     * @param EntityInterface $other Another entity.
     * @return bool
     */
    public function equals(EntityInterface $other): bool;

    /**
     * Get the entity as an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
