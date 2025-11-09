<?php

declare(strict_types=1);

namespace Toporia\Framework\Database;

use PDO;

/**
 * Database connection interface.
 *
 * Defines the contract for database connections across different drivers.
 */
interface ConnectionInterface
{
    /**
     * Get the underlying PDO instance.
     *
     * @return PDO
     */
    public function getPdo(): PDO;

    /**
     * Execute a SQL query and return the PDO statement.
     *
     * @param string $query SQL query.
     * @param array $bindings Parameter bindings.
     * @return \PDOStatement
     */
    public function execute(string $query, array $bindings = []): \PDOStatement;

    /**
     * Begin a database transaction.
     *
     * @return bool
     */
    public function beginTransaction(): bool;

    /**
     * Commit the current transaction.
     *
     * @return bool
     */
    public function commit(): bool;

    /**
     * Rollback the current transaction.
     *
     * @return bool
     */
    public function rollback(): bool;

    /**
     * Check if currently in a transaction.
     *
     * @return bool
     */
    public function inTransaction(): bool;

    /**
     * Get the last inserted ID.
     *
     * @param string|null $name Sequence name (for PostgreSQL).
     * @return string
     */
    public function lastInsertId(?string $name = null): string;

    /**
     * Get the database driver name.
     *
     * @return string (mysql, pgsql, sqlite)
     */
    public function getDriverName(): string;

    /**
     * Disconnect from the database.
     *
     * @return void
     */
    public function disconnect(): void;

    /**
     * Reconnect to the database.
     *
     * @return void
     */
    public function reconnect(): void;

    /**
     * Execute a SELECT query and return all rows.
     *
     * @param string $query SQL query.
     * @param array $bindings Parameter bindings.
     * @return array<int, array<string, mixed>>
     */
    public function select(string $query, array $bindings = []): array;

    /**
     * Execute a SELECT query and return first result.
     *
     * @param string $query SQL query.
     * @param array $bindings Parameter bindings.
     * @return array<string, mixed>|null
     */
    public function selectOne(string $query, array $bindings = []): ?array;

    /**
     * Execute an INSERT, UPDATE, or DELETE statement.
     *
     * @param string $query SQL query.
     * @param array $bindings Parameter bindings.
     * @return int Number of affected rows.
     */
    public function affectingStatement(string $query, array $bindings = []): int;

    /**
     * Get a query builder for the given table.
     *
     * Enables fluent query building pattern:
     * $users = $connection->table('users')->where('active', true)->get();
     *
     * @param string $table Table name.
     * @return Query\QueryBuilder
     */
    public function table(string $table): Query\QueryBuilder;
}
