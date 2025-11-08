<?php

declare(strict_types=1);

namespace Toporia\Framework\Database\ORM\Relations;

use Toporia\Framework\Database\ORM\Model;
use Toporia\Framework\Database\ORM\ModelCollection;

/**
 * Has One Relationship
 *
 * Represents a one-to-one relationship where the parent has one related model.
 * Example: User hasOne Profile
 */
class HasOne extends Relation
{
    /**
     * @param class-string<Model> $relatedClass Related model class name
     */
    public function __construct(
        \Toporia\Framework\Database\Query\QueryBuilder $query,
        Model $parent,
        protected string $relatedClass,
        string $foreignKey,
        string $localKey
    ) {
        parent::__construct($query, $parent, $foreignKey, $localKey);
        $this->addConstraints();
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
    public function match(array $models, mixed $results, string $relationName): array
    {
        if (!$results instanceof ModelCollection) {
            return $models;
        }

        // Build dictionary: foreign_key => model
        $dictionary = [];
        foreach ($results as $result) {
            $key = $result->getAttribute($this->foreignKey);
            $dictionary[$key] = $result;
        }

        // Match to parents
        foreach ($models as $model) {
            $localValue = $model->getAttribute($this->localKey);
            $model->setRelation($relationName, $dictionary[$localValue] ?? null);
        }

        return $models;
    }
}
