<?php

declare(strict_types=1);

namespace Toporia\Framework\Database\ORM\Relations;

use Toporia\Framework\Database\ORM\Model;
use Toporia\Framework\Database\ORM\ModelCollection;

/**
 * Morph Many Relationship
 *
 * Represents a polymorphic one-to-many relationship.
 *
 * Example: Post/Video → Comments
 * - posts.id
 * - videos.id
 * - comments.commentable_id (polymorphic foreign key)
 * - comments.commentable_type (polymorphic type: 'Post' or 'Video')
 *
 * Post::morphMany(Comment::class, 'commentable')
 *
 * SQL Generated:
 * SELECT *
 * FROM comments
 * WHERE commentable_type = 'Post'
 * AND commentable_id = ?
 *
 * Use Case: Multiple models can have many related models (flexible schema).
 * Real examples:
 * - Posts, Videos, Articles → Comments
 * - Users, Teams, Projects → Activities
 * - Products, Orders, Invoices → Attachments
 *
 * Performance: O(1) query for single parent - optimal!
 *              Eager loading: O(N) where N = distinct polymorphic types
 *              (Grouped by type for efficiency)
 *
 * SOLID Principles:
 * - Single Responsibility: Only handles polymorphic one-to-many
 * - Open/Closed: Extensible to new morphable types without modification
 * - Liskov Substitution: Implements RelationInterface
 * - Interface Segregation: Minimal interface contract
 * - Dependency Inversion: Depends on QueryBuilder abstraction
 */
class MorphMany extends Relation
{
    /**
     * @param \Toporia\Framework\Database\Query\QueryBuilder $query Query builder
     * @param Model $parent Parent model instance (Post or Video)
     * @param class-string<Model> $relatedClass Related model class (Comment)
     * @param string $morphName Morph name ('commentable')
     * @param string|null $morphType Type column (commentable_type)
     * @param string|null $morphId ID column (commentable_id)
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
            // WHERE commentable_type = 'Post'
            $this->query->where($this->morphType, $this->getMorphClass());

            // AND commentable_id = ?
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
        // Example: App\Domain\Post\Post -> Post
        $class = get_class($this->parent);
        $parts = explode('\\', $class);
        return end($parts);
    }

    /**
     * {@inheritdoc}
     *
     * @return ModelCollection
     */
    public function getResults(): ModelCollection
    {
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
     * Eager loading optimization with closures:
     * Groups models by type and loads in minimal queries.
     *
     * Example: Loading comments for 50 Posts and 30 Videos
     * Single query with nested WHERE:
     * WHERE (type='Post' AND id IN (?,?,...)) OR (type='Video' AND id IN (?,?,...))
     *
     * Performance: 1 query instead of 80! O(N) where N = distinct types
     */
    public function addEagerConstraints(array $models): void
    {
        // Group models by type for efficient loading
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

        // Build nested WHERE with closures (clean Laravel-style)
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
     * Store morphType for access
     */
    protected string $morphType;
}
