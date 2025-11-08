<?php

declare(strict_types=1);

use Toporia\Framework\Database\Migration\Migration;

/**
 * Create sessions table for database session driver.
 */
class CreateSessionsTable extends Migration
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
        $this->schema->create('sessions', function ($table) {
            $table->string('id', 255);
            $table->integer('user_id')->unsigned()->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->text('payload');
            $table->integer('last_activity')->unsigned();

            // Primary key
            $table->primaryKey('id');

            // Indexes
            $table->index('user_id');
            $table->index('last_activity');
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        $this->schema->dropIfExists('sessions');
    }
}
