<?php

declare(strict_types=1);

namespace Toporia\Framework\Database\ORM\Relations;

use Toporia\Framework\Database\Query\QueryBuilder;
use Toporia\Framework\Database\ORM\Model;

/**
 * Abstract Relation Base Class
 *
 * Provides common functionality for all relationship types.
 * Follows DRY principle and Template Method pattern.
 */
abstract class Relation implements RelationInterface
{
    /**
     * @param QueryBuilder $query Query builder for related model
     * @param Model $parent Parent model instance
     * @param string $foreignKey Foreign key column name
     * @param string $localKey Local key column name
     */
    public function __construct(
        protected QueryBuilder $query,
        protected Model $parent,
        protected string $foreignKey,
        protected string $localKey
    ) {}

    /**
     * {@inheritdoc}
     */
    public function getQuery(): QueryBuilder
    {
        return $this->query;
    }

    /**
     * {@inheritdoc}
     */
    public function getForeignKey(): string
    {
        return $this->foreignKey;
    }

    /**
     * {@inheritdoc}
     */
    public function getLocalKey(): string
    {
        return $this->localKey;
    }

    /**
     * Add basic WHERE constraint based on parent model.
     *
     * @return $this
     */
    public function addConstraints(): static
    {
        if ($this->parent->exists()) {
            $this->query->where(
                $this->foreignKey,
                $this->parent->getAttribute($this->localKey)
            );
        }

        return $this;
    }

    /**
     * Set WHERE IN constraint for eager loading.
     *
     * @param array<int, Model> $models
     * @return void
     */
    public function addEagerConstraints(array $models): void
    {
        $keys = [];
        foreach ($models as $model) {
            $key = $model->getAttribute($this->localKey);
            if ($key !== null) {
                $keys[] = $key;
            }
        }

        if (!empty($keys)) {
            $this->query->whereIn($this->foreignKey, array_unique($keys));
        }
    }

    /**
     * Execute the relationship query.
     *
     * Subclasses must implement getResults() for specific return types.
     */
    abstract public function getResults(): mixed;

    /**
     * Match eager loaded results to their parent models.
     *
     * Subclasses must implement match() for specific matching logic.
     */
    abstract public function match(array $models, mixed $results, string $relationName): array;
}
