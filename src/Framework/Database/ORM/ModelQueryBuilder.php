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
}
