<?php

declare(strict_types=1);

namespace Toporia\Framework\Database\ORM;

use Toporia\Framework\Database\Query\QueryBuilder;
use Toporia\Framework\Database\ConnectionInterface;

/**
 * Model Query Builder - Extends QueryBuilder with Model-aware functionality.
 *
 * Responsibilities:
 * - Convert raw database rows to Model instances
 * - Handle eager loading of relationships
 * - Return ModelCollection instead of RowCollection
 *
 * This class follows Clean Architecture by:
 * - Keeping Model logic separate from base QueryBuilder
 * - Base QueryBuilder remains framework-agnostic (works with raw arrays)
 * - ModelQueryBuilder adds ORM-specific behavior
 *
 * @template TModel of Model
 */
class ModelQueryBuilder extends QueryBuilder
{
    /**
     * @param ConnectionInterface $connection Database connection
     * @param class-string<TModel> $modelClass Model class to hydrate results into
     */
    public function __construct(
        ConnectionInterface $connection,
        private readonly string $modelClass
    ) {
        parent::__construct($connection);
    }

    /**
     * Execute the query and return a ModelCollection.
     *
     * This method:
     * 1. Gets raw rows from database
     * 2. Hydrates into model instances
     * 3. Loads eager relationships
     *
     * Note: Named getModels() instead of overriding get() due to PHP return type constraints.
     * PHP doesn't support return type variance (ModelCollection is not subtype of RowCollection).
     *
     * @return ModelCollection<TModel>
     */
    public function getModels(): ModelCollection
    {
        // Step 1: Get raw rows from parent QueryBuilder
        $rowCollection = parent::get();
        $rows = $rowCollection->all();

        // Step 2: Hydrate rows into models
        /** @var callable $hydrate */
        $hydrate = [$this->modelClass, 'hydrate'];
        $collection = $hydrate($rows);

        // Step 3: Load eager relationships if configured
        $eagerLoad = $this->getEagerLoad();
        if (!empty($eagerLoad) && !$collection->isEmpty()) {
            /** @var callable $eagerLoadRelations */
            $eagerLoadRelations = [$this->modelClass, 'eagerLoadRelations'];
            $eagerLoadRelations($collection, $eagerLoad);
        }

        return $collection;
    }

    /**
     * Paginate the query results with Model hydration.
     *
     * Overrides parent to return Paginator with ModelCollection.
     *
     * @param int $perPage Number of items per page (default: 15)
     * @param int $page Current page number (1-indexed, default: 1)
     * @param string|null $path Base URL path for pagination links
     * @return \Toporia\Framework\Support\Pagination\Paginator<TModel>
     */
    public function paginate(int $perPage = 15, int $page = 1, ?string $path = null): \Toporia\Framework\Support\Pagination\Paginator
    {
        // Validate parameters
        if ($perPage < 1) {
            throw new \InvalidArgumentException('Per page must be at least 1');
        }
        if ($page < 1) {
            throw new \InvalidArgumentException('Page must be at least 1');
        }

        // Step 1: Get total count (without limit/offset)
        $total = $this->count();

        // Step 2: Get paginated items as ModelCollection
        $offset = ($page - 1) * $perPage;
        $items = $this->limit($perPage)->offset($offset)->getModels(); // Hydrates and loads relationships

        // Step 3: Return Paginator value object
        return new \Toporia\Framework\Support\Pagination\Paginator(
            items: $items,
            total: $total,
            perPage: $perPage,
            currentPage: $page,
            path: $path
        );
    }

    /**
     * Spawn a fresh ModelQueryBuilder sharing the same connection and model class.
     */
    public function newQuery(): self
    {
        return new self($this->getConnection(), $this->modelClass);
    }

    // =========================================================================
    // RELATIONSHIP QUERY METHODS
    // =========================================================================

