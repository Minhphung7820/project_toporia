<?php

declare(strict_types=1);

namespace Toporia\Framework\Database\ORM\Relations;

use Toporia\Framework\Database\ORM\Model;
use Toporia\Framework\Database\ORM\ModelCollection;

/**
 * Has One Through Relationship
 *
 * Represents a one-to-one relationship through an intermediate model.
 *
 * Example: Country → User → Phone
 * - countries.id
 * - users.country_id (foreign key to countries)
 * - phones.user_id (foreign key to users)
 *
 * Country::hasOneThrough(Phone::class, User::class)
 *
 * SQL Generated:
 * SELECT phones.*
 * FROM phones
 * INNER JOIN users ON users.id = phones.user_id
 * WHERE users.country_id = ?
 *
 * Performance: O(1) query with JOIN - optimal!
 *
 * SOLID Principles:
 * - Single Responsibility: Only handles one-to-one through relationships
 * - Open/Closed: Extensible via callbacks
 * - Liskov Substitution: Implements RelationInterface
 * - Interface Segregation: RelationInterface is minimal
 * - Dependency Inversion: Depends on abstractions
 */
class HasOneThrough extends Relation
{
    /**
     * @param \Toporia\Framework\Database\Query\QueryBuilder $query Query builder for related model
     * @param Model $parent Parent model instance
     * @param class-string<Model> $relatedClass Related model class (Phone)
     * @param class-string<Model> $throughClass Through model class (User)
     * @param string $firstKey Foreign key on through table (users.country_id)
     * @param string $secondKey Foreign key on related table (phones.user_id)
     * @param string $localKey Local key on parent table (countries.id)
     * @param string $secondLocalKey Local key on through table (users.id)
     */
    public function __construct(
        \Toporia\Framework\Database\Query\QueryBuilder $query,
        Model $parent,
        protected string $relatedClass,
        protected string $throughClass,
        protected string $firstKey,  // users.country_id
        string $secondKey,           // phones.user_id
        string $localKey,            // countries.id
        protected string $secondLocalKey
    ) {
        // Store keys
        $this->foreignKey = $secondKey; // phones.user_id
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
        // INNER JOIN users ON users.id = phones.user_id
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
     * @return Model|null
     */
    public function getResults(): mixed
    {
        $relatedTable = call_user_func([$this->relatedClass, 'getTableName']);

        // Select only related table columns to avoid column ambiguity
        $this->query->select("{$relatedTable}.*");

        $row = $this->query->first();

        if (!$row) {
            return null;
        }

        return call_user_func([$this->relatedClass, 'hydrate'], [$row])->first();
    }

    /**
     * {@inheritdoc}
     */
    public function addEagerConstraints(array $models): void
    {
        $throughTable = call_user_func([$this->throughClass, 'getTableName']);

        // Get parent IDs
        $keys = array_map(fn($m) => $m->getAttribute($this->localKey), $models);

        // Clear existing where (from performJoin)
        $this->query = $this->query->newQuery()->table(
            call_user_func([$this->relatedClass, 'getTableName'])
        );

        // Re-add join
        $this->performJoin();

        // WHERE users.country_id IN (1, 2, 3, ...)
        $this->query->whereIn("{$throughTable}.{$this->firstKey}", $keys);
    }

    /**
     * {@inheritdoc}
     */
    public function match(array $models, mixed $results, string $relationName): array
    {
        if (!$results instanceof ModelCollection) {
            return $models;
        }

        $throughTable = call_user_func([$this->throughClass, 'getTableName']);

        // Build dictionary: parent_id => related_model
        $dictionary = [];
        foreach ($results as $result) {
            // Get the through key from result
            $key = $result->getAttribute($this->firstKey);
            $dictionary[$key] = $result;
        }

        // Match to parents
        foreach ($models as $model) {
            $localValue = $model->getAttribute($this->localKey);
            $related = $dictionary[$localValue] ?? null;
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
}
