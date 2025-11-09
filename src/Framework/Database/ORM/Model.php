<?php

declare(strict_types=1);

namespace Toporia\Framework\Database\ORM;

use Toporia\Framework\Database\ConnectionInterface;
use Toporia\Framework\Database\Query\QueryBuilder;
use Toporia\Framework\Database\ORM\ModelCollection;
use Toporia\Framework\Database\Query\RowCollection;
use Toporia\Framework\Database\ORM\Relations;

/**
 * Base Active Record model.
 *
 * Responsibilities:
 * - Persistence (save / delete / refresh)
 * - QueryBuilder integration (`static::query()`)
 * - Timestamp management (created_at, updated_at)
 * - Attribute casting and mass-assignment protection
 * - Dirty checking via original snapshot
 * - Simple lifecycle hooks (creating, created, updating, updated, deleting, deleted)
 *
 * Notes:
 * - This class intentionally follows an Eloquent-like API surface while remaining framework-agnostic.
 * - Collections returned by `all()` / `get()` are `ModelCollection<static>`.
 *
 * @property mixed $id Primary key (dynamic attribute)
 */
abstract class Model implements ModelInterface
{
    /**
     * Database table name (override in child class).
     *
     * @var string
     */
    protected static string $table = '';

    /**
     * Primary key column name.
     *
     * @var string
     */
    protected static string $primaryKey = 'id';

    /**
     * Whether timestamp columns should be automatically managed.
     *
     * @var bool
     */
    protected static bool $timestamps = true;

    /**
     * Whitelist of attributes that can be mass-assigned.
     * If non-empty, only keys listed here are fillable.
     *
     * @var array<string>
     */
    protected static array $fillable = [];

    /**
     * Blacklist of attributes that cannot be mass-assigned.
     * If ['*'] is present, mass-assignment is globally disabled unless listed in $fillable.
     *
     * @var array<string>
     */
    protected static array $guarded = ['*'];

    /**
     * Attribute casting map. Example: ['is_active' => 'bool'].
     * Supported types: int, float, string, bool, array, json, date.
     *
     * @var array<string, string>
     */
    protected static array $casts = [];

    /**
     * Connection name to use for this model.
     * If null, uses the default global connection.
     *
     * Example:
     * protected static ?string $connection = 'analytics';
     *
     * This follows SOLID principles:
     * - Single Responsibility: Model specifies its data source
     * - Open/Closed: Can override per model without modifying base class
     * - Dependency Inversion: Depends on connection name, not concrete connection
     *
     * @var string|null
     */
    protected static ?string $connection = null;

    /**
     * Current attribute bag.
     *
     * @var array<string, mixed>
     */
    private array $attributes = [];

    /**
     * Snapshot of attributes used for dirty checking.
     *
     * @var array<string, mixed>
     */
    private array $original = [];

    /**
     * Whether the model currently exists in the database.
     *
     * @var bool
     */
    private bool $exists = false;

    /**
     * Global default database connection instance.
     *
     * @var ConnectionInterface|null
     */
    private static ?ConnectionInterface $defaultConnection = null;

    /**
     * Loaded relationships.
     *
     * @var array<string, mixed>
     */
    private array $relations = [];

