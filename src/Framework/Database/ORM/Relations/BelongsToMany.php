<?php

declare(strict_types=1);

namespace Toporia\Framework\Database\ORM\Relations;

use Toporia\Framework\Database\ORM\Model;
use Toporia\Framework\Database\ORM\ModelCollection;
use Toporia\Framework\Database\Query\QueryBuilder;

/**
 * Belongs To Many Relationship (Many-to-Many)
 *
 * Represents a many-to-many relationship using a pivot table.
 * Example: User belongsToMany Roles (via user_roles pivot table)
 */
class BelongsToMany extends Relation
{
    /**
     * @param QueryBuilder $query Query builder for related model
     * @param Model $parent Parent model instance
     * @param class-string<Model> $relatedClass Related model class name
     * @param string $pivotTable Pivot table name
     * @param string $foreignPivotKey Foreign key in pivot table for parent
     * @param string $relatedPivotKey Foreign key in pivot table for related
     * @param string $parentKey Parent's primary key
     * @param string $relatedKey Related model's primary key
     */
    public function __construct(
        QueryBuilder $query,
        Model $parent,
        protected string $relatedClass,
        protected string $pivotTable,
        protected string $foreignPivotKey,
        protected string $relatedPivotKey,
        protected string $parentKey,
        protected string $relatedKey
    ) {
        parent::__construct($query, $parent, $foreignPivotKey, $parentKey);
        $this->addPivotConstraints();
    }

    /**
     * Add JOIN and WHERE constraints for the pivot table.
     *
     * @return $this
     */
    protected function addPivotConstraints(): static
    {
        if ($this->parent->exists()) {
            $relatedTable = call_user_func([$this->relatedClass, 'getTableName']);

            $this->query
                ->join(
                    $this->pivotTable,
                    "{$relatedTable}.{$this->relatedKey}",
                    '=',
                    "{$this->pivotTable}.{$this->relatedPivotKey}"
                )
                ->where(
                    "{$this->pivotTable}.{$this->foreignPivotKey}",
                    $this->parent->getAttribute($this->parentKey)
                )
                ->select(["{$relatedTable}.*"]);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @return ModelCollection
     */
    public function getResults(): ModelCollection
    {
        $rows = $this->query->get();

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
        $keys = [];
        foreach ($models as $model) {
            $key = $model->getAttribute($this->parentKey);
            if ($key !== null) {
                $keys[] = $key;
            }
        }

        if (!empty($keys)) {
            $this->query->whereIn(
                "{$this->pivotTable}.{$this->foreignPivotKey}",
                array_unique($keys)
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function match(array $models, mixed $results, string $relationName): array
    {
        if (!$results instanceof ModelCollection) {
            return $models;
        }

        // We need to re-query with pivot data to build the dictionary
        // This is a simplified version - a full implementation would include pivot data
        $dictionary = [];

        // For now, return empty collections for each parent
        foreach ($models as $model) {
            $model->setRelation($relationName, new ModelCollection([]));
        }

        return $models;
    }

    /**
     * Attach a related model to the parent via pivot table.
     *
     * @param int|string $id Related model ID
     * @param array<string, mixed> $pivotData Additional pivot data
     * @return bool
     */
    public function attach(int|string $id, array $pivotData = []): bool
    {
        $data = array_merge([
            $this->foreignPivotKey => $this->parent->getAttribute($this->parentKey),
            $this->relatedPivotKey => $id,
        ], $pivotData);

        $qb = new QueryBuilder($this->query->getConnection());
        $qb->table($this->pivotTable)->insert($data);

        return true;
    }

    /**
     * Detach a related model from the parent via pivot table.
     *
     * @param int|string|null $id Related model ID (null = detach all)
     * @return int Number of rows deleted
     */
    public function detach(int|string|null $id = null): int
    {
        $qb = new QueryBuilder($this->query->getConnection());
        $qb->table($this->pivotTable)
            ->where($this->foreignPivotKey, $this->parent->getAttribute($this->parentKey));

        if ($id !== null) {
            $qb->where($this->relatedPivotKey, $id);
        }

        return $qb->delete();
    }

    /**
     * Sync the pivot table with the given IDs.
     *
     * @param array<int|string> $ids Related model IDs
     * @return void
     */
    public function sync(array $ids): void
    {
        // Detach all current relations
        $this->detach();

        // Attach new relations
        foreach ($ids as $id) {
            $this->attach($id);
        }
    }
}
