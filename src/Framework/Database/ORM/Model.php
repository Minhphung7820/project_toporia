<?php

declare(strict_types=1);

namespace Framework\Database\ORM;

use Framework\Database\ConnectionInterface;
use Framework\Database\Query\QueryBuilder;

/**
 * Base ORM Model with Active Record pattern.
 *
 * Features:
 * - Active Record pattern (save, delete, refresh)
 * - Query builder integration
 * - Automatic timestamps (created_at, updated_at)
 * - Attribute casting
 * - Mass assignment protection
 * - Dirty checking
 * - Event hooks (creating, created, updating, updated, deleting, deleted)
 *
 * @property mixed $id Primary key
 */
abstract class Model implements ModelInterface
{
    /**
     * @var string Table name (override in child class).
     */
    protected static string $table = '';

    /**
     * @var string Primary key column name.
     */
    protected static string $primaryKey = 'id';

    /**
     * @var bool Enable automatic timestamps.
     */
    protected static bool $timestamps = true;

    /**
     * @var array<string> Fillable attributes for mass assignment.
     */
    protected static array $fillable = [];

    /**
     * @var array<string> Guarded attributes (opposite of fillable).
     */
    protected static array $guarded = ['*'];

    /**
     * @var array<string, string> Attribute casting (e.g., ['is_active' => 'bool']).
     */
    protected static array $casts = [];

    /**
     * @var array<string, mixed> Model attributes.
     */
    private array $attributes = [];

    /**
     * @var array<string, mixed> Original attributes (for dirty checking).
     */
    private array $original = [];

    /**
     * @var bool Whether model exists in database.
     */
    private bool $exists = false;

    /**
     * @var ConnectionInterface|null Database connection.
     */
    private static ?ConnectionInterface $connection = null;