    /**
     * Filter models that have a related model matching the given constraints.
     *
     * More efficient than Laravel's implementation:
     * - Uses EXISTS subquery instead of JOIN when possible (better performance)
     * - Supports callback for complex constraints
     *
     * Clean Architecture & SOLID:
     * - Single Responsibility: Only adds WHERE EXISTS clause
     * - Open/Closed: Extensible via callback
     * - Dependency Inversion: Works with any RelationInterface
     *
     * @param string $relation Relationship method name
     * @param callable|null $callback Optional callback to constrain the relationship query
     * @param string $operator Comparison operator (>=, =, etc.)
     * @param int $count Minimum count (default: 1 means "has at least one")
     * @return $this
     *
     * @example
     * // Products that have at least one review
     * ProductModel::whereHas('reviews')->get();
     *
     * // Products with reviews rating >= 4
     * ProductModel::whereHas('reviews', function($query) {
     *     $query->where('rating', '>=', 4);
     * })->get();
     *
     * // Products with at least 5 reviews
     * ProductModel::whereHas('reviews', null, '>=', 5)->get();
     */
    public function whereHas(string $relation, ?callable $callback = null, string $operator = '>=', int $count = 1): self
    {
        // Get table name from model
        /** @var callable $getTableName */
        $getTableName = [$this->modelClass, 'getTableName'];
        $table = $getTableName();

        // Create a dummy model to get the relationship
        $model = new $this->modelClass([]);

        if (!method_exists($model, $relation)) {
            throw new \InvalidArgumentException("Relationship '{$relation}' does not exist on model {$this->modelClass}");
        }

        $relationInstance = $model->$relation();

        if (!$relationInstance instanceof Relations\RelationInterface) {
            throw new \InvalidArgumentException("Method '{$relation}' is not a valid relationship");
        }

        // Get the relationship's query builder
        $relationQuery = $relationInstance->getQuery();

        // Apply callback constraints if provided
        if ($callback !== null) {
            $callback($relationQuery);
        }

        // Get foreign and local keys for the EXISTS subquery
        $foreignKey = $relationInstance->getForeignKey();
        $localKey = $relationInstance->getLocalKey();

        // Get relation table
        $relationTable = $relationQuery->getTable();

        // Use alias for self-referencing relationships
        $relationAlias = $table === $relationTable ? "{$relationTable}_relation" : $relationTable;

        // Build EXISTS subquery with proper aliasing
        // Example: SELECT COUNT(*) FROM products AS products_relation WHERE products_relation.parent_id = products.id
        $fromClause = $table === $relationTable ? "{$relationTable} AS {$relationAlias}" : $relationTable;
        $subquerySql = "SELECT COUNT(*) FROM {$fromClause} WHERE {$relationAlias}.{$foreignKey} = {$table}.{$localKey}";

        // Add relation query wheres to subquery
        // Important: Inject bindings directly into SQL (same approach as withCount)
        // to avoid binding order issues
        $relationSql = $relationQuery->toSql();
        if (preg_match('/WHERE (.+?)(?:ORDER BY|LIMIT|$)/s', $relationSql, $matches)) {
            $whereClause = $matches[1];

            // Replace placeholders with actual values (escaped)
            $relationBindings = $relationQuery->getBindings();
            $boundWhereClause = $whereClause;
            foreach ($relationBindings as $binding) {
                // Escape and quote value
                $escaped = is_string($binding) ? "'" . addslashes($binding) . "'" : $binding;
                $boundWhereClause = preg_replace('/\?/', (string)$escaped, $boundWhereClause, 1);
            }

            $subquerySql .= " AND ({$boundWhereClause})";
        }

        // Add the EXISTS clause with count comparison
        // Example: WHERE (SELECT COUNT(*) ...) >= 1
        $this->whereRaw("({$subquerySql}) {$operator} ?", [$count]);

        return $this;
    }

