<?php

declare(strict_types=1);

namespace Framework\Database\Migration;

use Framework\Database\Schema\SchemaBuilder;

/**
 * Base Migration class.
 *
 * Extend this class to create database migrations.
 */
abstract class Migration
{
    /**
     * @var SchemaBuilder Schema builder instance.
     */
    protected SchemaBuilder $schema;

    /**
     * Set the schema builder.
     *
     * @param SchemaBuilder $schema
     * @return void
     */
    public function setSchema(SchemaBuilder $schema): void
    {
        $this->schema = $schema;
    }

    /**
     * Run the migration (create tables/columns).
     *
     * @return void
     */
    abstract public function up(): void;

    /**
     * Reverse the migration (drop tables/columns).
     *
     * @return void
     */
    abstract public function down(): void;
}
