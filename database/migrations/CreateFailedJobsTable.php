<?php

declare(strict_types=1);

use Toporia\Framework\Database\Migration\Migration;

/**
 * Create failed_jobs table for failed queue jobs.
 */
class CreateFailedJobsTable extends Migration
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
        $this->schema->create('failed_jobs', function ($table) {
            $table->string('id', 255);
            $table->string('queue');
            $table->text('payload');
            $table->text('exception');
            $table->integer('failed_at')->unsigned();

            // Primary key
            $table->primaryKey('id');

            // Indexes
            $table->index('queue');
            $table->index('failed_at');
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        $this->schema->dropIfExists('failed_jobs');
    }
}
