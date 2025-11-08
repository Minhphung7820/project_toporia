<?php

declare(strict_types=1);

namespace Toporia\Framework\Support\Accessors;

use Toporia\Framework\Foundation\ServiceAccessor;
use Toporia\Framework\Database\Connection;
use Toporia\Framework\Database\Query\QueryBuilder;

/**
 * DB Service Accessor
 *
 * Provides static-like access to the database connection.
 *
 * @method static QueryBuilder table(string $table) Get query builder for table
 * @method static mixed query(string $sql, array $bindings = []) Execute raw query
 * @method static array select(string $sql, array $bindings = []) Execute SELECT query
 * @method static int insert(string $sql, array $bindings = []) Execute INSERT query
 * @method static int update(string $sql, array $bindings = []) Execute UPDATE query
 * @method static int delete(string $sql, array $bindings = []) Execute DELETE query
 * @method static void beginTransaction() Begin transaction
 * @method static void commit() Commit transaction
 * @method static void rollback() Rollback transaction
 * @method static \PDO getPdo() Get PDO instance
 *
 * @see Connection
 *
 * @example
 * // Query builder
 * $users = DB::table('users')->where('active', true)->get();
 *
 * // Raw query
 * $results = DB::select('SELECT * FROM users WHERE id = ?', [1]);
 *
 * // Transaction
 * DB::beginTransaction();
 * try {
 *     DB::table('users')->insert(['name' => 'John']);
 *     DB::commit();
 * } catch (\Exception $e) {
 *     DB::rollback();
 * }
 */
final class DB extends ServiceAccessor
{
    protected static function getServiceName(): string
    {
        return 'db';
    }
}
