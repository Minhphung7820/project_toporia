<?php

declare(strict_types=1);

namespace Framework\Database;

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
}
