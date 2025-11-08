<?php

declare(strict_types=1);

namespace Toporia\Framework\Database\ORM\Relations;

use Toporia\Framework\Database\Query\QueryBuilder;

/**
 * Relationship Interface
 *
 * Defines contract for all relationship types.
 * Follows Interface Segregation Principle (SOLID).
 */
interface RelationInterface
{
    /**
     * Get the query builder for this relationship.
     *
     * @return QueryBuilder
     */
    public function getQuery(): QueryBuilder;

    /**
     * Execute the relationship query and get results.
     *
     * @return mixed Single model, collection, or null
     */
    public function getResults(): mixed;

    /**
     * Add eager loading constraints to the query.
     *
     * @param array<int, \Toporia\Framework\Database\ORM\Model> $models Parent models
     * @return void
     */
    public function addEagerConstraints(array $models): void;

    /**
     * Match eager loaded results to their parent models.
     *
     * @param array<int, \Toporia\Framework\Database\ORM\Model> $models Parent models
     * @param mixed $results Eager loaded results
     * @param string $relationName Name of the relationship
     * @return array<int, \Toporia\Framework\Database\ORM\Model>
     */
    public function match(array $models, mixed $results, string $relationName): array;

    /**
     * Get the foreign key for the relationship.
     *
     * @return string
     */
    public function getForeignKey(): string;

    /**
     * Get the local key for the relationship.
     *
     * @return string
     */
    public function getLocalKey(): string;
}
