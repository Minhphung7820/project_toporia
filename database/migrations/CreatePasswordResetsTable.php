<?php

declare(strict_types=1);

use Toporia\Framework\Database\Migration\Migration;

/**
 * Create password_resets table for password reset tokens.
 */
class CreatePasswordResetsTable extends Migration
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
        $this->schema->create('password_resets', function ($table) {
            $table->string('email');
            $table->string('token');
            $table->timestamp('created_at')->nullable();

            // Indexes
            $table->index('email');
            $table->index('token');
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        $this->schema->dropIfExists('password_resets');
    }
}