    /**
     * Eager load relationships.
     *
     * Supports multiple syntaxes:
     * - with('relation')
     * - with(['relation'])
     * - with(['relation' => callback])
     * - with('relation:column1,column2')
     *
     * Clean Architecture & SOLID:
     * - Single Responsibility: Only configures eager loading
     * - Open/Closed: Extensible via callbacks
     * - Dependency Inversion: Works with any RelationInterface
     *
     * @param string|array|callable ...$relations Relationship specifications
     * @return $this
     *
     * @example
     * // Basic eager loading
     * $query->with('childrens')->get();
     *
     * // With column selection
     * $query->with('childrens:id,title,price')->get();
     *
     * // With callback constraints
     * $query->with(['childrens' => function($q) {
     *     $q->where('is_active', 1);
     * }])->get();
     *
     * // Multiple relationships
     * $query->with(['childrens', 'category'])->get();
     */
    public function with(string|array|callable ...$relations): self
    {
        // Delegate to Model's static method for normalization
        /** @var callable $normalizeMethod */
        $normalizeMethod = [$this->modelClass, 'normalizeEagerLoadRelations'];
        $normalized = $normalizeMethod($relations);

        // Merge with existing eager load configuration
        $existing = $this->getEagerLoad();
        $this->setEagerLoad(array_merge($existing, $normalized));

        return $this;
    }

    /**
     * Add a subselect count of a relationship to the query.
     *
     * More efficient than Laravel:
     * - Single query with subselect instead of separate query
     * - Automatically optimized by database engine
     *
     * Supports callbacks like with():
     * - withCount('reviews') - count all
     * - withCount(['reviews' => fn($q) => $q->where('rating', '>=', 4)]) - count with constraints
     *
     * @param string|array $relations Relationship name(s) or associative array with callbacks
     * @return $this
     *
     * @example
     * // Get products with review count
     * $products = ProductModel::withCount('reviews')->get();
     * // Access: $product->reviews_count
     *
     * // Multiple relationships
     * $products = ProductModel::withCount(['reviews', 'orders'])->get();
     *
     * // With callback constraints
     * $products = ProductModel::withCount(['reviews' => function($q) {
     *     $q->where('rating', '>=', 4);
     * }])->get();
     * // Access: $product->reviews_count (only counts reviews with rating >= 4)
     */
    public function withCount(string|array $relations): self
    {
        // Convert string to array
        if (is_string($relations)) {
            $relations = [$relations];
        }

        foreach ($relations as $key => $value) {
            // Case 1: 'relation' => callback
            if (is_string($key) && is_callable($value)) {
                $this->addRelationCountSelect($key, $value);
            }
            // Case 2: numeric key with string value (no callback)
            elseif (is_int($key) && is_string($value)) {
                $this->addRelationCountSelect($value, null);
            }
        }

        return $this;
    }

    /**
     * Add a subselect sum of a relationship column to the query.
     *
     * @param string $relation Relationship name
     * @param string $column Column to sum
     * @return $this
     *
     * @example
     * // Get users with total order amount
     * $users = UserModel::withSum('orders', 'total')->get();
     * // Access: $user->orders_sum_total
     */
    public function withSum(string $relation, string $column): self
    {
        return $this->addRelationAggregateSelect($relation, $column, 'SUM');
    }

    /**
     * Add a subselect average of a relationship column to the query.
     *
     * @param string $relation Relationship name
     * @param string $column Column to average
     * @return $this
     */
    public function withAvg(string $relation, string $column): self
    {
        return $this->addRelationAggregateSelect($relation, $column, 'AVG');
    }

    /**
     * Add a subselect minimum of a relationship column to the query.
     *
     * @param string $relation Relationship name
     * @param string $column Column to find minimum
     * @return $this
     */
    public function withMin(string $relation, string $column): self
    {
        return $this->addRelationAggregateSelect($relation, $column, 'MIN');
    }

    /**
     * Add a subselect maximum of a relationship column to the query.
     *
     * @param string $relation Relationship name
     * @param string $column Column to find maximum
     * @return $this
     */
    public function withMax(string $relation, string $column): self
    {
        return $this->addRelationAggregateSelect($relation, $column, 'MAX');
    }

    // =========================================================================
    // PRIVATE HELPER METHODS
    // =========================================================================

