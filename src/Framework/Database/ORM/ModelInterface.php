<?php

declare(strict_types=1);

namespace Toporia\Framework\Database\ORM;

/**
 * ORM Model interface.
 *
 * Defines the contract for Active Record pattern models.
 */
interface ModelInterface
{
    /**
     * Save the model to the database (insert or update).
     *
     * @return bool
     */
    public function save(): bool;

    /**
     * Delete the model from the database.
     *
     * @return bool
     */
    public function delete(): bool;

    /**
     * Refresh the model from the database.
     *
     * @return self
     */
    public function refresh(): self;

    /**
     * Get the model's attributes as an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;

    /**
     * Get the model's attributes as JSON.
     *
     * @return string
     */
    public function toJson(): string;

    /**
     * Check if the model exists in the database.
     *
     * @return bool
     */
    public function exists(): bool;

    /**
     * Get the table name for the model.
     *
     * @return string
     */
    public static function getTableName(): string;

    /**
     * Get the primary key name.
     *
     * @return string
     */
    public static function getPrimaryKey(): string;
}
