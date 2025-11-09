<?php

declare(strict_types=1);

namespace Toporia\Framework\Database\ORM\Relations;

use Toporia\Framework\Database\ORM\Model;
use Toporia\Framework\Database\ORM\ModelCollection;

/**
 * Has Many Through Relationship
 *
 * Represents a one-to-many relationship through an intermediate model.
 *
 * Example: Country → Users → Posts
 * - countries.id
 * - users.country_id (foreign key to countries)
 * - posts.user_id (foreign key to users)
 *
 * Country::hasManyThrough(Post::class, User::class)
 *
 * SQL Generated:
 * SELECT posts.*
 * FROM posts
 * INNER JOIN users ON users.id = posts.user_id
 * WHERE users.country_id = ?
 *
 * Use Case: Get all posts from a country without directly relating countries to posts.
 *
 * Performance: O(1) query with JOIN for single parent
 *              O(1) query with IN clause for eager loading - optimal!
 *
 * SOLID Principles:
 * - Single Responsibility: Only handles one-to-many through relationships
 * - Open/Closed: Extensible via query callbacks
 * - Liskov Substitution: Implements RelationInterface
 * - Interface Segregation: Minimal interface
 * - Dependency Inversion: Depends on QueryBuilder abstraction
 */
class HasManyThrough extends Relation
{
    /**
     * @param \Toporia\Framework\Database\Query\QueryBuilder $query Query builder for related model
     * @param Model $parent Parent model instance
     * @param class-string<Model> $relatedClass Related model class (Post)
     * @param class-string<Model> $throughClass Through model class (User)
     * @param string $firstKey Foreign key on through table (users.country_id)
     * @param string $secondKey Foreign key on related table (posts.user_id)
     * @param string $localKey Local key on parent table (countries.id)
     * @param string $secondLocalKey Local key on through table (users.id)
     */
    public function __construct(
        \Toporia\Framework\Database\Query\QueryBuilder $query,
        Model $parent,
        protected string $relatedClass,
        protected string $throughClass,
        protected string $firstKey,  // users.country_id
        string $secondKey,           // posts.user_id
        string $localKey,            // countries.id
        protected string $secondLocalKey
    ) {
        // Store keys for eager loading
        $this->foreignKey = $secondKey; // posts.user_id
        $this->localKey = $localKey;     // countries.id

        parent::__construct($query, $parent, $firstKey, $localKey);

        // Set up the join
        $this->performJoin();
    }

    /**
     * Perform join with through table.
     *
     * @return void
     */
    protected function performJoin(): void
    {
        $throughTable = call_user_func([$this->throughClass, 'getTableName']);
        $relatedTable = call_user_func([$this->relatedClass, 'getTableName']);

        // Join through table to related table
        // INNER JOIN users ON users.id = posts.user_id
        $this->query->join(
            $throughTable,
            "{$throughTable}.{$this->secondLocalKey}",
            '=',
            "{$relatedTable}.{$this->foreignKey}"
        );

        // Add constraint for parent
        // WHERE users.country_id = ?
        if ($this->parent->exists()) {
            $this->query->where(
                "{$throughTable}.{$this->firstKey}",
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

        // Select only related table columns to avoid column ambiguity
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
     */
    public function addEagerConstraints(array $models): void
    {
        $throughTable = call_user_func([$this->throughClass, 'getTableName']);
        $relatedTable = call_user_func([$this->relatedClass, 'getTableName']);

        // Get parent IDs
        $keys = array_map(fn($m) => $m->getAttribute($this->localKey), $models);

        // Clear existing where (from performJoin)
        $this->query = $this->query->newQuery()->table($relatedTable);

        // Re-add join
        $this->query->join(
            $throughTable,
            "{$throughTable}.{$this->secondLocalKey}",
            '=',
            "{$relatedTable}.{$this->foreignKey}"
        );

        // WHERE users.country_id IN (1, 2, 3, ...)
        $this->query->whereIn("{$throughTable}.{$this->firstKey}", $keys);

        // Select with through key for matching
        $this->query->select("{$relatedTable}.*", "{$throughTable}.{$this->firstKey}");
    }

    /**
     * {@inheritdoc}
     */
    public function match(array $models, mixed $results, string $relationName): array
    {
        if (!$results instanceof ModelCollection) {
            return $models;
        }

        // Build dictionary: parent_id => [related_models]
        $dictionary = [];
        foreach ($results as $result) {
            // Get the through key from result
            $key = $result->getAttribute($this->firstKey);
            if (!isset($dictionary[$key])) {
                $dictionary[$key] = [];
            }
            $dictionary[$key][] = $result;
        }

        // Match to parents
        foreach ($models as $model) {
            $localValue = $model->getAttribute($this->localKey);
            $related = $dictionary[$localValue] ?? [];
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
}