    /**
     * Add a relationship count subselect to the query.
     *
     * @param string $relation Relationship name
     * @param callable|null $callback Optional callback to constrain the count
     * @return void
     */
    private function addRelationCountSelect(string $relation, ?callable $callback = null): void
    {
        /** @var callable $getTableName */
        $getTableName = [$this->modelClass, 'getTableName'];
        $table = $getTableName();

        $model = new $this->modelClass([]);
        $relationInstance = $model->$relation();

        if (!$relationInstance instanceof Relations\RelationInterface) {
            throw new \InvalidArgumentException("Method '{$relation}' is not a valid relationship");
        }

        $relationQuery = $relationInstance->getQuery();

        // Apply callback constraints if provided
        if ($callback !== null) {
            $callback($relationQuery);
        }

        $foreignKey = $relationInstance->getForeignKey();
        $localKey = $relationInstance->getLocalKey();
        $relationTable = $relationQuery->getTable();

        // Use alias for self-referencing relationships (e.g., products.parent_id -> products.id)
        // This prevents ambiguity when parent and child tables are the same
        $relationAlias = $table === $relationTable ? "{$relationTable}_relation" : $relationTable;

        // Build subselect with proper aliasing
        // Example: (SELECT COUNT(*) FROM products AS products_relation WHERE products_relation.parent_id = products.id)
        $fromClause = $table === $relationTable ? "{$relationTable} AS {$relationAlias}" : $relationTable;
        $subquery = "SELECT COUNT(*) FROM {$fromClause} WHERE {$relationAlias}.{$foreignKey} = {$table}.{$localKey}";

        // Add relation query wheres to subquery
        // Important: We need to inject bindings directly into SQL because
        // selectRaw bindings are added to the end, but subquery bindings need to be
        // embedded within the subquery itself for correct ordering
        $relationSql = $relationQuery->toSql();
        if (preg_match('/WHERE (.+?)(?:ORDER BY|LIMIT|$)/s', $relationSql, $matches)) {
            $whereClause = $matches[1];

            // Replace placeholders with actual values (escaped)
            $relationBindings = $relationQuery->getBindings();
            $boundWhereClause = $whereClause;
            foreach ($relationBindings as $binding) {
                // Escape and quote value
                $escaped = is_string($binding) ? "'" . addslashes($binding) . "'" : $binding;
                $boundWhereClause = preg_replace('/\?/', (string)$escaped, $boundWhereClause, 1);
            }

            $subquery .= " AND ({$boundWhereClause})";
        }

        $columnAlias = "{$relation}_count";

        // Ensure we select table.* along with the subquery (only once)
        $columns = $this->getColumns();
        if (empty($columns) || !in_array("{$table}.*", $columns, true)) {
            $this->select("{$table}.*");
        }

        $this->selectRaw("({$subquery}) AS {$columnAlias}");
    }

    /**
     * Add a relationship aggregate subselect to the query.
     *
     * @param string $relation Relationship name
     * @param string $column Column to aggregate
     * @param string $function Aggregate function (SUM, AVG, MIN, MAX)
     * @return $this
     */
    private function addRelationAggregateSelect(string $relation, string $column, string $function): self
    {
        /** @var callable $getTableName */
        $getTableName = [$this->modelClass, 'getTableName'];
        $table = $getTableName();

        $model = new $this->modelClass([]);
        $relationInstance = $model->$relation();

        if (!$relationInstance instanceof Relations\RelationInterface) {
            throw new \InvalidArgumentException("Method '{$relation}' is not a valid relationship");
        }

        $relationQuery = $relationInstance->getQuery();
        $foreignKey = $relationInstance->getForeignKey();
        $localKey = $relationInstance->getLocalKey();
        $relationTable = $relationQuery->getTable();

        // Use alias for self-referencing relationships
        $relationAlias = $table === $relationTable ? "{$relationTable}_relation" : $relationTable;

        // Build subselect with proper aliasing
        $fromClause = $table === $relationTable ? "{$relationTable} AS {$relationAlias}" : $relationTable;
        $subquery = "SELECT {$function}({$relationAlias}.{$column}) FROM {$fromClause} WHERE {$relationAlias}.{$foreignKey} = {$table}.{$localKey}";

        $functionLower = strtolower($function);
        $columnAlias = "{$relation}_{$functionLower}_{$column}";

        // Ensure we select table.* along with the subquery (only once)
        $columns = $this->getColumns();
        if (empty($columns) || !in_array("{$table}.*", $columns, true)) {
            $this->select("{$table}.*");
        }

        $this->selectRaw("({$subquery}) AS {$columnAlias}");

        return $this;
    }
}
