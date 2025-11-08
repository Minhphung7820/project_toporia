<?php

declare(strict_types=1);

namespace Toporia\Framework\Database;

use Toporia\Framework\Database\Schema\SchemaBuilder;

/**
 * Database Manager.
 *
 * Centralized manager for database connections and operations.
 * Supports multiple named connections and provides convenient access
 * to schema builder and query builder.
 */
class DatabaseManager
{
    /**
     * @var array<string, ConnectionInterface> Active connections.
     */
    private array $connections = [];

    /**
     * @var array<string, array> Connection configurations.
     */
    private array $config;

    /**
     * @var string Default connection name.
     */
    private string $defaultConnection = 'default';

    /**
     * @param array $config Connection configurations.
     *        Example: ['default' => ['driver' => 'mysql', ...]]
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Get a database connection.
     *
     * @param string|null $name Connection name (null for default).
     * @return ConnectionInterface
     */
    public function connection(?string $name = null): ConnectionInterface
    {
        $name = $name ?? $this->defaultConnection;

        if (!isset($this->connections[$name])) {
            $this->connections[$name] = $this->createConnection($name);
        }

        return $this->connections[$name];
    }

    /**
     * Create a new connection instance.
     *
     * @param string $name Connection name.
     * @return ConnectionInterface
     */
    private function createConnection(string $name): ConnectionInterface
    {
        if (!isset($this->config[$name])) {
            throw new \RuntimeException("Database connection '{$name}' not configured");
        }

        return new Connection($this->config[$name]);
    }

    /**
     * Get schema builder for a connection.
     *
     * @param string|null $name Connection name.
     * @return SchemaBuilder
     */
    public function schema(?string $name = null): SchemaBuilder
    {
        return new SchemaBuilder($this->connection($name));
    }

    /**
     * Set the default connection name.
     *
     * @param string $name Connection name.
     * @return void
     */
    public function setDefaultConnection(string $name): void
    {
        $this->defaultConnection = $name;
    }

    /**
     * Disconnect all connections.
     *
     * @return void
     */
    public function disconnect(): void
    {
        foreach ($this->connections as $connection) {
            $connection->disconnect();
        }

        $this->connections = [];
    }

    /**
     * Reconnect a connection.
     *
     * @param string|null $name Connection name.
     * @return void
     */
    public function reconnect(?string $name = null): void
    {
        $name = $name ?? $this->defaultConnection;

        if (isset($this->connections[$name])) {
            $this->connections[$name]->reconnect();
        }
    }
}
