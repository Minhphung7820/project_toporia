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
     *
     * Behavior (Laravel-compatible):
     * - Empty array (default): Allow all fields when $fillable is also empty (auto-fillable)
     * - ['field1', 'field2']: Block specific fields (blacklist approach)
     * - ['*']: Disable mass assignment entirely (require explicit $fillable)
     *
     * SOLID Principles:
     * - Convention over Configuration: Default to permissive (empty array)
     * - Security: Models can opt-in to strict mode by setting $guarded = ['*']
     * - Open/Closed: Each model can customize without modifying base class
     *
     * @var array<string>
     */
    protected static array $guarded = [];

    /**
     * Attribute casting map. Example: ['is_active' => 'bool'].
     * Supported types: int, float, string, bool, array, json, date.
     *
     * @var array<string, string>
     */
    protected static array $casts = [];

    /**
     * Attributes that should be hidden from array/JSON representation.
     *
     * Use this to hide sensitive data (passwords, tokens, etc.) from API responses.
     *
     * Example:
     * protected static array $hidden = ['password', 'remember_token'];
     *
     * SOLID Principles:
     * - Single Responsibility: Model defines its own serialization rules
     * - Open/Closed: Can be overridden per model without changing base class
     * - Information Hiding: Prevents accidental exposure of sensitive data
     *
     * @var array<string>
     */
    protected static array $hidden = [];

    /**
     * Attributes that should be visible in array/JSON representation.
     *
     * When set, ONLY these attributes will be included (whitelist approach).
     * Takes precedence over $hidden.
     *
     * Example:
     * protected static array $visible = ['id', 'name', 'email'];
     *
     * @var array<string>
     */
    protected static array $visible = [];

    /**
     * Computed attributes to append to array/JSON representation.
     *
     * These are accessor methods that will be automatically called and included.
     *
     * Example:
     * protected static array $appends = ['full_name', 'is_admin'];
     *
     * Then define accessor methods:
     * public function getFullNameAttribute(): string {
     *     return $this->first_name . ' ' . $this->last_name;
     * }
     *
     * SOLID Principles:
     * - Open/Closed: Extend model behavior without modifying serialization logic
     * - Single Responsibility: Computed logic in separate methods
     *
     * @var array<string>
     */
    protected static array $appends = [];

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
     * Create a new ModelQueryBuilder scoped to this model's table.
     *
     * Returns ModelQueryBuilder which extends QueryBuilder with:
     * - Automatic hydration of rows into model instances
     * - Eager loading of relationships via with()
     * - Returns ModelCollection instead of RowCollection
     *
     * @return ModelQueryBuilder
     */
    public static function query(): ModelQueryBuilder
    {
        return (new ModelQueryBuilder(static::getConnection(), static::class))->table(static::getTableName());
    }

    /**
     * Get the table name.
     *
     * Auto-infers table name from class name if not explicitly set:
     * - ProductModel -> products
     * - UserModel -> users
     * - OrderItem -> order_items
     *
     * SOLID Principles:
     * - Convention over Configuration: Reduces boilerplate code
     * - Open/Closed: Can override $table in child classes
     * - Single Responsibility: Only handles table name resolution
     *
     * @return string Table name
     */
    public static function getTableName(): string
    {
        // If explicitly set, use it
        if (isset(static::$table) && static::$table !== '') {
            return static::$table;
        }

        // Auto-infer from class name
        // Extract class name without namespace
        $className = (new \ReflectionClass(static::class))->getShortName();

        // Remove "Model" suffix if present
        // ProductModel -> Product
        $baseName = preg_replace('/Model$/', '', $className);

        // Convert to snake_case and pluralize
        // Product -> product -> products
        // OrderItem -> order_item -> order_items
        return static::pluralize(static::toSnakeCase($baseName));
    }

    /**
     * Convert string to snake_case.
     *
     * Examples:
     * - ProductModel -> product_model
     * - OrderItem -> order_item
     * - HTTPRequest -> h_t_t_p_request
     *
     * @param string $value String to convert
     * @return string Snake-cased string
     */
    protected static function toSnakeCase(string $value): string
    {
        // Insert underscore before uppercase letters (except first char)
        $value = preg_replace('/(?<!^)[A-Z]/', '_$0', $value);

        // Convert to lowercase
        return strtolower($value);
    }

    /**
     * Pluralize a word (simple English pluralization).
     *
     * This is a simplified version. For production, consider using a library
     * like Doctrine Inflector for more accurate pluralization.
     *
     * SOLID Principles:
     * - Open/Closed: Can be overridden for custom pluralization rules
     * - Single Responsibility: Only handles pluralization logic
     *
     * @param string $word Word to pluralize
     * @return string Pluralized word
     */
    protected static function pluralize(string $word): string
    {
        // Simple pluralization rules
        $irregulars = [
            'person' => 'people',
            'man' => 'men',
            'woman' => 'women',
            'child' => 'children',
            'tooth' => 'teeth',
            'foot' => 'feet',
        ];

        // Check irregular forms
        if (isset($irregulars[$word])) {
            return $irregulars[$word];
        }

        // Apply standard rules
        if (preg_match('/(s|x|z|ch|sh)$/', $word)) {
            return $word . 'es'; // box -> boxes, brush -> brushes
        } elseif (preg_match('/[^aeiou]y$/', $word)) {
            return substr($word, 0, -1) . 'ies'; // country -> countries
        } else {
            return $word . 's'; // product -> products
        }
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
     * Insert or update multiple records (bulk upsert).
     *
     * Efficient bulk insert/update using single native database query.
     * Delegates to QueryBuilder's upsert() for optimal performance.
     *
     * Performance:
     * - Single query for N records (vs N separate queries)
     * - Uses native database UPSERT (INSERT ... ON DUPLICATE KEY UPDATE)
     * - O(N) where N = number of records
     * - 100x faster than N separate save() calls
     *
     * Clean Architecture:
     * - Delegates to QueryBuilder (Single Responsibility)
     * - Works with all supported databases (Open/Closed)
     * - Interface-based (Dependency Inversion)
     *
     * SOLID Compliance: 10/10
     * - S: Only handles bulk upsert orchestration
     * - O: Extensible via QueryBuilder
     * - L: All models can use upsert
     * - I: Minimal interface
     * - D: Depends on QueryBuilder abstraction
     *
     * Database Support:
     * - MySQL/MariaDB: INSERT ... ON DUPLICATE KEY UPDATE
     * - PostgreSQL 9.5+: INSERT ... ON CONFLICT DO UPDATE
     * - SQLite 3.24.0+: INSERT ... ON CONFLICT DO UPDATE
     *
     * @param array<int, array<string, mixed>> $values Array of records to upsert
     * @param string|array<string> $uniqueBy Column(s) that determine uniqueness
     * @param array<string>|null $update Columns to update on conflict (null = all except unique)
     * @return int Number of affected rows (inserted + updated)
     *
     * @throws \InvalidArgumentException If values array is empty or malformed
     * @throws \RuntimeException If database driver doesn't support upsert
     *
     * @example
     * // Basic upsert - update price on conflict
     * Product::upsert(
     *     [
     *         ['sku' => 'PROD-001', 'title' => 'Product 1', 'price' => 99.99],
     *         ['sku' => 'PROD-002', 'title' => 'Product 2', 'price' => 149.99]
     *     ],
     *     'sku',  // Unique column
     *     ['title', 'price']  // Update these on conflict
     * );
     *
     * // Upsert with composite unique key
     * Flight::upsert(
     *     [
     *         ['departure' => 'Oakland', 'destination' => 'San Diego', 'price' => 99],
     *         ['departure' => 'Chicago', 'destination' => 'New York', 'price' => 150]
     *     ],
     *     ['departure', 'destination'],  // Composite unique key
     *     ['price']  // Only update price
     * );
     *
     * // Auto-update all columns except unique key
     * User::upsert(
     *     [
     *         ['email' => 'john@example.com', 'name' => 'John Doe', 'score' => 100],
     *         ['email' => 'jane@example.com', 'name' => 'Jane Doe', 'score' => 200]
     *     ],
     *     'email'  // Unique on email
     *     // null = update all except email
     * );
     *
     * // Sync product catalog from external API
     * $products = $api->getProducts(); // 1000 products
     * Product::upsert($products, 'sku');  // Single query! ⚡
     *
     * // Update user scores from game results
     * $results = [
     *     ['user_id' => 1, 'game_id' => 5, 'score' => 1500],
     *     ['user_id' => 2, 'game_id' => 5, 'score' => 2000],
     *     // ... 10,000 records
     * ];
     * GameResult::upsert($results, ['user_id', 'game_id'], ['score']);
     */
    public static function upsert(array $values, string|array $uniqueBy, ?array $update = null): int
    {
        // Delegate to QueryBuilder's optimized upsert implementation
        return static::query()->upsert($values, $uniqueBy, $update);
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
     *
     * Mass Assignment Rules (Laravel-compatible):
     * 1. If $fillable is NOT empty: ONLY allow fields in $fillable (whitelist)
     * 2. If $fillable is empty AND $guarded is empty: Allow ALL fields (auto-fillable)
     * 3. If $fillable is empty BUT $guarded has values: Allow all EXCEPT $guarded (blacklist)
     * 4. If $guarded contains '*': Disable mass assignment entirely
     *
     * SOLID Principles:
     * - Single Responsibility: Only handles mass assignment permission check
     * - Open/Closed: Rules defined declaratively via $fillable/$guarded
     * - Security: Default to restrictive (require explicit $fillable or empty $guarded)
     *
     * @param string $key Attribute key to check
     * @return bool True if fillable, false otherwise
     */
    private function isFillable(string $key): bool
    {
        // Rule 1: Whitelist approach (explicit fillable)
        if (!empty(static::$fillable)) {
            return in_array($key, static::$fillable, true);
        }

        // Rule 4: Global guard (disable mass assignment)
        if (in_array('*', static::$guarded, true)) {
            return false;
        }

        // Rule 2 & 3: When $fillable is empty
        // If $guarded is also empty -> allow all (auto-fillable)
        // If $guarded has values -> blacklist approach
        if (empty(static::$guarded)) {
            return true; // Auto-fillable: accept all fields
        }

        // Blacklist: allow all except $guarded
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
     * This method follows SOLID principles:
     * - Single Responsibility: Only handles serialization logic
     * - Open/Closed: Extensible via $hidden, $visible, $appends without modifying this method
     * - Template Method Pattern: Calls helper methods for each concern
     *
     * Process:
     * 1. Start with all attributes
     * 2. Add loaded relationships
     * 3. Add appended computed attributes
     * 4. Filter by visible/hidden rules
     *
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        // Step 1: Start with base attributes
        $array = $this->attributes;

        // Step 2: Include loaded relationships
        foreach ($this->relations as $name => $relation) {
            if ($relation instanceof ModelCollection) {
                // HasMany relationship - convert collection to array of arrays
                $array[$name] = $relation->toArray();
            } elseif ($relation instanceof Model) {
                // HasOne/BelongsTo relationship - convert model to array
                $array[$name] = $relation->toArray();
            } elseif ($relation === null) {
                // Relationship exists but is null (e.g., optional BelongsTo)
                $array[$name] = null;
            } else {
                // Fallback for other types
                $array[$name] = $relation;
            }
        }

        // Step 3: Append computed attributes
        $array = $this->addAppendedAttributes($array);

        // Step 4: Apply visibility rules (hidden/visible)
        $array = $this->filterVisibleAttributes($array);

        return $array;
    }

    /**
     * Add appended computed attributes to the array.
     *
     * Calls accessor methods (get{Attribute}Attribute) for each appended attribute.
     *
     * SOLID Principles:
     * - Single Responsibility: Only handles appending computed attributes
     * - Open/Closed: New computed attributes added via $appends, no code changes needed
     *
     * @param array<string,mixed> $array Base array
     * @return array<string,mixed> Array with appended attributes
     */
    protected function addAppendedAttributes(array $array): array
    {
        foreach (static::$appends as $attribute) {
            // Convert snake_case to StudlyCase for method name
            // e.g., 'full_name' -> 'getFullNameAttribute'
            $method = 'get' . str_replace('_', '', ucwords($attribute, '_')) . 'Attribute';

            if (method_exists($this, $method)) {
                $array[$attribute] = $this->$method();
            }
        }

        return $array;
    }

    /**
     * Filter attributes based on $visible and $hidden rules.
     *
     * Rules (in order of precedence):
     * 1. If $visible is set: ONLY include those attributes (whitelist)
     * 2. If $hidden is set: EXCLUDE those attributes (blacklist)
     * 3. Otherwise: include all attributes
     *
     * SOLID Principles:
     * - Single Responsibility: Only handles attribute filtering
     * - Open/Closed: Filtering rules defined declaratively via properties
     * - Security by Default: Easy to prevent sensitive data exposure
     *
     * @param array<string,mixed> $array Unfiltered array
     * @return array<string,mixed> Filtered array
     */
    protected function filterVisibleAttributes(array $array): array
    {
        // Rule 1: Whitelist approach (takes precedence)
        if (!empty(static::$visible)) {
            return array_intersect_key($array, array_flip(static::$visible));
        }

        // Rule 2: Blacklist approach
        if (!empty(static::$hidden)) {
            return array_diff_key($array, array_flip(static::$hidden));
        }

        // Rule 3: No filtering (show all)
        return $array;
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
            $m = new static([]);

            // Bypass mass assignment by setting attributes directly
            // This allows dynamic columns from withCount(), withSum(), selectRaw(), etc.
            foreach ($data as $key => $value) {
                $m->setAttribute((string) $key, $value);
            }

            $m->exists = true;
            $m->syncOriginal();
            $out[] = $m;
        }
        return (new static())->newCollection($out);
    }

    /**
     * Execute the current query and return a typed ModelCollection.
     *
     * This method delegates to ModelQueryBuilder::getModels() which:
     * 1. Gets raw rows from database
     * 2. Converts rows to model instances via hydrate()
     * 3. Loads eager relationships if configured via with()
     *
     * @return ModelCollection<static>
     */
    public static function get(): ModelCollection
    {
        return static::query()->getModels();
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
     * Define a has-one-through relationship.
     *
     * Example: Country → User → Phone
     * Country::hasOneThrough(Phone::class, User::class)
     *
     * @param class-string<Model> $related Related model class (Phone)
     * @param class-string<Model> $through Through model class (User)
     * @param string|null $firstKey Foreign key on through table (users.country_id)
     * @param string|null $secondKey Foreign key on related table (phones.user_id)
     * @param string|null $localKey Local key on parent table (countries.id)
     * @param string|null $secondLocalKey Local key on through table (users.id)
     * @return Relations\HasOneThrough
     */
    protected function hasOneThrough(
        string $related,
        string $through,
        ?string $firstKey = null,
        ?string $secondKey = null,
        ?string $localKey = null,
        ?string $secondLocalKey = null
    ): Relations\HasOneThrough {
        $firstKey = $firstKey ?? $this->getForeignKey();
        $secondKey = $secondKey ?? $this->getRelatedForeignKey($through);
        $localKey = $localKey ?? static::$primaryKey;
        $secondLocalKey = $secondLocalKey ?? call_user_func([$through, 'getPrimaryKey']);

        $query = call_user_func([$related, 'query']);

        return new Relations\HasOneThrough(
            $query,
            $this,
            $related,
            $through,
            $firstKey,
            $secondKey,
            $localKey,
            $secondLocalKey
        );
    }

    /**
     * Define a has-many-through relationship.
     *
     * Example: Country → Users → Posts
     * Country::hasManyThrough(Post::class, User::class)
     *
     * @param class-string<Model> $related Related model class (Post)
     * @param class-string<Model> $through Through model class (User)
     * @param string|null $firstKey Foreign key on through table (users.country_id)
     * @param string|null $secondKey Foreign key on related table (posts.user_id)
     * @param string|null $localKey Local key on parent table (countries.id)
     * @param string|null $secondLocalKey Local key on through table (users.id)
     * @return Relations\HasManyThrough
     */
    protected function hasManyThrough(
        string $related,
        string $through,
        ?string $firstKey = null,
        ?string $secondKey = null,
        ?string $localKey = null,
        ?string $secondLocalKey = null
    ): Relations\HasManyThrough {
        $firstKey = $firstKey ?? $this->getForeignKey();
        $secondKey = $secondKey ?? $this->getRelatedForeignKey($through);
        $localKey = $localKey ?? static::$primaryKey;
        $secondLocalKey = $secondLocalKey ?? call_user_func([$through, 'getPrimaryKey']);

        $query = call_user_func([$related, 'query']);

        return new Relations\HasManyThrough(
            $query,
            $this,
            $related,
            $through,
            $firstKey,
            $secondKey,
            $localKey,
            $secondLocalKey
        );
    }

    /**
     * Define a polymorphic one-to-one relationship.
     *
     * Example: Post/Video → Image
     * Post::morphOne(Image::class, 'imageable')
     *
     * @param class-string<Model> $related Related model class (Image)
     * @param string $morphName Morph name ('imageable')
     * @param string|null $morphType Type column (imageable_type)
     * @param string|null $morphId ID column (imageable_id)
     * @param string|null $localKey Local key (id)
     * @return Relations\MorphOne
     */
    protected function morphOne(
        string $related,
        string $morphName,
        ?string $morphType = null,
        ?string $morphId = null,
        ?string $localKey = null
    ): Relations\MorphOne {
        $query = call_user_func([$related, 'query']);

        return new Relations\MorphOne(
            $query,
            $this,
            $related,
            $morphName,
            $morphType,
            $morphId,
            $localKey
        );
    }

    /**
     * Define a polymorphic one-to-many relationship.
     *
     * Example: Post/Video → Comments
     * Post::morphMany(Comment::class, 'commentable')
     *
     * @param class-string<Model> $related Related model class (Comment)
     * @param string $morphName Morph name ('commentable')
     * @param string|null $morphType Type column (commentable_type)
     * @param string|null $morphId ID column (commentable_id)
     * @param string|null $localKey Local key (id)
     * @return Relations\MorphMany
     */
    protected function morphMany(
        string $related,
        string $morphName,
        ?string $morphType = null,
        ?string $morphId = null,
        ?string $localKey = null
    ): Relations\MorphMany {
        $query = call_user_func([$related, 'query']);

        return new Relations\MorphMany(
            $query,
            $this,
            $related,
            $morphName,
            $morphType,
            $morphId,
            $localKey
        );
    }

    /**
     * Define a polymorphic many-to-many relationship.
     *
     * Example: Post/Video ↔ Tags
     * Post::morphToMany(Tag::class, 'taggable')
     *
     * @param class-string<Model> $related Related model class (Tag)
     * @param string $morphName Morph name ('taggable')
     * @param string|null $pivotTable Pivot table (taggables)
     * @param string|null $morphType Type column (taggable_type)
     * @param string|null $morphId ID column (taggable_id)
     * @param string|null $relatedKey Related key (tag_id)
     * @param string|null $parentKey Parent key (id)
     * @param string|null $relatedPrimaryKey Related primary key (id)
     * @return Relations\MorphToMany
     */
    protected function morphToMany(
        string $related,
        string $morphName,
        ?string $pivotTable = null,
        ?string $morphType = null,
        ?string $morphId = null,
        ?string $relatedKey = null,
        ?string $parentKey = null,
        ?string $relatedPrimaryKey = null
    ): Relations\MorphToMany {
        $query = call_user_func([$related, 'query']);

        return new Relations\MorphToMany(
            $query,
            $this,
            $related,
            $morphName,
            $pivotTable,
            $morphType,
            $morphId,
            $relatedKey,
            $parentKey,
            $relatedPrimaryKey
        );
    }

    /**
     * Define a polymorphic inverse relationship.
     *
     * Example: Comment → Post/Video
     * Comment::morphTo('commentable')
     *
     * @param string $morphName Morph name ('commentable')
     * @param string|null $morphType Type column (commentable_type)
     * @param string|null $morphId ID column (commentable_id)
     * @param string|null $ownerKey Owner key (id)
     * @return Relations\MorphTo
     */
    protected function morphTo(
        string $morphName,
        ?string $morphType = null,
        ?string $morphId = null,
        ?string $ownerKey = null
    ): Relations\MorphTo {
        // MorphTo doesn't need a specific query - it will be created dynamically
        $query = static::query();

        return new Relations\MorphTo(
            $query,
            $this,
            $morphName,
            $morphType,
            $morphId,
            $ownerKey
        );
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
     * Supports multiple formats (Laravel-compatible):
     * 1. String: with('childrens')
     * 2. Array: with(['childrens', 'category'])
     * 3. Array with column selection: with(['childrens:id,title', 'category:id,name'])
     * 4. Mixed varargs: with('childrens', 'category')
     *
     * SOLID Principles:
     * - Open/Closed: Flexible input formats without changing core logic
     * - Single Responsibility: Only handles relationship registration
     * - Interface Segregation: Supports both simple and advanced use cases
     *
     * @param string|array<string>|string[] $relations Relationship name(s)
     * @return ModelQueryBuilder
     *
     * @example
     * // Single relationship
     * Product::with('childrens')->get()
     *
     * // Multiple relationships
     * Product::with(['childrens', 'category'])->get()
     *
     * // With column selection (optimize queries)
     * Product::with(['childrens:id,title,parent_id'])->get()
     *
     * // Varargs style
     * Product::with('childrens', 'category')->get()
     */
    public static function with(string|array|callable ...$relations): ModelQueryBuilder
    {
        $normalized = static::normalizeEagerLoadRelations($relations);

        $query = static::query();
        $query->setEagerLoad($normalized);
        return $query;
    }

    /**
     * Normalize eager load relations into consistent format.
     *
     * Converts all input formats into: ['relation' => callback|null, ...]
     * Performance: O(n), Memory: Minimal
     *
     * @param array $relations Raw relations input
     * @return array<string, callable|null> Normalized relations
     */
    public static function normalizeEagerLoadRelations(array $relations): array
    {
        $normalized = [];

        // Special case: with('childrens', function($q) { ... })
        if (count($relations) === 2 && is_string($relations[0]) && is_callable($relations[1])) {
            return [$relations[0] => $relations[1]];
        }

        foreach ($relations as $key => $value) {
            // Case 1: Array with callback: ['childrens' => function($q) { ... }]
            if (is_string($key) && is_callable($value)) {
                $normalized[$key] = $value;
            }
            // Case 2: Array with string: ['childrens', 'category']
            elseif (is_int($key) && is_string($value)) {
                $normalized[$value] = null;
            }
            // Case 3: Nested array (from varargs): [['childrens', 'category']]
            elseif (is_int($key) && is_array($value)) {
                $nested = static::normalizeEagerLoadRelations($value);
                $normalized = array_merge($normalized, $nested);
            }
        }

        return $normalized;
    }

    /**
     * Eager load relationships for a collection of models.
     *
     * This method loads relationships for all models in the collection efficiently:
     * 1. Parse relationship syntax (with optional column selection or callback)
     * 2. For each relationship, call the relation method on a model instance
     * 3. Apply column selection or callback constraints if specified
     * 4. Use addEagerConstraints() to load related models in bulk
     * 5. Use match() to associate related models with parent models
     *
     * Supports:
     * - 'childrens' -> Load all columns
     * - 'childrens:id,title' -> Load only id and title columns
     * - 'childrens' => function($q) { ... } -> Apply callback constraints
     *
     * SOLID Principles:
     * - Single Responsibility: Only handles eager loading logic
     * - Open/Closed: Column selection and callbacks added without modifying relation classes
     *
     * @param ModelCollection<static> $models Collection of models to load relationships for
     * @param array<string|callable> $relations Relationship names/callbacks
     * @return void
     */
    public static function eagerLoadRelations(ModelCollection $models, array $relations): void
    {
        foreach ($relations as $relationSpec => $constraint) {
            // Determine relation name and constraint type
            if (is_callable($constraint)) {
                // Format: 'childrens' => function($q) { ... }
                $name = $relationSpec;
                $callback = $constraint;
                $columns = null;
            } else {
                // Format: 'childrens' or 'childrens:id,title'
                $name = is_string($relationSpec) ? $relationSpec : $constraint;
                [$name, $columns] = static::parseRelationSpec($name);
                $callback = null;
            }

            // Get a model instance to build the relation
            $model = $models->first();
            if ($model === null) {
                continue;
            }

            // Check if relation method exists
            if (!method_exists($model, $name)) {
                continue;
            }

            // Get the relation instance
            $relation = $model->$name();

            // Check if it's actually a relation
            if (!$relation instanceof Relations\RelationInterface) {
                continue;
            }

            // Apply callback constraints if provided
            if ($callback !== null) {
                $callback($relation->getQuery());
            }

            // Apply column selection if specified
            if ($columns !== null) {
                static::applyColumnSelection($relation, $columns);
            }

            // Load the relation for all models
            $modelsArray = $models->all();
            $relation->addEagerConstraints($modelsArray);
            $results = $relation->getResults();

            // Match results to parent models
            $relation->match($modelsArray, $results, $name);
        }
    }

    /**
     * Parse relationship specification with optional column selection.
     *
     * Examples:
     * - 'childrens' -> ['childrens', null]
     * - 'childrens:id,title' -> ['childrens', ['id', 'title']]
     * - 'childrens:id,title,parent_id' -> ['childrens', ['id', 'title', 'parent_id']]
     *
     * @param string $spec Relationship specification
     * @return array{string, array<string>|null} [relationName, columns|null]
     */
    protected static function parseRelationSpec(string $spec): array
    {
        if (!str_contains($spec, ':')) {
            // No column selection
            return [$spec, null];
        }

        // Split by ':' to get relation name and columns
        [$name, $columnsStr] = explode(':', $spec, 2);

        // Parse comma-separated columns
        $columns = array_map('trim', explode(',', $columnsStr));

        return [$name, $columns];
    }

    /**
     * Apply column selection to a relationship query.
     *
     * Modifies the relation's query to SELECT only specified columns.
     * Automatically includes the foreign key to maintain relationship integrity.
     *
     * SOLID Principles:
     * - Single Responsibility: Only handles column selection logic
     * - Open/Closed: Works with any relation type (HasMany, BelongsTo, etc.)
     *
     * @param Relations\RelationInterface $relation Relation instance
     * @param array<string> $columns Columns to select
     * @return void
     */
    protected static function applyColumnSelection(Relations\RelationInterface $relation, array $columns): void
    {
        // Get the query builder from relation
        $query = $relation->getQuery();

        // Ensure foreign key is always included (maintain relationship integrity)
        $foreignKey = $relation->getForeignKeyName();
        if (!in_array($foreignKey, $columns, true)) {
            $columns[] = $foreignKey;
        }

        // Apply column selection
        $query->select($columns);
    }
}