    /**
     * @param array $attributes Initial attributes.
     */
    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
        $this->syncOriginal();
    }

    /**
     * Set the database connection for all models.
     *
     * @param ConnectionInterface $connection
     * @return void
     */
    public static function setConnection(ConnectionInterface $connection): void
    {
        self::$connection = $connection;
    }

    /**
     * Get the database connection.
     *
     * @return ConnectionInterface
     */
    protected static function getConnection(): ConnectionInterface
    {
        if (self::$connection === null) {
            throw new \RuntimeException('Database connection not set. Call Model::setConnection() first.');
        }

        return self::$connection;
    }

    /**
     * Create a new query builder for the model.
     *
     * @return QueryBuilder
     */
    public static function query(): QueryBuilder
    {
        return (new QueryBuilder(static::getConnection()))->table(static::getTableName());
    }

    /**
     * {@inheritdoc}
     */
    public static function getTableName(): string
    {
        return static::$table;
    }

    /**
     * {@inheritdoc}
     */
    public static function getPrimaryKey(): string
    {
        return static::$primaryKey;
    }

    /**
     * Find a model by primary key.
     *
     * @param int|string $id Primary key value.
     * @return static|null
     */
    public static function find(int|string $id): ?static
    {
        $data = static::query()->find($id, static::$primaryKey);

        if ($data === null) {
            return null;
        }

        $model = new static($data);
        $model->exists = true;
        $model->syncOriginal();

        return $model;
    }

    /**
     * Find a model by primary key or throw exception.
     *
     * @param int|string $id Primary key value.
     * @return static
     * @throws \RuntimeException
     */
    public static function findOrFail(int|string $id): static
    {
        $model = static::find($id);

        if ($model === null) {
            throw new \RuntimeException(sprintf(
                'Model %s with ID %s not found',
                static::class,
                $id
            ));
        }

        return $model;
    }

    /**
     * Get all models.
     *
     * @return array<static>
     */
    public static function all(): array
    {
        $results = static::query()->get();

        return array_map(function ($data) {
            $model = new static($data);
            $model->exists = true;
            $model->syncOriginal();
            return $model;
        }, $results);
    }

    /**
     * Create and save a new model.
     *
     * @param array $attributes Model attributes.
     * @return static
     */
    public static function create(array $attributes): static
    {
        $model = new static($attributes);
        $model->save();
        return $model;
    }

    /**
     * {@inheritdoc}
     */
    public function save(): bool
    {
        if ($this->exists) {
            return $this->performUpdate();
        }

        return $this->performInsert();
    }

    /**
     * Perform model insert.
     *
     * @return bool
     */
    private function performInsert(): bool
    {
        $this->fireEvent('creating');

        if (static::$timestamps) {
            $this->updateTimestamps();
        }

        $id = static::query()->insert($this->attributes);

        $this->setAttribute(static::$primaryKey, $id);
        $this->exists = true;
        $this->syncOriginal();

        $this->fireEvent('created');

        return true;
    }

    /**
     * Perform model update.
     *
     * @return bool
     */
    private function performUpdate(): bool
    {
        if (!$this->isDirty()) {
            return true;
        }

        $this->fireEvent('updating');

        if (static::$timestamps) {
            $this->attributes['updated_at'] = date('Y-m-d H:i:s');
        }

        $dirty = $this->getDirty();

        static::query()
            ->where(static::$primaryKey, $this->getKey())
            ->update($dirty);

        $this->syncOriginal();

        $this->fireEvent('updated');

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function delete(): bool
    {
        if (!$this->exists) {
            return false;
        }

        $this->fireEvent('deleting');

        static::query()
            ->where(static::$primaryKey, $this->getKey())
            ->delete();

        $this->exists = false;

        $this->fireEvent('deleted');

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function refresh(): self
    {
        if (!$this->exists) {
            return $this;
        }

        $fresh = static::find($this->getKey());

        if ($fresh) {
            $this->attributes = $fresh->attributes;
            $this->syncOriginal();
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function exists(): bool
    {
        return $this->exists;
    }

    /**
     * Fill the model with attributes.
     *
     * @param array $attributes Attributes to fill.
     * @return self
     */
    public function fill(array $attributes): self
    {
        foreach ($attributes as $key => $value) {
            if ($this->isFillable($key)) {
                $this->setAttribute($key, $value);
            }
        }

        return $this;
    }

    /**
     * Check if attribute is fillable.
     *
     * @param string $key Attribute key.
     * @return bool
     */
    private function isFillable(string $key): bool
    {
        if (!empty(static::$fillable)) {
            return in_array($key, static::$fillable, true);
        }

        if (in_array('*', static::$guarded, true)) {
            return false;
        }

        return !in_array($key, static::$guarded, true);
    }

    /**
     * Get the primary key value.
     *
     * @return mixed
     */
    public function getKey(): mixed
    {
        return $this->getAttribute(static::$primaryKey);
    }

    /**
     * Get an attribute value.
     *
     * @param string $key Attribute key.
     * @return mixed
     */
    public function getAttribute(string $key): mixed
    {
        $value = $this->attributes[$key] ?? null;

        return $this->castAttribute($key, $value);
    }

    /**
     * Set an attribute value.
     *
     * @param string $key Attribute key.
     * @param mixed $value Attribute value.
     * @return void
     */
    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    /**
     * Cast an attribute to native type.
     *
     * @param string $key Attribute key.
     * @param mixed $value Raw value.
     * @return mixed
     */
    private function castAttribute(string $key, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        $cast = static::$casts[$key] ?? null;

        return match ($cast) {
            'int', 'integer' => (int) $value,
            'float', 'double' => (float) $value,
            'string' => (string) $value,
            'bool', 'boolean' => (bool) $value,
            'array' => is_string($value) ? json_decode($value, true) : $value,
            'json' => is_string($value) ? json_decode($value) : $value,
            'date' => is_string($value) ? new \DateTime($value) : $value,
            default => $value
        };
    }

    /**
     * Check if model has changed.
     *
     * @return bool
     */
    public function isDirty(): bool
    {
        return !empty($this->getDirty());
    }

    /**
     * Get changed attributes.
     *
     * @return array
     */
    public function getDirty(): array
    {
        $dirty = [];

        foreach ($this->attributes as $key => $value) {
            if (!array_key_exists($key, $this->original) || $this->original[$key] !== $value) {
                $dirty[$key] = $value;
            }
        }

        return $dirty;
    }

    /**
     * Sync original attributes with current.
     *
     * @return void
     */
    private function syncOriginal(): void
    {
        $this->original = $this->attributes;
    }

    /**
     * Update timestamps.
     *
     * @return void
     */
    private function updateTimestamps(): void
    {
        $time = date('Y-m-d H:i:s');

        if (!$this->exists) {
            $this->attributes['created_at'] = $time;
        }

        $this->attributes['updated_at'] = $time;
    }

    /**
     * Fire a model event.
     *
     * @param string $event Event name.
     * @return void
     */
    private function fireEvent(string $event): void
    {
        $method = $event;

        if (method_exists($this, $method)) {
            $this->{$method}();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    /**
     * {@inheritdoc}
     */
    public function toJson(): string
    {
        return json_encode($this->toArray());
    }

    /**
     * Magic getter for attributes.
     *
     * @param string $key Attribute key.
     * @return mixed
     */
    public function __get(string $key): mixed
    {
        return $this->getAttribute($key);
    }

    /**
     * Magic setter for attributes.
     *
     * @param string $key Attribute key.
     * @param mixed $value Attribute value.
     * @return void
     */
    public function __set(string $key, mixed $value): void
    {
        $this->setAttribute($key, $value);
    }

    /**
     * Magic isset for attributes.
     *
     * @param string $key Attribute key.
     * @return bool
     */
    public function __isset(string $key): bool
    {
        return isset($this->attributes[$key]);
    }
}
