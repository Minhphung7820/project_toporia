<?php

declare(strict_types=1);

namespace Toporia\Framework\Support\Accessors;

use Toporia\Framework\Foundation\ServiceAccessor;
use Toporia\Framework\Database\ConnectionInterface;
use Toporia\Framework\Database\Query\QueryBuilder;
use Toporia\Framework\Database\DatabaseManager;

/**
 * DB Service Accessor
 *
 * Provides static-like access to database connections following Clean Architecture and SOLID principles.
 *
 * Clean Architecture Compliance:
 * - Presentation Layer: This facade provides clean API for controllers/services
 * - Application Layer: Delegates to DatabaseManager (infrastructure)
 * - No direct coupling to implementation details (uses interfaces)
 *
 * SOLID Principles:
 * - Single Responsibility: Only provides access to connections, doesn't manage them
 * - Open/Closed: Extensible through inheritance (not final), closed for modification
 * - Liskov Substitution: Can be substituted with subclasses
 * - Interface Segregation: Clients use only what they need via @method annotations
 * - Dependency Inversion: Depends on DatabaseManager abstraction, not concrete implementation
 *
 * High Reusability:
 * - Single entry point for all database operations
 * - Works with default connection or named connections
 * - Can be extended for custom behavior without modifying core
 *
 * @method static QueryBuilder table(string $table) Get query builder for table
 * @method static array select(string $sql, array $bindings = []) Execute SELECT query
 * @method static int insert(string $sql, array $bindings = []) Execute INSERT query
 * @method static int update(string $sql, array $bindings = []) Execute UPDATE query
 * @method static int delete(string $sql, array $bindings = []) Execute DELETE query
 * @method static void beginTransaction() Begin transaction
 * @method static void commit() Commit transaction
 * @method static void rollback() Rollback transaction
 * @method static \PDO getPdo() Get PDO instance
 *
 * @see ConnectionInterface
 * @see DatabaseManager
 *
 * @example
 * // Default connection - clean and simple
 * $users = DB::table('users')->where('active', true)->get();
 * $results = DB::select('SELECT * FROM users WHERE id = ?', [1]);
 *
 * // Named connections - switch between databases
 * $products = DB::connection('mysql')->table('products')->get();
 * $analytics = DB::connection('analytics')->table('events')->get();
 * $cache = DB::connection('redis')->table('sessions')->get();
 *
 * // Transactions on default connection
 * DB::beginTransaction();
 * try {
 *     DB::table('users')->insert(['name' => 'John']);
 *     DB::commit();
 * } catch (\Exception $e) {
 *     DB::rollback();
 * }
 *
 * // Transactions on specific connection
 * $mysql = DB::connection('mysql');
 * $mysql->beginTransaction();
 * try {
 *     $mysql->table('orders')->insert(['total' => 100]);
 *     $mysql->commit();
 * } catch (\Exception $e) {
 *     $mysql->rollback();
 * }
 */
class DB extends ServiceAccessor
{
    /**
     * Get a specific database connection by name.
     *
     * This method follows SOLID principles:
     * - Single Responsibility: Only retrieves connections, doesn't create them
     * - Dependency Inversion: Depends on DatabaseManager abstraction via container
     * - Open/Closed: Can be overridden in subclasses for custom behavior
     *
     * @param string|null $name Connection name from config/database.php.
     *                          If null, returns the default connection.
     * @return ConnectionInterface Database connection instance.
     *
     * @example
     * // Get default connection
     * $conn = DB::connection();
     * $conn->table('users')->get();
     *
     * // Get named connection
     * $mysql = DB::connection('mysql');
     * $postgres = DB::connection('analytics');
     * $redis = DB::connection('redis');
     */
    public static function connection(?string $name = null): ConnectionInterface
    {
        return static::getDatabaseManager()->connection($name);
    }

    /**
     * Get the DatabaseManager instance from the container.
     *
     * Separated into its own method following SOLID principles:
     * - Single Responsibility: Encapsulates container access logic
     * - Open/Closed: Can be overridden for testing or custom implementations
     * - Dependency Inversion: Isolates container dependency in one place
     *
     * Benefits:
     * - Testability: Easy to mock in unit tests
     * - Reusability: Can be used by subclasses
     * - Maintainability: Single point of change for container access
     *
     * @return DatabaseManager
     */
    protected static function getDatabaseManager(): DatabaseManager
    {
        return container(DatabaseManager::class);
    }

    /**
     * {@inheritdoc}
     *
     * Returns the service name for the default connection.
     * This enables static method delegation via ServiceAccessor.
     */
    protected static function getServiceName(): string
    {
        return 'db';
    }
}
