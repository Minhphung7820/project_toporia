<?php

declare(strict_types=1);

namespace Toporia\Framework\Database\Query;

/**
 * Query Builder interface.
 *
 * Provides fluent interface for building SQL queries.
 */
interface QueryBuilderInterface
{
    /**
     * Set the table for the query.
     *
     * @param string $table Table name.
     * @return self
     */
    public function table(string $table): self;

    /**
     * Add a SELECT clause.
     *
     * @param string|array $columns Columns to select.
     * @return self
     */
    public function select(string|array $columns = ['*']): self;

    /**
     * Add a WHERE clause.
     *
     * @param string $column Column name.
     * @param mixed $operator Operator or value.
     * @param mixed $value Value (optional if operator is value).
     * @return self
     */
    public function where(string $column, mixed $operator, mixed $value = null): self;

    /**
     * Add an OR WHERE clause.
     *
     * @param string $column Column name.
     * @param mixed $operator Operator or value.
     * @param mixed $value Value (optional).
     * @return self
     */
    public function orWhere(string $column, mixed $operator, mixed $value = null): self;

    /**
     * Add a WHERE IN clause.
     *
     * @param string $column Column name.
     * @param array $values Values array.
     * @return self
     */
    public function whereIn(string $column, array $values): self;

    /**
     * Add a WHERE NULL clause.
     *
     * @param string $column Column name.
     * @return self
     */
    public function whereNull(string $column): self;

    /**
     * Add an ORDER BY clause.
     *
     * @param string $column Column name.
     * @param string $direction Direction (ASC or DESC).
     * @return self
     */
    public function orderBy(string $column, string $direction = 'ASC'): self;

    /**
     * Add a LIMIT clause.
     *
     * @param int $limit Limit value.
     * @return self
     */
    public function limit(int $limit): self;

    /**
     * Add an OFFSET clause.
     *
     * @param int $offset Offset value.
     * @return self
     */
    public function offset(int $offset): self;

    /**
     * Add a JOIN clause.
     *
     * @param string $table Table to join.
     * @param string $first First column.
     * @param string $operator Operator.
     * @param string $second Second column.
     * @param string $type Join type (INNER, LEFT, RIGHT).
     * @return self
     */
    public function join(string $table, string $first, string $operator, string $second, string $type = 'INNER'): self;

    /**
     * Execute query and get all results.
     *
     * @return RowCollection|array<array>
     */
    public function get(): RowCollection|array;

    /**
     * Execute query and get first result.
     *
     * @return array|null
     */
    public function first(): ?array;

    /**
     * Find a record by ID.
     *
     * @param int|string $id Primary key value.
     * @param string $column Primary key column name.
     * @return array|null
     */
    public function find(int|string $id, string $column = 'id'): ?array;

    /**
     * Insert a new record.
     *
     * @param array $data Data to insert.
     * @return int Last insert ID.
     */
    public function insert(array $data): int;

    /**
     * Update records.
     *
     * @param array $data Data to update.
     * @return int Number of affected rows.
     */
    public function update(array $data): int;

    /**
     * Delete records.
     *
     * @return int Number of affected rows.
     */
    public function delete(): int;

    /**
     * Get the SQL query string.
     *
     * @return string
     */
    public function toSql(): string;

    /**
     * Get the query bindings.
     *
     * @return array
     */
    public function getBindings(): array;
}
