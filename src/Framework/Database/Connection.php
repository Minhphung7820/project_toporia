<?php

declare(strict_types=1);

namespace Framework\Database;

use PDO;
use PDOException;
use Framework\Database\Exception\ConnectionException;
use Framework\Database\Exception\QueryException;

/**
 * Database connection implementation.
 *
 * Manages database connections using PDO with support for:
 * - Multiple database drivers (MySQL, PostgreSQL, SQLite)
 * - Connection pooling
 * - Transaction management
 * - Query execution with parameter binding
 * - Automatic reconnection
 */
class Connection implements ConnectionInterface
{
    /**
     * @var PDO|null PDO instance.
     */
    private ?PDO $pdo = null;

    /**
     * @var array<string, mixed> Connection configuration.
     */
    private array $config;

    /**
     * @param array $config Connection configuration.
     *        Required keys: driver, host, database, username, password
     *        Optional keys: port, charset, options
     */
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->connect();
    }

    /**
     * {@inheritdoc}
     */
    public function getPdo(): PDO
    {
        if ($this->pdo === null) {
            $this->reconnect();
        }

        return $this->pdo;
    }

    /**
     * {@inheritdoc}
     */
    public function execute(string $query, array $bindings = []): \PDOStatement
    {
        try {
            $statement = $this->getPdo()->prepare($query);

            // Bind parameters
            foreach ($bindings as $key => $value) {
                $type = $this->getPdoType($value);
                $statement->bindValue(
                    is_int($key) ? $key + 1 : $key,
                    $value,
                    $type
                );
            }

            $statement->execute();

            return $statement;
        } catch (PDOException $e) {
            throw new QueryException(
                "Query execution failed: {$e->getMessage()}",
                $query,
                $bindings,
                $e
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function beginTransaction(): bool
    {
        return $this->getPdo()->beginTransaction();
    }

    /**
     * {@inheritdoc}
     */
    public function commit(): bool
    {
        return $this->getPdo()->commit();
    }

    /**
     * {@inheritdoc}
     */
    public function rollback(): bool
    {
        return $this->getPdo()->rollBack();
    }

    /**
     * {@inheritdoc}
     */
    public function inTransaction(): bool
    {
        return $this->getPdo()->inTransaction();
    }

    /**
     * {@inheritdoc}
     */
    public function lastInsertId(?string $name = null): string
    {
        return $this->getPdo()->lastInsertId($name);
    }

    /**
     * {@inheritdoc}
     */
    public function getDriverName(): string
    {
        return $this->config['driver'] ?? 'mysql';
    }

    /**
     * {@inheritdoc}
     */
    public function disconnect(): void
    {
        $this->pdo = null;
    }

    /**
     * {@inheritdoc}
     */
    public function reconnect(): void
    {
        $this->disconnect();
        $this->connect();
    }

    /**
     * Establish database connection.
     *
     * @return void
     * @throws ConnectionException
     */
    private function connect(): void
    {
        try {
            $dsn = $this->buildDsn();
            $username = $this->config['username'] ?? null;
            $password = $this->config['password'] ?? null;
            $options = $this->getDefaultOptions();

            $this->pdo = new PDO($dsn, $username, $password, $options);
        } catch (PDOException $e) {
            throw new ConnectionException(
                "Failed to connect to database: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * Build DSN string based on driver.
     *
     * @return string
     */
    private function buildDsn(): string
    {
        $driver = $this->config['driver'] ?? 'mysql';

        return match ($driver) {
            'mysql' => $this->buildMysqlDsn(),
            'pgsql' => $this->buildPgsqlDsn(),
            'sqlite' => $this->buildSqliteDsn(),
            default => throw new ConnectionException("Unsupported driver: {$driver}")
        };
    }

    /**
     * Build MySQL DSN.
     *
     * @return string
     */
    private function buildMysqlDsn(): string
    {
        $host = $this->config['host'] ?? 'localhost';
        $port = $this->config['port'] ?? 3306;
        $database = $this->config['database'];
        $charset = $this->config['charset'] ?? 'utf8mb4';

        return "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";
    }

    /**
     * Build PostgreSQL DSN.
     *
     * @return string
     */
    private function buildPgsqlDsn(): string
    {
        $host = $this->config['host'] ?? 'localhost';
        $port = $this->config['port'] ?? 5432;
        $database = $this->config['database'];

        return "pgsql:host={$host};port={$port};dbname={$database}";
    }

    /**
     * Build SQLite DSN.
     *
     * @return string
     */
    private function buildSqliteDsn(): string
    {
        $database = $this->config['database'];
        return "sqlite:{$database}";
    }

    /**
     * Get default PDO options.
     *
     * @return array<int, mixed>
     */
    private function getDefaultOptions(): array
    {
        $defaults = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        return array_merge($defaults, $this->config['options'] ?? []);
    }

    /**
     * Get PDO parameter type for value.
     *
     * @param mixed $value
     * @return int PDO::PARAM_* constant
     */
    private function getPdoType(mixed $value): int
    {
        return match (true) {
            is_int($value) => PDO::PARAM_INT,
            is_bool($value) => PDO::PARAM_BOOL,
            is_null($value) => PDO::PARAM_NULL,
            default => PDO::PARAM_STR,
        };
    }

    /**
     * Execute a SELECT query and return all results.
     *
     * @param string $query SQL query.
     * @param array $bindings Parameter bindings.
     * @return array<array>
     */
    public function select(string $query, array $bindings = []): array
    {
        $statement = $this->execute($query, $bindings);
        return $statement->fetchAll();
    }

    /**
     * Execute a SELECT query and return first result.
     *
     * @param string $query SQL query.
     * @param array $bindings Parameter bindings.
     * @return array|null
     */
    public function selectOne(string $query, array $bindings = []): ?array
    {
        $statement = $this->execute($query, $bindings);
        $result = $statement->fetch();
        return $result ?: null;
    }

    /**
     * Execute an INSERT/UPDATE/DELETE query.
     *
     * @param string $query SQL query.
     * @param array $bindings Parameter bindings.
     * @return int Number of affected rows.
     */
    public function affectingStatement(string $query, array $bindings = []): int
    {
        $statement = $this->execute($query, $bindings);
        return $statement->rowCount();
    }
}
