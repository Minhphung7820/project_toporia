<?php

declare(strict_types=1);

namespace Toporia\Framework\Database\ORM\Relations;

use Toporia\Framework\Database\ORM\Model;
use Toporia\Framework\Database\ORM\ModelCollection;

/**
 * Morph To Many Relationship
 *
 * Represents a polymorphic many-to-many relationship.
 *
 * Example: Post/Video ↔ Tags
 * - posts.id
 * - videos.id
 * - tags.id
 * - taggables.tag_id (foreign key to tags)
 * - taggables.taggable_id (polymorphic foreign key)
 * - taggables.taggable_type (polymorphic type: 'Post' or 'Video')
 *
 * Post::morphToMany(Tag::class, 'taggable')
 *
 * SQL Generated:
 * SELECT tags.*, taggables.*
 * FROM tags
 * INNER JOIN taggables ON tags.id = taggables.tag_id
 * WHERE taggables.taggable_type = 'Post'
 * AND taggables.taggable_id = ?
 *
 * Use Case: Multiple models share many-to-many relationship with same related model.
 * Real examples:
 * - Posts, Videos, Articles ↔ Tags
 * - Users, Teams, Projects ↔ Permissions
 * - Products, Services, Bundles ↔ Categories
 *
 * Performance: O(1) query with JOIN for single parent - optimal!
 *              Eager loading: O(N) where N = distinct polymorphic types
 *              (Grouped by type for efficiency)
 *
 * SOLID Principles:
 * - Single Responsibility: Only handles polymorphic many-to-many
 * - Open/Closed: New morphable types work without modification
 * - Liskov Substitution: Implements RelationInterface
 * - Interface Segregation: Minimal interface
 * - Dependency Inversion: Depends on QueryBuilder abstraction
 */
class MorphToMany extends Relation
{
    /**
     * @param \Toporia\Framework\Database\Query\QueryBuilder $query Query builder
     * @param Model $parent Parent model (Post or Video)
     * @param class-string<Model> $relatedClass Related model class (Tag)
     * @param string $morphName Morph name ('taggable')
     * @param string|null $pivotTable Pivot table name (taggables)
     * @param string|null $morphType Type column (taggable_type)
     * @param string|null $morphId ID column (taggable_id)
     * @param string|null $relatedKey Related key (tag_id)
     * @param string|null $parentKey Parent key (id)
     * @param string|null $relatedPrimaryKey Related primary key (id)
     */
    public function __construct(
        \Toporia\Framework\Database\Query\QueryBuilder $query,
        Model $parent,
        protected string $relatedClass,
        protected string $morphName,
        ?string $pivotTable = null,
        ?string $morphType = null,
        ?string $morphId = null,
        ?string $relatedKey = null,
        ?string $parentKey = null,
        ?string $relatedPrimaryKey = null
    ) {
        $this->pivotTable = $pivotTable ?? $this->guessPivotTable();
        $this->morphType = $morphType ?? "{$morphName}_type";
        $this->foreignKey = $morphId ?? "{$morphName}_id";
        $this->relatedPivotKey = $relatedKey ?? $this->guessRelatedKey();
        $this->localKey = $parentKey ?? $parent::getPrimaryKey();
        $this->relatedKey = $relatedPrimaryKey ?? call_user_func([$relatedClass, 'getPrimaryKey']);

        parent::__construct($query, $parent, $this->foreignKey, $this->localKey);

        $this->performJoin();
    }

    /**
     * Guess pivot table name.
     *
     * @return string
     */
    protected function guessPivotTable(): string
    {
        // Use morph name + 's' (taggable -> taggables)
        return $this->morphName . 's';
    }

    /**
     * Guess related key name.
     *
     * @return string
     */
    protected function guessRelatedKey(): string
    {
        $parts = explode('\\', $this->relatedClass);
        $className = strtolower(end($parts));
        return "{$className}_id";
    }

    /**
     * Get morph class name for parent.
     *
     * @return string
     */
    protected function getMorphClass(): string
    {
        $class = get_class($this->parent);
        $parts = explode('\\', $class);
        return end($parts);
    }

    /**
     * Perform join with pivot table.
     *
     * @return void
     */
    protected function performJoin(): void
    {
        $relatedTable = call_user_func([$this->relatedClass, 'getTableName']);

        // Join pivot table to related table
        // INNER JOIN taggables ON tags.id = taggables.tag_id
        $this->query->join(
            $this->pivotTable,
            "{$relatedTable}.{$this->relatedKey}",
            '=',
            "{$this->pivotTable}.{$this->relatedPivotKey}"
        );

        // Add morph constraints
        if ($this->parent->exists()) {
            // WHERE taggables.taggable_type = 'Post'
            $this->query->where(
                "{$this->pivotTable}.{$this->morphType}",
                $this->getMorphClass()
            );

            // AND taggables.taggable_id = ?
            $this->query->where(
                "{$this->pivotTable}.{$this->foreignKey}",
                $this->parent->getAttribute($this->localKey)
            );
        }
    }

    /**
     * {@inheritdoc}
     *
     * @return ModelCollection
     */
    public function getResults(): ModelCollection
    {
        $relatedTable = call_user_func([$this->relatedClass, 'getTableName']);

        // Select related table columns + pivot columns
        $this->query->select("{$relatedTable}.*");

        $rowCollection = $this->query->get();

        $rows = $rowCollection instanceof \Toporia\Framework\Database\Query\RowCollection
            ? $rowCollection->all()
            : $rowCollection;

        if (empty($rows)) {
            return new ModelCollection([]);
        }

        return call_user_func([$this->relatedClass, 'hydrate'], $rows);
    }

