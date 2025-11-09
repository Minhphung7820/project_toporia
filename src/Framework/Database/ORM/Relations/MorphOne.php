<?php

declare(strict_types=1);

namespace Toporia\Framework\Database\ORM\Relations;

use Toporia\Framework\Database\ORM\Model;
use Toporia\Framework\Database\ORM\ModelCollection;

/**
 * Morph One Relationship
 *
 * Represents a polymorphic one-to-one relationship.
 *
 * Example: Post/Video → Image
 * - posts.id
 * - videos.id
 * - images.imageable_id (polymorphic foreign key)
 * - images.imageable_type (polymorphic type: 'Post' or 'Video')
 *
 * Post::morphOne(Image::class, 'imageable')
 *
 * SQL Generated:
 * SELECT *
 * FROM images
 * WHERE imageable_type = 'Post'
 * AND imageable_id = ?
 *
 * Use Case: Multiple models can have one related model (flexible schema).
 *
 * Performance: O(1) query - optimal!
 *              Eager loading: O(N) queries where N = number of morphable types
 *              (Grouped by type: 1 query per type)
 *
 * SOLID Principles:
 * - Single Responsibility: Only handles polymorphic one-to-one
 * - Open/Closed: New morphable types can be added without modifying this class
 * - Liskov Substitution: Implements RelationInterface
 * - Interface Segregation: Minimal interface
 * - Dependency Inversion: Depends on abstractions
 */
class MorphOne extends Relation
{
    /**
     * @param \Toporia\Framework\Database\Query\QueryBuilder $query Query builder
     * @param Model $parent Parent model instance (Post or Video)
     * @param class-string<Model> $relatedClass Related model class (Image)
     * @param string $morphName Morph name ('imageable')
     * @param string|null $morphType Type column (imageable_type)
     * @param string|null $morphId ID column (imageable_id)
     * @param string|null $localKey Local key on parent (id)
     */
    public function __construct(
        \Toporia\Framework\Database\Query\QueryBuilder $query,
        Model $parent,
        protected string $relatedClass,
        protected string $morphName,
        ?string $morphType = null,
        ?string $morphId = null,
        ?string $localKey = null
    ) {
        $this->morphType = $morphType ?? "{$morphName}_type";
        $this->foreignKey = $morphId ?? "{$morphName}_id";
        $this->localKey = $localKey ?? $parent::getPrimaryKey();

        parent::__construct($query, $parent, $this->foreignKey, $this->localKey);

        $this->addConstraints();
    }

    /**
     * Add constraints for morph relationship.
     *
     * @return void
     */
    protected function addConstraints(): void
    {
        if ($this->parent->exists()) {
            // WHERE imageable_type = 'Post'
            $this->query->where($this->morphType, $this->getMorphClass());

            // AND imageable_id = ?
            $this->query->where(
                $this->foreignKey,
                $this->parent->getAttribute($this->localKey)
            );
        }
    }

    /**
     * Get morph class name for parent.
     *
     * @return string
     */
    protected function getMorphClass(): string
    {
        // Use short class name by default
        // Can be customized via getMorphClass() method on model
        $class = get_class($this->parent);
        $parts = explode('\\', $class);
        return end($parts);
    }

    /**
     * {@inheritdoc}
     *
     * @return Model|null
     */
    public function getResults(): mixed
    {
        $row = $this->query->first();

        if (!$row) {
            return null;
        }

        return call_user_func([$this->relatedClass, 'hydrate'], [$row])->first();
    }

    /**
     * {@inheritdoc}
     *
     * Eager loading with closure-based WHERE for clean, efficient SQL:
     * WHERE (type = 'Post' AND id IN (?,...)) OR (type = 'Video' AND id IN (?,...))
     *
     * Performance: O(N) where N = number of distinct types (typically 2-3)
     */
    public function addEagerConstraints(array $models): void
    {
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

        // Build nested WHERE with closures (Laravel-style)
        // WHERE (type='Post' AND id IN (...)) OR (type='Video' AND id IN (...))
        $this->query->where(function($q) use ($types) {
            $first = true;
            foreach ($types as $type => $ids) {
                if ($first) {
                    $q->where(function($subQ) use ($type, $ids) {
                        $subQ->where($this->morphType, $type)
                             ->whereIn($this->foreignKey, $ids);
                    });
                    $first = false;
                } else {
                    $q->orWhere(function($subQ) use ($type, $ids) {
                        $subQ->where($this->morphType, $type)
                             ->whereIn($this->foreignKey, $ids);
                    });
                }
            }
        });
    }

    /**
     * {@inheritdoc}
     */
    public function match(array $models, mixed $results, string $relationName): array
    {
        if (!$results instanceof ModelCollection) {
            return $models;
        }

        // Build dictionary: type:id => related_model
        $dictionary = [];
        foreach ($results as $result) {
            $type = $result->getAttribute($this->morphType);
            $id = $result->getAttribute($this->foreignKey);
            $key = "{$type}:{$id}";
            $dictionary[$key] = $result;
        }

        // Match to parents
        foreach ($models as $model) {
            $class = get_class($model);
            $parts = explode('\\', $class);
            $type = end($parts);
            $id = $model->getAttribute($this->localKey);
            $key = "{$type}:{$id}";

            $related = $dictionary[$key] ?? null;
            $model->setRelation($relationName, $related);
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
     * Store morphType for access
     */
    protected string $morphType;
}
