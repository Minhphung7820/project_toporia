<?php

declare(strict_types=1);

namespace Framework\Data;

/**
 * Repository interface.
 *
 * Repositories provide an abstraction layer for data access.
 * They act as a collection-like interface for accessing domain objects.
 *
 * This interface defines the base contract that all repositories should follow,
 * regardless of the underlying data source (database, API, file, etc.).
 */
interface RepositoryInterface
{
    /**
     * Find an entity by its unique identifier.
     *
     * @param int|string $id Entity identifier.
     * @return object|null Domain entity or null if not found.
     */
    public function findById(int|string $id): ?object;

    /**
     * Find all entities.
     *
     * @return array<object> Array of domain entities.
     */
    public function findAll(): array;

    /**
     * Save an entity (insert or update).
     *
     * @param object $entity Domain entity.
     * @return object Saved entity with updated properties (like ID).
     */
    public function save(object $entity): object;

    /**
     * Remove an entity.
     *
     * @param object $entity Domain entity.
     * @return bool True if removed successfully.
     */
    public function remove(object $entity): bool;
}
