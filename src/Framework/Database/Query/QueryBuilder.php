<?php

declare(strict_types=1);

namespace Framework\Database\Query;

use Framework\Database\ConnectionInterface;

/**
 * SQL Query Builder with fluent interface.
 *
 * Features:
 * - Fluent method chaining
 * - Parameter binding for security
 * - Support for complex WHERE clauses
 * - JOIN support
 * - Aggregation functions
 * - Subquery support
 */
class QueryBuilder implements QueryBuilderInterface
{
    /**
     * @var string|null Table name.
     */
    private ?string $table = null;

    /**
     * @var array<string> SELECT columns.
     */
    private array $columns = ['*'];

    /**
     * @var array<array> WHERE clauses.
     */
    private array $wheres = [];

    /**
     * @var array<array> JOIN clauses.
     */
    private array $joins = [];

    /**
     * @var array<array> ORDER BY clauses.
     */
    private array $orders = [];

    /**
     * @var int|null LIMIT value.
     */
    private ?int $limit = null;

    /**
     * @var int|null OFFSET value.
     */
    private ?int $offset = null;

    /**
     * @var array<mixed> Query bindings.
     */
    private array $bindings = [];

    /**
     * @param ConnectionInterface $connection Database connection.
     */
    public function __construct(
        private ConnectionInterface $connection
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function table(string $table): self
    {
        $this->table = $table;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function select(string|array $columns = ['*']): self
    {
        $this->columns = is_array($columns) ? $columns : func_get_args();
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function where(string $column, mixed $operator, mixed $value = null): self
    {
        // Handle where($column, $value) syntax
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $this->wheres[] = [
            'type' => 'basic',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => 'AND'
        ];

        $this->bindings[] = $value;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function orWhere(string $column, mixed $operator, mixed $value = null): self
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $this->wheres[] = [
            'type' => 'basic',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => 'OR'
        ];

        $this->bindings[] = $value;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function whereIn(string $column, array $values): self
    {
        $this->wheres[] = [
            'type' => 'in',
            'column' => $column,
            'values' => $values,
            'boolean' => 'AND'
        ];

        foreach ($values as $value) {
            $this->bindings[] = $value;
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function whereNull(string $column): self
    {
        $this->wheres[] = [
            'type' => 'null',
            'column' => $column,
            'boolean' => 'AND'
        ];

        return $this;
    }

    /**
     * Add a WHERE NOT NULL clause.
     *
     * @param string $column Column name.
     * @return self
     */
    public function whereNotNull(string $column): self
    {
        $this->wheres[] = [
            'type' => 'not_null',
            'column' => $column,
            'boolean' => 'AND'
        ];

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->orders[] = [
            'column' => $column,
            'direction' => strtoupper($direction)
        ];

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function limit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function offset(int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function join(string $table, string $first, string $operator, string $second, string $type = 'INNER'): self
    {
        $this->joins[] = [
            'type' => strtoupper($type),
            'table' => $table,
            'first' => $first,
            'operator' => $operator,
            'second' => $second
        ];

        return $this;
    }

    /**
     * Add a LEFT JOIN clause.
     *
     * @param string $table Table to join.
     * @param string $first First column.
     * @param string $operator Operator.
     * @param string $second Second column.
     * @return self
     */
    public function leftJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->join($table, $first, $operator, $second, 'LEFT');
    }

    /**
     * Add a RIGHT JOIN clause.
     *
     * @param string $table Table to join.
     * @param string $first First column.
     * @param string $operator Operator.
     * @param string $second Second column.
     * @return self
     */
    public function rightJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->join($table, $first, $operator, $second, 'RIGHT');
    }

    /**
     * {@inheritdoc}
     */
    public function get(): array
    {
        $sql = $this->toSql();
        return $this->connection->select($sql, $this->bindings);
    }

    /**
     * {@inheritdoc}
     */
    public function first(): ?array
    {
        $this->limit(1);
        $results = $this->get();
        return $results[0] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function find(int|string $id, string $column = 'id'): ?array
    {
        return $this->where($column, $id)->first();
    }

    /**
     * {@inheritdoc}
     */
    public function insert(array $data): int
    {
        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $this->connection->execute($sql, array_values($data));

        return (int) $this->connection->lastInsertId();
    }

    /**
     * {@inheritdoc}
     */
    public function update(array $data): int
    {
        $sets = [];
        $bindings = [];

        foreach ($data as $column => $value) {
            $sets[] = "{$column} = ?";
            $bindings[] = $value;
        }

        // Add WHERE bindings
        $bindings = array_merge($bindings, $this->bindings);

        $sql = sprintf(
            'UPDATE %s SET %s%s',
            $this->table,
            implode(', ', $sets),
            $this->compileWheres()
        );

        return $this->connection->affectingStatement($sql, $bindings);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(): int
    {
        $sql = sprintf(
            'DELETE FROM %s%s',
            $this->table,
            $this->compileWheres()
        );

        return $this->connection->affectingStatement($sql, $this->bindings);
    }

    /**
     * Get count of records.
     *
     * @param string $column Column to count (default: *).
     * @return int
     */
    public function count(string $column = '*'): int
    {
        $originalColumns = $this->columns;
        $this->columns = ["COUNT({$column}) as aggregate"];

        $result = $this->first();

        $this->columns = $originalColumns;

        return (int) ($result['aggregate'] ?? 0);
    }

    /**
     * Check if any records exist.
     *
     * @return bool
     */
    public function exists(): bool
    {
        return $this->count() > 0;
    }

    /**
     * {@inheritdoc}
     */
    public function toSql(): string
    {
        return sprintf(
            'SELECT %s FROM %s%s%s%s%s%s',
            implode(', ', $this->columns),
            $this->table,
            $this->compileJoins(),
            $this->compileWheres(),
            $this->compileOrders(),
            $this->compileLimit(),
            $this->compileOffset()
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }

    /**
     * Compile JOIN clauses.
     *
     * @return string
     */
    private function compileJoins(): string
    {
        if (empty($this->joins)) {
            return '';
        }

        $sql = '';

        foreach ($this->joins as $join) {
            $sql .= sprintf(
                ' %s JOIN %s ON %s %s %s',
                $join['type'],
                $join['table'],
                $join['first'],
                $join['operator'],
                $join['second']
            );
        }

        return $sql;
    }

    /**
     * Compile WHERE clauses.
     *
     * @return string
     */
    private function compileWheres(): string
    {
        if (empty($this->wheres)) {
            return '';
        }

        $sql = '';

        foreach ($this->wheres as $index => $where) {
            $boolean = $index === 0 ? 'WHERE' : $where['boolean'];

            $sql .= match ($where['type']) {
                'basic' => sprintf(' %s %s %s ?', $boolean, $where['column'], $where['operator']),
                'in' => sprintf(' %s %s IN (%s)', $boolean, $where['column'], implode(', ', array_fill(0, count($where['values']), '?'))),
                'null' => sprintf(' %s %s IS NULL', $boolean, $where['column']),
                'not_null' => sprintf(' %s %s IS NOT NULL', $boolean, $where['column']),
                default => ''
            };
        }

        return $sql;
    }

    /**
     * Compile ORDER BY clauses.
     *
     * @return string
     */
    private function compileOrders(): string
    {
        if (empty($this->orders)) {
            return '';
        }

        $orders = array_map(
            fn($order) => "{$order['column']} {$order['direction']}",
            $this->orders
        );

        return ' ORDER BY ' . implode(', ', $orders);
    }

    /**
     * Compile LIMIT clause.
     *
     * @return string
     */
    private function compileLimit(): string
    {
        return $this->limit !== null ? " LIMIT {$this->limit}" : '';
    }

    /**
     * Compile OFFSET clause.
     *
     * @return string
     */
    private function compileOffset(): string
    {
        return $this->offset !== null ? " OFFSET {$this->offset}" : '';
    }

    /**
     * Create a new query builder instance.
     *
     * @return self
     */
    public function newQuery(): self
    {
        return new self($this->connection);
    }
}