    /**
     * @param array<string,mixed> $attributes Initial attributes.
     */
    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
        $this->syncOriginal();
    }

    /**
     * Set the global default connection used by all models.
     *
     * This is typically called once during application bootstrap.
     *
     * @param ConnectionInterface $connection Default connection instance.
     * @return void
     */
    public static function setConnection(ConnectionInterface $connection): void
    {
        self::$defaultConnection = $connection;
    }

    /**
     * Get the database connection for this model.
     *
     * Resolution order (following Laravel pattern):
     * 1. Check if model specifies a connection name (static::$connection)
     * 2. If yes, resolve from DatabaseManager
     * 3. If no, use global default connection
     *
     * This follows SOLID principles:
     * - Open/Closed: Each model can specify its connection without modifying base class
     * - Dependency Inversion: Depends on DatabaseManager abstraction
     * - Single Responsibility: Connection resolution logic in one place
     *
     * @return ConnectionInterface
     * @throws \RuntimeException If no connection available.
     */
    protected static function getConnection(): ConnectionInterface
    {
        // If model specifies a connection name, resolve it from DatabaseManager
        if (static::$connection !== null) {
            return static::resolveConnection(static::$connection);
        }

        // Otherwise use global default connection
        if (self::$defaultConnection === null) {
            throw new \RuntimeException(
                'Database connection not set. Call Model::setConnection() first or specify connection name in model.'
            );
        }

        return self::$defaultConnection;
    }

    /**
     * Resolve a connection by name from the DatabaseManager.
     *
     * This method can be overridden in tests to provide mock connections.
     *
     * @param string $name Connection name from config/database.php
     * @return ConnectionInterface
     */
    protected static function resolveConnection(string $name): ConnectionInterface
    {
        // Get DatabaseManager from container
        $manager = container(\Toporia\Framework\Database\DatabaseManager::class);
        return $manager->connection($name);
    }

    /**
     * Create a new QueryBuilder scoped to this model's table.
     */
    public static function query(): QueryBuilder
    {
        return (new QueryBuilder(static::getConnection()))->table(static::getTableName());
    }

    /**
     * Get the table name.
     */
    public static function getTableName(): string
    {
        return static::$table;
    }

    /**
     * Get the primary key column name.
     */
    public static function getPrimaryKey(): string
    {
        return static::$primaryKey;
    }

    /**
     * Find a model by its primary key.
     *
     * @param int|string $id Primary key value.
     * @return static|null The hydrated model or null if not found.
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
     * Find a model by its primary key or throw.
     *
     * @param int|string $id Primary key value.
     * @return static
     *
     * @throws \RuntimeException If not found.
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
     * Get all records as a typed ModelCollection.
     *
     * @return ModelCollection<static>
     */
    public static function all(): ModelCollection
    {
        return static::get();
    }

    /**
     * Paginate the model query results.
     *
     * This provides a clean API for pagination at the model level:
     * - Uses QueryBuilder::paginate() for database-level pagination
     * - Returns Paginator with ModelCollection items
     * - Supports all query builder methods (where, orderBy, etc.)
     *
     * SOLID Principles:
     * - Single Responsibility: Delegates to QueryBuilder for actual pagination
     * - Open/Closed: Can be overridden in child models for custom pagination
     * - Dependency Inversion: Returns Paginator abstraction
     *
     * @param int $perPage Number of items per page (default: 15)
     * @param int $page Current page number (1-indexed, default: 1)
     * @param string|null $path Base URL path for pagination links
     * @return \Toporia\Framework\Support\Pagination\Paginator
     *
     * @example
     * // Basic pagination
     * $products = ProductModel::paginate(15);
     *
     * // With query builder methods
     * $products = ProductModel::where('is_active', true)
     *     ->orderBy('created_at', 'DESC')
     *     ->paginate(20, page: 2);
     *
     * // Access paginated data
     * foreach ($products->items() as $product) {
     *     echo $product->title;
     * }
     *
     * // Get pagination metadata
     * $total = $products->total();
     * $lastPage = $products->lastPage();
     * $hasMore = $products->hasMorePages();
     */
    public static function paginate(int $perPage = 15, int $page = 1, ?string $path = null): \Toporia\Framework\Support\Pagination\Paginator
    {
        return static::query()->paginate($perPage, $page, $path);
    }

    /**
     * Get the first record or null.
     *
     * @return static|null
     */
    public static function first(): ?static
    {
        $row = static::query()->limit(1)->first();
        if (!$row) return null;

        $m = new static($row);
        $m->exists = true;
        $m->syncOriginal();
        return $m;
    }

    /**
     * Create a new instance and immediately persist it.
     *
     * @param array<string,mixed> $attributes
     * @return static
     */
    public static function create(array $attributes): static
    {
        $model = new static($attributes);
        $model->save();
        return $model;
    }

    /**
     * Persist the model: insert if new, otherwise update dirty attributes.
     */
    public function save(): bool
    {
        if ($this->exists) {
            return $this->performUpdate();
        }

        return $this->performInsert();
    }

    /**
     * Insert the model attributes and mark as existing.
     *
     * @internal Emits "creating" and "created" hooks.
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
     * Update dirty attributes on an existing model.
     *
     * @internal Emits "updating" and "updated" hooks.
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
     * Delete the model if it exists.
     *
     * @internal Emits "deleting" and "deleted" hooks.
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
     * Refresh the model state from the database by primary key.
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
     * Whether this instance exists in the database.
     */
    public function exists(): bool
    {
        return $this->exists;
    }

    /**
     * Mass-assign attributes using fillable/guarded rules.
     *
     * @param array<string,mixed> $attributes
     * @return $this
     */
    public function fill(array $attributes): self
    {
        foreach ($attributes as $key => $value) {
            // Ensure key is string for mass assignment
            $keyString = (string) $key;
            if ($this->isFillable($keyString)) {
                $this->setAttribute($keyString, $value);
            }
        }

        return $this;
    }

    /**
     * Check whether a key can be mass-assigned.
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
     * Get the current primary key value.
     */
    public function getKey(): mixed
    {
        return $this->getAttribute(static::$primaryKey);
    }

    /**
     * Get an attribute with casting applied if configured.
     */
    public function getAttribute(string $key): mixed
    {
        $value = $this->attributes[$key] ?? null;

        return $this->castAttribute($key, $value);
    }

    /**
     * Set a raw attribute value (no casting).
     */
    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    /**
     * Cast an attribute to a native type if configured.
     *
     * Supported types: int, float, string, bool, array, json, date (\DateTime).
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
     * Whether any attribute has changed from the original snapshot.
     */
    public function isDirty(): bool
    {
        return !empty($this->getDirty());
    }

    /**
     * Get the subset of attributes which differ from the original snapshot.
     *
     * @return array<string,mixed>
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
     * Replace the original snapshot with current attributes.
     */
    private function syncOriginal(): void
    {
        $this->original = $this->attributes;
    }

    /**
     * Update timestamps on the model (created_at on insert, updated_at always).
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
     * Dispatch a lifecycle hook if the corresponding method is implemented.
     *
     * Available hooks: creating, created, updating, updated, deleting, deleted.
     */
    private function fireEvent(string $event): void
    {
        $method = $event;

        if (method_exists($this, $method)) {
            $this->{$method}();
        }
    }

    /**
     * Convert the model to an array of raw attributes.
     *
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    /**
     * Convert the model to a JSON string.
     */
    public function toJson(): string
    {
        return json_encode($this->toArray());
    }

    /**
     * Magic getter: proxies to getAttribute().
     */
    public function __get(string $key): mixed
    {
        return $this->getAttribute($key);
    }

    /**
     * Magic setter: proxies to setAttribute().
     */
    public function __set(string $key, mixed $value): void
    {
        $this->setAttribute($key, $value);
    }

    /**
     * Magic isset: checks if attribute is present in the bag.
     */
    public function __isset(string $key): bool
    {
        return isset($this->attributes[$key]);
    }

    /**
     * Set a loaded relationship.
     *
     * @param string $name Relationship name
     * @param mixed $value Loaded models
     * @return $this
     */
    public function setRelation(string $name, mixed $value): self
    {
        $this->relations[$name] = $value;
        return $this;
    }

    /**
     * Get a loaded relationship.
     *
     * @param string $name Relationship name
     * @return mixed
     */
    public function getRelation(string $name): mixed
    {
        return $this->relations[$name] ?? null;
    }

    /**
     * Check if a relationship has been loaded.
     *
     * @param string $name Relationship name
     * @return bool
     */
    public function relationLoaded(string $name): bool
    {
        return array_key_exists($name, $this->relations);
    }

    /**
     * Create a typed collection for this model type.
     *
     * @param array<int,static> $models
     * @return ModelCollection<static>
     */
    protected function newCollection(array $models = []): ModelCollection
    {
        return new ModelCollection($models);
    }

    /**
     * Hydrate model instances from an array of database rows.
     *
     * @param array<int, array<string,mixed>> $rows
     * @return ModelCollection<static>
     */
    public static function hydrate(array $rows): ModelCollection
    {
        $out = [];
        foreach ($rows as $data) {
            $m = new static($data);
            $m->exists = true;
            $m->syncOriginal();
            $out[] = $m;
        }
        return (new static())->newCollection($out);
    }

    /**
     * Execute the current query and return a typed ModelCollection.
     *
     * Compatible with both RowCollection and plain array results from QueryBuilder::get().
     *
     * @return ModelCollection<static>
     */
    public static function get(): ModelCollection
    {
        $qb = static::query();
        $result = $qb->get(); // RowCollection|array

        if ($result instanceof RowCollection) {
            /** @var RowCollection $result */
            return static::hydrate($result->all());
        }

        /** @var array<int, array<string,mixed>> $result */
        return static::hydrate($result);
    }

    /**
     * Define a one-to-one relationship.
     *
     * @param class-string<Model> $related Related model class
     * @param string|null $foreignKey Foreign key on related table (default: {parent}_id)
     * @param string|null $localKey Local key on parent table (default: id)
     * @return Relations\HasOne
     */
    protected function hasOne(string $related, ?string $foreignKey = null, ?string $localKey = null): Relations\HasOne
    {
        $foreignKey = $foreignKey ?? $this->getForeignKey();
        $localKey = $localKey ?? static::$primaryKey;

        $query = call_user_func([$related, 'query']);

        return new Relations\HasOne($query, $this, $related, $foreignKey, $localKey);
    }

    /**
     * Define a one-to-many relationship.
     *
     * @param class-string<Model> $related Related model class
     * @param string|null $foreignKey Foreign key on related table (default: {parent}_id)
     * @param string|null $localKey Local key on parent table (default: id)
     * @return Relations\HasMany
     */
    protected function hasMany(string $related, ?string $foreignKey = null, ?string $localKey = null): Relations\HasMany
    {
        $foreignKey = $foreignKey ?? $this->getForeignKey();
        $localKey = $localKey ?? static::$primaryKey;

        $query = call_user_func([$related, 'query']);

        return new Relations\HasMany($query, $this, $related, $foreignKey, $localKey);
    }

    /**
     * Define an inverse one-to-one or one-to-many relationship.
     *
     * @param class-string<Model> $related Related model class
     * @param string|null $foreignKey Foreign key on current table (default: {related}_id)
     * @param string|null $ownerKey Primary key on related table (default: id)
     * @return Relations\BelongsTo
     */
    protected function belongsTo(string $related, ?string $foreignKey = null, ?string $ownerKey = null): Relations\BelongsTo
    {
        $foreignKey = $foreignKey ?? $this->guessBelongsToForeignKey($related);
        $ownerKey = $ownerKey ?? call_user_func([$related, 'getPrimaryKey']);

        $query = call_user_func([$related, 'query']);

        return new Relations\BelongsTo($query, $this, $related, $foreignKey, $ownerKey);
    }

    /**
     * Define a many-to-many relationship.
     *
     * @param class-string<Model> $related Related model class
     * @param string|null $pivotTable Pivot table name
     * @param string|null $foreignPivotKey Foreign key in pivot for parent
     * @param string|null $relatedPivotKey Foreign key in pivot for related
     * @param string|null $parentKey Parent primary key
     * @param string|null $relatedKey Related primary key
     * @return Relations\BelongsToMany
     */
    protected function belongsToMany(
        string $related,
        ?string $pivotTable = null,
        ?string $foreignPivotKey = null,
        ?string $relatedPivotKey = null,
        ?string $parentKey = null,
        ?string $relatedKey = null
    ): Relations\BelongsToMany {
        $foreignPivotKey = $foreignPivotKey ?? $this->getForeignKey();
        $relatedPivotKey = $relatedPivotKey ?? $this->getRelatedForeignKey($related);
        $parentKey = $parentKey ?? static::$primaryKey;
        $relatedKey = $relatedKey ?? call_user_func([$related, 'getPrimaryKey']);

        $query = call_user_func([$related, 'query']);

        return new Relations\BelongsToMany(
            $query,
            $this,
            $related,
            $pivotTable ?? $this->guessPivotTable($related),
            $foreignPivotKey,
            $relatedPivotKey,
            $parentKey,
            $relatedKey
        );
    }

    /**
     * Get the default foreign key name for this model.
     *
     * @return string
     */
    protected function getForeignKey(): string
    {
        $parts = explode('\\', static::class);
        $className = end($parts);
        return strtolower($className) . '_id';
    }

    /**
     * Get the foreign key name for a related model.
     *
     * @param class-string<Model> $related
     * @return string
     */
    protected function getRelatedForeignKey(string $related): string
    {
        $parts = explode('\\', $related);
        $className = end($parts);
        return strtolower($className) . '_id';
    }

    /**
     * Guess the belongs to foreign key.
     *
     * @param class-string<Model> $related
     * @return string
     */
    protected function guessBelongsToForeignKey(string $related): string
    {
        return $this->getRelatedForeignKey($related);
    }

    /**
     * Guess the pivot table name for a many-to-many relationship.
     *
     * @param class-string<Model> $related
     * @return string
     */
    protected function guessPivotTable(string $related): string
    {
        $models = [
            strtolower(basename(str_replace('\\', '/', static::class))),
            strtolower(basename(str_replace('\\', '/', $related)))
        ];

        sort($models);

        return implode('_', $models);
    }

    /**
     * Eager load relationships.
     *
     * @param array<string> $relations Relationship names to load
     * @return static
     */
    public function load(array $relations): static
    {
        foreach ($relations as $relation) {
            if (!$this->relationLoaded($relation)) {
                $result = $this->{$relation}();

                if ($result instanceof Relations\RelationInterface) {
                    $this->setRelation($relation, $result->getResults());
                }
            }
        }

        return $this;
    }

    /**
     * Static eager loading for query results.
     *
     * @param array<string> $relations Relationship names
     * @return QueryBuilder
     */
    public static function with(array $relations): QueryBuilder
    {
        $query = static::query();
        $query->setEagerLoad($relations);
        return $query;
    }
}

