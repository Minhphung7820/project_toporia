<?php

declare(strict_types=1);

namespace Toporia\Framework\Database\Query;

use Toporia\Framework\Database\ConnectionInterface;
use Toporia\Framework\Database\Query\RowCollection;

/**
 * SQL Query Builder with a fluent interface.
 *
 * Responsibilities:
 * - Compose SQL SELECT statements with WHERE / JOIN / ORDER / LIMIT / OFFSET
 * - Bind parameters to prevent SQL injection
 * - Provide helpers for aggregate queries (count) and existence checks
 * - Execute and return typed RowCollection or single row
 *
 * Design notes:
 * - This builder is stateless with respect to the connection; state only holds the
 *   query parts (table/columns/wheres/joins/etc.). Call newQuery() for a clean builder.
 */
class QueryBuilder implements QueryBuilderInterface
{
    /**
     * Target table name.
     *
     * @var string|null
     */
    private ?string $table = null;

    /**
     * Selected columns.
     *
     * @var array<string>
     */
    private array $columns = ['*'];

    /**
     * WHERE clauses (internal representation).
     *
     * @var array<array>
     */
    private array $wheres = [];

    /**
     * JOIN clauses (internal representation).
     *
     * @var array<array>
     */
    private array $joins = [];

    /**
     * ORDER BY clauses (internal representation).
     *
     * @var array<array>
     */
    private array $orders = [];

    /**
     * LIMIT value.
     *
     * @var int|null
     */
    private ?int $limit = null;

    /**
     * OFFSET value.
     *
     * @var int|null
     */
    private ?int $offset = null;

    /**
     * Positional bindings for prepared statements.
     *
     * @var array<mixed>
     */
    private array $bindings = [];

    /**
     * Relationships to eager load.
     *
     * @var array<string>
     */
    private array $eagerLoad = [];

    /**
     * @param ConnectionInterface $connection Database connection used to execute statements.
     */
    public function __construct(
        private ConnectionInterface $connection
    ) {}

    /**
     * Set the working table for the query.
     */
    public function table(string $table): self
    {
        $this->table = $table;
        return $this;
    }

    /**
     * Set selected columns.
     *
     * Accepts either an array of columns or varargs: select('id', 'name').
     *
     * @param string|array<int,string> $columns
     */
    public function select(string|array $columns = ['*']): self
    {
        $this->columns = is_array($columns) ? $columns : func_get_args();
        return $this;
    }

    /**
     * Add a raw SELECT expression.
     *
     * @param string $expression Raw SQL expression (e.g., "COUNT(*) AS count")
     * @param array<mixed> $bindings Optional bindings for the expression
     * @return $this
     */
    public function selectRaw(string $expression, array $bindings = []): self
    {
        $this->columns[] = $expression;

        foreach ($bindings as $binding) {
            $this->bindings[] = $binding;
        }

        return $this;
    }

    /**
     * Get the table name for this query.
     *
     * @return string|null
     */
    public function getTable(): ?string
    {
        return $this->table;
    }

    /**
     * Get the columns for this query.
     *
     * @return array<string>
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * Add a binding to the query.
     *
     * @param mixed $value Binding value
     * @return void
     */
    public function addBinding(mixed $value): void
    {
        $this->bindings[] = $value;
    }

    /**
     * Add a basic WHERE clause.
     *
     * Supports both:
     * - where('col', '=', 10)
     * - where('col', 10)    // operator defaults to '='
     *
     * @param string $column
     * @param mixed  $operator
     * @param mixed  $value
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
     * Add a basic OR WHERE clause.
     *
     * Same semantics as where(), joined with OR.
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
     * Add a WHERE IN clause.
     *
     * @param string $column
     * @param array<int,mixed> $values
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
     * Add a WHERE IS NULL clause.
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
     * Add a raw WHERE clause.
     *
     * @param string $sql Raw SQL condition (e.g., "price > ? AND stock < ?")
     * @param array<mixed> $bindings Bindings for the placeholders
     * @return $this
     */
    public function whereRaw(string $sql, array $bindings = []): self
    {
        $this->wheres[] = [
            'type' => 'raw',
            'sql' => $sql,
            'boolean' => 'AND'
        ];

        foreach ($bindings as $binding) {
            $this->bindings[] = $binding;
        }

        return $this;
    }

    /**
     * Add a WHERE IS NOT NULL clause.
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
     * Append an ORDER BY clause.
     *
     * @param string $direction 'ASC' or 'DESC' (case-insensitive)
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
     * Set LIMIT.
     */
    public function limit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    /**
     * Set OFFSET.
     */
    public function offset(int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }

