<?php

declare(strict_types=1);

namespace Framework\Data\Pdo;

use Framework\Data\AbstractRepository;
use Framework\Data\RepositoryInterface;
use Framework\Database\ConnectionInterface;
use Framework\Database\Query\QueryBuilder;

/**
 * Base PDO Repository with database-specific functionality.
 *
 * Provides common database operations using the Database Connection layer.
 * Implements the Repository pattern for database persistence.
 *
 * Features:
 * - Transaction management
 * - Query builder access
 * - CRUD operations
 * - Hydration/extraction helpers
 *
 * Usage:
 * ```php
 * class PdoProductRepository extends AbstractPdoRepository implements ProductRepository
 * {
 *     protected string $table = 'products';
 *     protected string $primaryKey = 'id';
 *
 *     protected function hydrate(array $data): Product
 *     {
 *         return new Product(
 *             $data['id'],
 *             $data['title'],
 *             $data['sku']
 *         );
 *     }
 *
 *     protected function extract(object $entity): array
 *     {
 *         return [
 *             'title' => $entity->title,
 *             'sku' => $entity->sku
 *         ];
 *     }
 * }
 * ```
 */
abstract class AbstractPdoRepository extends AbstractRepository implements RepositoryInterface
{
    /**
     * @var string Table name.
     */
    protected string $table;

    /**
     * @var string Primary key column name.
     */
    protected string $primaryKey = 'id';

    /**
     * @param ConnectionInterface $connection Database connection.
     */
    public function __construct(
        protected ConnectionInterface $connection
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function findById(int|string $id): ?object
    {
        $data = $this->query()
            ->where($this->primaryKey, $id)
            ->first();

        return $data ? $this->hydrate($data) : null;
    }

    /**
     * {@inheritdoc}
     */
    public function findAll(): array
    {
        $results = $this->query()->get();

        return array_map([$this, 'hydrate'], $results);
    }

    /**
     * {@inheritdoc}
     */
    public function save(object $entity): object
    {
        $data = $this->extract($entity);

        // Determine if insert or update
        $id = $this->getEntityId($entity);

        if ($id === null) {
            return $this->insert($entity, $data);
        }

        return $this->update($entity, $data, $id);
    }

    /**
     * {@inheritdoc}
     */
    public function remove(object $entity): bool
    {
        $id = $this->getEntityId($entity);

        if ($id === null) {
            return false;
        }

        $affected = $this->query()
            ->where($this->primaryKey, $id)
            ->delete();

        return $affected > 0;
    }

    /**
     * Create a new query builder for this repository's table.
     *
     * @return QueryBuilder
     */
    protected function query(): QueryBuilder
    {
        return (new QueryBuilder($this->connection))->table($this->table);
    }

    /**
     * Insert a new entity.
     *
     * @param object $entity Domain entity.
     * @param array $data Extracted data.
     * @return object Entity with ID set.
     */
    protected function insert(object $entity, array $data): object
    {
        $id = $this->query()->insert($data);

        return $this->setEntityId($entity, $id);
    }

    /**
     * Update an existing entity.
     *
     * @param object $entity Domain entity.
     * @param array $data Extracted data.
     * @param int|string $id Entity ID.
     * @return object Updated entity.
     */
    protected function update(object $entity, array $data, int|string $id): object
    {
        $this->query()
            ->where($this->primaryKey, $id)
            ->update($data);

        return $entity;
    }

    /**
     * Begin a database transaction.
     *
     * @return void
     */
    protected function beginTransaction(): void
    {
        $this->connection->beginTransaction();
    }

    /**
     * Commit the current transaction.
     *
     * @return void
     */
    protected function commit(): void
    {
        $this->connection->commit();
    }

    /**
     * Rollback the current transaction.
     *
     * @return void
     */
    protected function rollback(): void
    {
        if ($this->connection->inTransaction()) {
            $this->connection->rollback();
        }
    }

    /**
     * Execute a callback within a transaction.
     *
     * @param callable $callback Callback to execute.
     * @return mixed Result of callback.
     * @throws \Throwable
     */
    protected function transaction(callable $callback): mixed
    {
        $this->beginTransaction();

        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }

    /**
     * Get entity ID using reflection.
     *
     * Override this if your entity uses a different ID property name.
     *
     * @param object $entity Domain entity.
     * @return int|string|null Entity ID or null if not set.
     */
    protected function getEntityId(object $entity): int|string|null
    {
        // Try common property names
        foreach (['id', 'getId', $this->primaryKey] as $accessor) {
            if (property_exists($entity, $accessor)) {
                return $entity->{$accessor};
            }

            if (method_exists($entity, $accessor)) {
                return $entity->{$accessor}();
            }
        }

        return null;
    }

    /**
     * Set entity ID using reflection.
     *
     * Override this if your entity uses a different ID property or is immutable.
     *
     * @param object $entity Domain entity.
     * @param int|string $id ID to set.
     * @return object Entity with ID set.
     */
    protected function setEntityId(object $entity, int|string $id): object
    {
        // Try to set ID via property
        if (property_exists($entity, 'id')) {
            $reflection = new \ReflectionProperty($entity, 'id');
            $reflection->setAccessible(true);
            $reflection->setValue($entity, $id);
            return $entity;
        }

        // For immutable entities, you might need to recreate the entity
        // Override this method in your repository
        return $entity;
    }
}
