<?php

declare(strict_types=1);

use Toporia\Framework\Database\Migration\Migration;

/**
 * Create Notifications Table
 *
 * Stores in-app notifications for users.
 * Supports read/unread tracking and polymorphic notifiables.
 *
 * Table Structure:
 * - id: string (UUID from notification)
 * - type: string (notification class name)
 * - notifiable_type: string (User, Admin, etc.)
 * - notifiable_id: string (user ID)
 * - data: json (notification data)
 * - read_at: timestamp (null = unread)
 * - created_at: timestamp
 *
 * Performance Indexes:
 * - (notifiable_id, read_at) - Fast unread queries
 * - (created_at) - Fast cleanup of old notifications
 *
 * Usage:
 * ```php
 * // Get unread notifications
 * $notifications = DB::table('notifications')
 *     ->where('notifiable_id', $userId)
 *     ->whereNull('read_at')
 *     ->orderBy('created_at', 'DESC')
 *     ->get();
 *
 * // Mark as read
 * DB::table('notifications')
 *     ->where('id', $notificationId)
 *     ->update(['read_at' => time()]);
 * ```
 */
final class CreateNotificationsTable extends Migration
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
        $this->schema->create('notifications', function ($table) {
            // Primary key
            $table->string('id', 255); // notification_xxxxx format
            $table->primary('id');

            // Notification metadata
            $table->string('type'); // Full class name
            $table->string('notifiable_type'); // User, Admin, etc.
            $table->string('notifiable_id'); // User ID

            // Notification data (JSON)
            $table->text('data'); // JSON encoded data

            // Timestamps
            $table->integer('read_at')->nullable(); // Unix timestamp
            $table->integer('created_at'); // Unix timestamp

            // Performance indexes
            // Fast query: Get unread notifications for user
            $table->index(['notifiable_id', 'read_at']);

            // Fast cleanup: Delete old notifications
            $table->index(['created_at']);

            // Polymorphic index (for complex queries)
            $table->index(['notifiable_type', 'notifiable_id']);
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        $this->schema->dropIfExists('notifications');
    }
}