    /**
     * Add a JOIN clause.
     *
     * @param string $type One of: INNER, LEFT, RIGHT (case-insensitive)
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
     * Convenience LEFT JOIN.
     */
    public function leftJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->join($table, $first, $operator, $second, 'LEFT');
    }

    /**
     * Convenience RIGHT JOIN.
     */
    public function rightJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->join($table, $first, $operator, $second, 'RIGHT');
    }

    /**
     * Execute the built SELECT and return a typed RowCollection.
     *
     * @return RowCollection<int, array<string,mixed>>
     */
    public function get(): RowCollection
    {
        $sql  = $this->toSql();
        $rows = $this->connection->select($sql, $this->bindings); // array<array>
        return new RowCollection($rows);
    }

    /**
     * Execute the built SELECT with LIMIT 1 and return the first row or null.
     *
     * @return array<string,mixed>|null
     */
    public function first(): ?array
    {
        $this->limit(1);
        return $this->get()->first() ?? null;
    }

    /**
     * Alias of get() for a more collection-oriented naming.
     *
     * @return RowCollection<int, array<string,mixed>>
     */
    public function collect(): RowCollection
    {
        return $this->get();
    }

    /**
     * Backward-compatible helper to return raw array results.
     *
     * @return array<int, array<string,mixed>>
     */
    public function getArray(): array
    {
        return $this->get()->all();
    }

    /**
     * Find a row by primary key column.
     *
     * @param int|string $id
     * @param string     $column Primary key column (default: 'id').
     * @return array<string,mixed>|null
     */
    public function find(int|string $id, string $column = 'id'): ?array
    {
        return $this->where($column, $id)->first();
    }

    /**
     * Insert a single row and return the last inserted id.
     *
     * @param array<string,mixed> $data
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
     * Update rows matching the WHERE clauses.
     *
     * @param array<string,mixed> $data
     * @return int Number of affected rows.
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
     * Delete rows matching the WHERE clauses.
     *
     * @return int Number of affected rows.
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
     * Count rows for the current query.
     *
     * @param string $column Defaults to '*'.
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
     * Whether at least one row exists for the current query.
     */
    public function exists(): bool
    {
        return $this->count() > 0;
    }

    /**
     * Compile the SELECT statement into raw SQL.
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
     * Return the current parameter bindings in positional order.
     *
     * @return array<mixed>
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }

    /**
     * Set relationships to eager load.
     *
     * @param array<string> $relations
     * @return $this
     */
    public function setEagerLoad(array $relations): self
    {
        $this->eagerLoad = $relations;
        return $this;
    }

    /**
     * Get relationships to eager load.
     *
     * @return array<string>
     */
    public function getEagerLoad(): array
    {
        return $this->eagerLoad;
    }

    /**
     * Get the database connection.
     *
     * @return ConnectionInterface
     */
    public function getConnection(): ConnectionInterface
    {
        return $this->connection;
    }

    /**
     * Compile JOIN clauses.
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
                'basic'    => sprintf(' %s %s %s ?', $boolean, $where['column'], $where['operator']),
                'in'       => sprintf(' %s %s IN (%s)', $boolean, $where['column'], implode(', ', array_fill(0, count($where['values']), '?'))),
                'null'     => sprintf(' %s %s IS NULL', $boolean, $where['column']),
                'not_null' => sprintf(' %s %s IS NOT NULL', $boolean, $where['column']),
                'raw'      => sprintf(' %s %s', $boolean, $where['sql']),
                default    => ''
            };
        }

        return $sql;
    }

    /**
     * Compile ORDER BY clauses.
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
     */
    private function compileLimit(): string
    {
        return $this->limit !== null ? " LIMIT {$this->limit}" : '';
    }

    /**
     * Compile OFFSET clause.
     */
    private function compileOffset(): string
    {
        return $this->offset !== null ? " OFFSET {$this->offset}" : '';
    }

    /**
     * Spawn a fresh QueryBuilder sharing the same connection.
     */
    public function newQuery(): self
    {
        return new self($this->connection);
    }

    /**
     * Paginate the query results.
     *
     * This method follows SOLID principles:
     * - Single Responsibility: Only handles database-level pagination
     * - Open/Closed: Returns Paginator that can be extended
     * - Dependency Inversion: Returns abstraction (Paginator), not concrete collection
     *
     * Performance:
     * - Executes 2 queries: COUNT(*) for total, SELECT with LIMIT/OFFSET for data
     * - Much more efficient than loading all data into memory
     * - Scales to millions of records
     *
     * @param int $perPage Number of items per page (default: 15)
     * @param int $page Current page number (1-indexed, default: 1)
     * @param string|null $path Base URL path for pagination links
     * @return \Toporia\Framework\Support\Pagination\Paginator
     *
     * @example
     * // Basic pagination
     * $paginator = DB::table('users')->paginate(15);
     *
     * // With filters
     * $paginator = DB::table('products')
     *     ->where('is_active', true)
     *     ->orderBy('created_at', 'DESC')
     *     ->paginate(20, page: 2);
     *
     * // Access data
     * $items = $paginator->items();
     * $total = $paginator->total();
     * $hasMore = $paginator->hasMorePages();
     */
    public function paginate(int $perPage = 15, int $page = 1, ?string $path = null): \Toporia\Framework\Support\Pagination\Paginator
    {
        // Validate parameters
        if ($perPage < 1) {
            throw new \InvalidArgumentException('Per page must be at least 1');
        }
        if ($page < 1) {
            throw new \InvalidArgumentException('Page must be at least 1');
        }

        // Step 1: Get total count (without limit/offset)
        $total = $this->count();

        // Step 2: Get paginated items
        $offset = ($page - 1) * $perPage;
        $items = $this->limit($perPage)->offset($offset)->get();

        // Step 3: Return Paginator value object
        return new \Toporia\Framework\Support\Pagination\Paginator(
            items: $items,
            total: $total,
            perPage: $perPage,
            currentPage: $page,
            path: $path
        );
    }
}
