<?php

declare(strict_types=1);

namespace Framework\Database\Schema;

/**
 * Schema Blueprint for defining table structure.
 *
 * Provides fluent interface for creating database tables.
 */
class Blueprint
{
    /**
     * @var array<array> Column definitions.
     */
    private array $columns = [];

    /**
     * @var array<array> Index definitions.
     */
    private array $indexes = [];

    /**
     * @var string|null Primary key column.
     */
    private ?string $primaryKey = null;

    /**
     * @var bool Enable timestamps (created_at, updated_at).
     */
    private bool $withTimestamps = false;

    /**
     * @param string $table Table name.
     */
    public function __construct(
        private string $table
    ) {
    }

    /**
     * Add auto-increment ID column.
     *
     * @param string $name Column name.
     * @return self
     */
    public function id(string $name = 'id'): self
    {
        $this->columns[] = [
            'name' => $name,
            'type' => 'integer',
            'autoIncrement' => true,
            'unsigned' => true,
            'nullable' => false
        ];

        $this->primaryKey = $name;

        return $this;
    }

    /**
     * Add string column.
     *
     * @param string $name Column name.
     * @param int $length Max length.
     * @return ColumnDefinition
     */
    public function string(string $name, int $length = 255): ColumnDefinition
    {
        $column = [
            'name' => $name,
            'type' => 'string',
            'length' => $length,
            'nullable' => false
        ];

        $this->columns[] = $column;

        return new ColumnDefinition($this->columns[array_key_last($this->columns)]);
    }

    /**
     * Add text column.
     *
     * @param string $name Column name.
     * @return ColumnDefinition
     */
    public function text(string $name): ColumnDefinition
    {
        $column = [
            'name' => $name,
            'type' => 'text',
            'nullable' => false
        ];

        $this->columns[] = $column;

        return new ColumnDefinition($this->columns[array_key_last($this->columns)]);
    }

    /**
     * Add integer column.
     *
     * @param string $name Column name.
     * @return ColumnDefinition
     */
    public function integer(string $name): ColumnDefinition
    {
        $column = [
            'name' => $name,
            'type' => 'integer',
            'nullable' => false
        ];

        $this->columns[] = $column;

        return new ColumnDefinition($this->columns[array_key_last($this->columns)]);
    }

    /**
     * Add decimal column.
     *
     * @param string $name Column name.
     * @param int $precision Total digits.
     * @param int $scale Decimal digits.
     * @return ColumnDefinition
     */
    public function decimal(string $name, int $precision = 10, int $scale = 2): ColumnDefinition
    {
        $column = [
            'name' => $name,
            'type' => 'decimal',
            'precision' => $precision,
            'scale' => $scale,
            'nullable' => false
        ];

        $this->columns[] = $column;

        return new ColumnDefinition($this->columns[array_key_last($this->columns)]);
    }

    /**
     * Add boolean column.
     *
     * @param string $name Column name.
     * @return ColumnDefinition
     */
    public function boolean(string $name): ColumnDefinition
    {
        $column = [
            'name' => $name,
            'type' => 'boolean',
            'nullable' => false
        ];

        $this->columns[] = $column;

        return new ColumnDefinition($this->columns[array_key_last($this->columns)]);
    }

    /**
     * Add date column.
     *
     * @param string $name Column name.
     * @return ColumnDefinition
     */
    public function date(string $name): ColumnDefinition
    {
        $column = [
            'name' => $name,
            'type' => 'date',
            'nullable' => false
        ];

        $this->columns[] = $column;

        return new ColumnDefinition($this->columns[array_key_last($this->columns)]);
    }

    /**
     * Add datetime column.
     *
     * @param string $name Column name.
     * @return ColumnDefinition
     */
    public function datetime(string $name): ColumnDefinition
    {
        $column = [
            'name' => $name,
            'type' => 'datetime',
            'nullable' => false
        ];

        $this->columns[] = $column;

        return new ColumnDefinition($this->columns[array_key_last($this->columns)]);
    }

    /**
     * Add timestamp column.
     *
     * @param string $name Column name.
     * @return ColumnDefinition
     */
    public function timestamp(string $name): ColumnDefinition
    {
        return $this->datetime($name);
    }

    /**
     * Add created_at and updated_at timestamp columns.
     *
     * @return self
     */
    public function timestamps(): self
    {
        $this->withTimestamps = true;
        $this->timestamp('created_at')->nullable();
        $this->timestamp('updated_at')->nullable();
        return $this;
    }

    /**
     * Add foreign key constraint.
     *
     * @param string $column Local column.
     * @param string $references Foreign column.
     * @param string $on Foreign table.
     * @return self
     */
    public function foreign(string $column, string $references, string $on): self
    {
        $this->indexes[] = [
            'type' => 'foreign',
            'column' => $column,
            'references' => $references,
            'on' => $on
        ];

        return $this;
    }

    /**
     * Add unique index.
     *
     * @param string|array $columns Column(s) for unique index.
     * @return self
     */
    public function unique(string|array $columns): self
    {
        $this->indexes[] = [
            'type' => 'unique',
            'columns' => is_array($columns) ? $columns : [$columns]
        ];

        return $this;
    }

    /**
     * Add index.
     *
     * @param string|array $columns Column(s) for index.
     * @return self
     */
    public function index(string|array $columns): self
    {
        $this->indexes[] = [
            'type' => 'index',
            'columns' => is_array($columns) ? $columns : [$columns]
        ];

        return $this;
    }

    /**
     * Get table name.
     *
     * @return string
     */
    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * Get column definitions.
     *
     * @return array
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * Get index definitions.
     *
     * @return array
     */
    public function getIndexes(): array
    {
        return $this->indexes;
    }

    /**
     * Get primary key.
     *
     * @return string|null
     */
    public function getPrimaryKey(): ?string
    {
        return $this->primaryKey;
    }
}
