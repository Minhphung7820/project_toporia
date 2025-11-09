<?php

declare(strict_types=1);

namespace Toporia\Framework\Database\ORM\Relations;

use Toporia\Framework\Database\ORM\Model;
use Toporia\Framework\Database\ORM\ModelCollection;

/**
 * Belongs To Relationship
 *
 * Represents the inverse of a one-to-one or one-to-many relationship.
 * Example: Post belongsTo User
 */
class BelongsTo extends Relation
{
    /**
     * @param class-string<Model> $relatedClass Related model class name
     */
    public function __construct(
        \Toporia\Framework\Database\Query\QueryBuilder $query,
        Model $parent,
        protected string $relatedClass,
        string $foreignKey,
        string $ownerKey
    ) {
        parent::__construct($query, $parent, $foreignKey, $ownerKey);
        $this->addConstraints();
    }

    /**
     * Add constraints for belongs to relationship.
     *
     * @return $this
     */
    public function addConstraints(): static
    {
        if ($this->parent->exists()) {
            $foreignKeyValue = $this->parent->getAttribute($this->foreignKey);

            if ($foreignKeyValue !== null) {
                // For BelongsTo, we query owner table WHERE owner_key = parent's foreign_key
                $this->query->where($this->localKey, $foreignKeyValue);
            }
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @return Model|null
     */
    public function getResults(): ?Model
    {
        $data = $this->query->first();

        if ($data === null) {
            return null;
        }

        /** @var Model $model */
        $model = new $this->relatedClass($data);
        $model->setAttribute('exists', true);

        return $model;
    }

    /**
     * {@inheritdoc}
     */
    public function addEagerConstraints(array $models): void
    {
        $keys = [];
        foreach ($models as $model) {
            $key = $model->getAttribute($this->foreignKey);
            if ($key !== null) {
                $keys[] = $key;
            }
        }

        if (!empty($keys)) {
            // Query owner table WHERE owner_key IN (foreign_key_values)
            $this->query->whereIn($this->localKey, array_unique($keys));
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

        // Build dictionary: owner_key => model
        $dictionary = [];
        foreach ($results as $result) {
            $key = $result->getAttribute($this->localKey);
            $dictionary[$key] = $result;
        }

        // Match to children
        foreach ($models as $model) {
            $foreignValue = $model->getAttribute($this->foreignKey);
            $model->setRelation($relationName, $dictionary[$foreignValue] ?? null);
        }

        return $models;
    }

    /**
     * {@inheritdoc}
     *
     * For BelongsTo, we need to ensure the owner key (localKey) is selected
     * on the related model, not the foreign key (which is on the parent).
     */
    public function getForeignKeyName(): string
    {
        return $this->localKey;
    }
}
