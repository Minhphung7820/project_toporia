<?php

declare(strict_types=1);

namespace Framework\Data;

use Framework\Database\ConnectionInterface;
use Framework\Database\Query\QueryBuilder;

/**
 * Base Database Repository.
 *
 * Provides common database operations for repositories following
 * the Repository pattern from Domain-Driven Design.
 *
 * Features:
 * - CRUD operations
 * - Query builder access
 * - Transaction support
 * - Pagination helpers
 */
abstract class DatabaseRepository
{
    /**
     * @var string Table name.
     */
    protected string $table;

    /**
     * @var string Primary key column.
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
     * Create a new query builder for the table.
     *
     * @return QueryBuilder
     */
    protected function query(): QueryBuilder
    {
        return (new QueryBuilder($this->connection))->table($this->table);
    }

    /**
     * Find a record by ID.
     *
     * @param int|string $id Primary key value.
     * @return array|null
     */
    public function findById(int|string $id): ?array
    {
        return $this->query()->find($id, $this->primaryKey);
    }

    /**
     * Get all records.
     *
     * @return array<array>
     */
    public function findAll(): array
    {
        return $this->query()->get();
    }

    /**
     * Find records matching criteria.
     *
     * @param array $criteria Key-value pairs for WHERE clauses.
     * @return array<array>
     */
    public function findBy(array $criteria): array
    {
        $query = $this->query();

        foreach ($criteria as $column => $value) {
            $query->where($column, $value);
        }

        return $query->get();
    }

    /**
     * Find one record matching criteria.
     *
     * @param array $criteria Key-value pairs for WHERE clauses.
     * @return array|null
     */
    public function findOneBy(array $criteria): ?array
    {
        $query = $this->query();

        foreach ($criteria as $column => $value) {
            $query->where($column, $value);
        }

        return $query->first();
    }

    /**
     * Insert a new record.
     *
     * @param array $data Data to insert.
     * @return int Last insert ID.
     */
    public function insert(array $data): int
    {
        return $this->query()->insert($data);
    }

    /**
     * Update records matching criteria.
     *
     * @param array $criteria Key-value pairs for WHERE clauses.
     * @param array $data Data to update.
     * @return int Number of affected rows.
     */
    public function update(array $criteria, array $data): int
    {
        $query = $this->query();

        foreach ($criteria as $column => $value) {
            $query->where($column, $value);
        }

        return $query->update($data);
    }

    /**
     * Update a record by ID.
     *
     * @param int|string $id Primary key value.
     * @param array $data Data to update.
     * @return int Number of affected rows.
     */
    public function updateById(int|string $id, array $data): int
    {
        return $this->update([$this->primaryKey => $id], $data);
    }

    /**
     * Delete records matching criteria.
     *
     * @param array $criteria Key-value pairs for WHERE clauses.
     * @return int Number of affected rows.
     */
    public function delete(array $criteria): int
    {
        $query = $this->query();

        foreach ($criteria as $column => $value) {
            $query->where($column, $value);
        }

        return $query->delete();
    }

    /**
     * Delete a record by ID.
     *
     * @param int|string $id Primary key value.
     * @return int Number of affected rows.
     */
    public function deleteById(int|string $id): int
    {
        return $this->delete([$this->primaryKey => $id]);
    }

    /**
     * Count records matching criteria.
     *
     * @param array $criteria Key-value pairs for WHERE clauses.
     * @return int
     */
    public function count(array $criteria = []): int
    {
        $query = $this->query();

        foreach ($criteria as $column => $value) {
            $query->where($column, $value);
        }

        return $query->count();
    }

    /**
     * Check if records exist matching criteria.
     *
     * @param array $criteria Key-value pairs for WHERE clauses.
     * @return bool
     */
    public function exists(array $criteria): bool
    {
        return $this->count($criteria) > 0;
    }

    /**
     * Get paginated results.
     *
     * @param int $page Page number (1-indexed).
     * @param int $perPage Items per page.
     * @param array $criteria Optional WHERE criteria.
     * @return array ['data' => array, 'total' => int, 'page' => int, 'perPage' => int]
     */
    public function paginate(int $page = 1, int $perPage = 15, array $criteria = []): array
    {
        $query = $this->query();

        foreach ($criteria as $column => $value) {
            $query->where($column, $value);
        }

        $total = $query->count();

        $data = $query
            ->limit($perPage)
            ->offset(($page - 1) * $perPage)
            ->get();

        return [
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'lastPage' => (int) ceil($total / $perPage)
        ];
    }

    /**
     * Begin a database transaction.
     *
     * @return bool
     */
    protected function beginTransaction(): bool
    {
        return $this->connection->beginTransaction();
    }

    /**
     * Commit the current transaction.
     *
     * @return bool
     */
    protected function commit(): bool
    {
        return $this->connection->commit();
    }

    /**
     * Rollback the current transaction.
     *
     * @return bool
     */
    protected function rollback(): bool
    {
        return $this->connection->rollback();
    }
}