    /**
     * {@inheritdoc}
     *
     * Eager loading optimization:
     * Groups by type and loads in minimal queries.
     *
     * Example: 50 Posts and 30 Videos with Tags
     * - Query 1: Load tags for Posts (WHERE type='Post' AND id IN (...))
     * - Query 2: Load tags for Videos (WHERE type='Video' AND id IN (...))
     * Total: 2 queries instead of 80!
     */
    public function addEagerConstraints(array $models): void
    {
        $relatedTable = call_user_func([$this->relatedClass, 'getTableName']);

        // Group models by type
        $types = [];
        foreach ($models as $model) {
            $class = get_class($model);
            $parts = explode('\\', $class);
            $type = end($parts);

            if (!isset($types[$type])) {
                $types[$type] = [];
            }
            $types[$type][] = $model->getAttribute($this->localKey);
        }

        // Clear existing where (from performJoin)
        $this->query = $this->query->newQuery()->table($relatedTable);

        // Re-add join
        $this->query->join(
            $this->pivotTable,
            "{$relatedTable}.{$this->relatedKey}",
            '=',
            "{$this->pivotTable}.{$this->relatedPivotKey}"
        );

        // Add constraints for all types using closures (Laravel-style)
        // WHERE (type='Post' AND id IN (?,...)) OR (type='Video' AND id IN (?,...))
        $pivotTable = $this->pivotTable;
        $morphType = $this->morphType;
        $foreignKey = $this->foreignKey;

        $this->query->where(function($q) use ($types, $pivotTable, $morphType, $foreignKey) {
            $first = true;
            foreach ($types as $type => $ids) {
                if ($first) {
                    $q->where(function($subQ) use ($type, $ids, $pivotTable, $morphType, $foreignKey) {
                        $subQ->where("{$pivotTable}.{$morphType}", $type)
                             ->whereIn("{$pivotTable}.{$foreignKey}", $ids);
                    });
                    $first = false;
                } else {
                    $q->orWhere(function($subQ) use ($type, $ids, $pivotTable, $morphType, $foreignKey) {
                        $subQ->where("{$pivotTable}.{$morphType}", $type)
                             ->whereIn("{$pivotTable}.{$foreignKey}", $ids);
                    });
                }
            }
        });

        // Select with pivot columns for matching
        $this->query->select(
            "{$relatedTable}.*",
            "{$this->pivotTable}.{$this->morphType}",
            "{$this->pivotTable}.{$this->foreignKey}"
        );
    }

    /**
     * {@inheritdoc}
     */
    public function match(array $models, mixed $results, string $relationName): array
    {
        if (!$results instanceof ModelCollection) {
            return $models;
        }

        // Build dictionary: type:id => [related_models]
        $dictionary = [];
        foreach ($results as $result) {
            $type = $result->getAttribute($this->morphType);
            $id = $result->getAttribute($this->foreignKey);
            $key = "{$type}:{$id}";

            if (!isset($dictionary[$key])) {
                $dictionary[$key] = [];
            }
            $dictionary[$key][] = $result;
        }

        // Match to parents
        foreach ($models as $model) {
            $class = get_class($model);
            $parts = explode('\\', $class);
            $type = end($parts);
            $id = $model->getAttribute($this->localKey);
            $key = "{$type}:{$id}";

            $related = $dictionary[$key] ?? [];
            $model->setRelation($relationName, new ModelCollection($related));
        }

        return $models;
    }

    /**
     * {@inheritdoc}
     */
    public function getForeignKeyName(): string
    {
        return $this->foreignKey;
    }

    /**
     * Attach models to the relationship.
     *
     * @param mixed $ids Model IDs or models to attach
     * @return void
     */
    public function attach(mixed $ids): void
    {
        $ids = is_array($ids) ? $ids : [$ids];

        foreach ($ids as $id) {
            $connection = $this->parent::getConnection();
            $query = new \Toporia\Framework\Database\Query\QueryBuilder($connection);

            $query->table($this->pivotTable)->insert([
                $this->relatedPivotKey => $id,
                $this->foreignKey => $this->parent->getAttribute($this->localKey),
                $this->morphType => $this->getMorphClass()
            ]);
        }
    }

    /**
     * Detach models from the relationship.
     *
     * @param mixed $ids Model IDs to detach (null = detach all)
     * @return void
     */
    public function detach(mixed $ids = null): void
    {
        $connection = $this->parent::getConnection();
        $query = new \Toporia\Framework\Database\Query\QueryBuilder($connection);

        $query->table($this->pivotTable)
            ->where($this->foreignKey, $this->parent->getAttribute($this->localKey))
            ->where($this->morphType, $this->getMorphClass());

        if ($ids !== null) {
            $ids = is_array($ids) ? $ids : [$ids];
            $query->whereIn($this->relatedPivotKey, $ids);
        }

        $query->delete();
    }

    /**
     * Store pivot table name
     */
    protected string $pivotTable;

    /**
     * Store morph type column
     */
    protected string $morphType;

    /**
     * Store related pivot key
     */
    protected string $relatedPivotKey;

    /**
     * Store related key
     */
    protected string $relatedKey;
}
