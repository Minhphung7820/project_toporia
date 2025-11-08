<?php

declare(strict_types=1);

namespace Toporia\Framework\Database\Schema;

use Toporia\Framework\Database\ConnectionInterface;

/**
 * Schema Builder for creating/modifying database tables.
 *
 * Compiles Blueprint definitions into SQL DDL statements.
 */
class SchemaBuilder
{
    /**
     * @param ConnectionInterface $connection Database connection.
     */
    public function __construct(
        private ConnectionInterface $connection
    ) {
    }

    /**
     * Create a new table.
     *
     * @param string $table Table name.
     * @param callable $callback Callback receives Blueprint.
     * @return void
     */
    public function create(string $table, callable $callback): void
    {
        $blueprint = new Blueprint($table);
        $callback($blueprint);

        $sql = $this->compileCreate($blueprint);

        $this->connection->execute($sql);
    }

    /**
     * Drop a table if exists.
     *
     * @param string $table Table name.
     * @return void
     */
    public function dropIfExists(string $table): void
    {
        $sql = "DROP TABLE IF EXISTS {$table}";
        $this->connection->execute($sql);
    }

    /**
     * Check if table exists.
     *
     * @param string $table Table name.
     * @return bool
     */
    public function hasTable(string $table): bool
    {
        $driver = $this->connection->getDriverName();

        $sql = match ($driver) {
            'mysql' => "SHOW TABLES LIKE '{$table}'",
            'pgsql' => "SELECT to_regclass('{$table}')",
            'sqlite' => "SELECT name FROM sqlite_master WHERE type='table' AND name='{$table}'",
            default => throw new \RuntimeException("Unsupported driver: {$driver}")
        };

        $result = $this->connection->selectOne($sql);

        return $result !== null;
    }

    /**
     * Compile CREATE TABLE statement.
     *
     * @param Blueprint $blueprint Table blueprint.
     * @return string SQL statement.
     */
    private function compileCreate(Blueprint $blueprint): string
    {
        $driver = $this->connection->getDriverName();

        $columns = array_map(
            fn($column) => $this->compileColumn($column, $driver),
            $blueprint->getColumns()
        );

        // Add primary key
        if ($pk = $blueprint->getPrimaryKey()) {
            $columns[] = "PRIMARY KEY ({$pk})";
        }

        // Add indexes
        foreach ($blueprint->getIndexes() as $index) {
            if ($index['type'] === 'unique') {
                $cols = implode(', ', $index['columns']);
                $columns[] = "UNIQUE ({$cols})";
            } elseif ($index['type'] === 'foreign') {
                $columns[] = sprintf(
                    'FOREIGN KEY (%s) REFERENCES %s(%s)',
                    $index['column'],
                    $index['on'],
                    $index['references']
                );
            }
        }

        $columnsSql = implode(', ', $columns);

        return "CREATE TABLE {$blueprint->getTable()} ({$columnsSql})";
    }

    /**
     * Compile column definition.
     *
     * @param array $column Column definition.
     * @param string $driver Database driver.
     * @return string Column SQL.
     */
    private function compileColumn(array $column, string $driver): string
    {
        $sql = $column['name'] . ' ';

        $sql .= $this->getColumnType($column, $driver);

        if (!empty($column['unsigned'])) {
            $sql .= ' UNSIGNED';
        }

        if (!empty($column['autoIncrement'])) {
            $sql .= match ($driver) {
                'mysql' => ' AUTO_INCREMENT',
                'pgsql' => '', // Handled by SERIAL type
                'sqlite' => ' AUTOINCREMENT',
                default => ''
            };
        }

        if (empty($column['nullable'])) {
            $sql .= ' NOT NULL';
        }

        if (array_key_exists('default', $column)) {
            $default = $this->quoteValue($column['default']);
            $sql .= " DEFAULT {$default}";
        }

        return $sql;
    }

    /**
     * Get column type SQL.
     *
     * @param array $column Column definition.
     * @param string $driver Database driver.
     * @return string Type SQL.
     */
    private function getColumnType(array $column, string $driver): string
    {
        $type = $column['type'];

        return match ($type) {
            'integer' => match ($driver) {
                'mysql' => !empty($column['autoIncrement']) ? 'INT' : 'INT',
                'pgsql' => !empty($column['autoIncrement']) ? 'SERIAL' : 'INTEGER',
                'sqlite' => 'INTEGER',
                default => 'INTEGER'
            },
            'string' => match ($driver) {
                'mysql', 'pgsql' => 'VARCHAR(' . ($column['length'] ?? 255) . ')',
                'sqlite' => 'TEXT',
                default => 'VARCHAR(255)'
            },
            'text' => 'TEXT',
            'decimal' => sprintf(
                'DECIMAL(%d, %d)',
                $column['precision'] ?? 10,
                $column['scale'] ?? 2
            ),
            'boolean' => match ($driver) {
                'mysql' => 'TINYINT(1)',
                'pgsql' => 'BOOLEAN',
                'sqlite' => 'INTEGER',
                default => 'BOOLEAN'
            },
            'date' => 'DATE',
            'datetime' => match ($driver) {
                'mysql' => 'DATETIME',
                'pgsql' => 'TIMESTAMP',
                'sqlite' => 'TEXT',
                default => 'DATETIME'
            },
            default => strtoupper($type)
        };
    }

    /**
     * Quote value for SQL.
     *
     * @param mixed $value Value to quote.
     * @return string Quoted value.
     */
    private function quoteValue(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return "'" . addslashes((string) $value) . "'";
    }
}
