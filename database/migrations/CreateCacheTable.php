<?php

declare(strict_types=1);

use Toporia\Framework\Database\Migration\Migration;

/**
 * Create cache table for database cache driver.
 */
class CreateCacheTable extends Migration
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
        $this->schema->create('cache', function ($table) {
            $table->string('key', 255);
            $table->text('value');
            $table->integer('expiration')->unsigned();

            // Primary key
            $table->primaryKey('key');

            // Indexes
            $table->index('expiration');
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        $this->schema->dropIfExists('cache');
    }
}
