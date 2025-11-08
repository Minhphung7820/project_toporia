<?php

declare(strict_types=1);

use Toporia\Framework\Database\Migration\Migration;

/**
 * Create jobs table for database queue driver.
 */
class CreateJobsTable extends Migration
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
        $this->schema->create('jobs', function ($table) {
            $table->string('id', 255);
            $table->string('queue');
            $table->text('payload');
            $table->integer('attempts')->unsigned()->default(0);
            $table->integer('available_at')->unsigned();
            $table->integer('created_at')->unsigned();

            // Primary key
            $table->primaryKey('id');

            // Indexes
            $table->index(['queue', 'available_at']);
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        $this->schema->dropIfExists('jobs');
    }
}
