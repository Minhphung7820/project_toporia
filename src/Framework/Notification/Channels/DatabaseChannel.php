<?php

declare(strict_types=1);

namespace Toporia\Framework\Notification\Channels;

use Toporia\Framework\Database\Connection;
use Toporia\Framework\Notification\Contracts\{ChannelInterface, NotifiableInterface, NotificationInterface};

/**
 * Database Notification Channel
 *
 * Stores notifications in database for in-app notifications.
 * Supports read/unread tracking and notification center.
 *
 * Database Schema:
 * - id: string (notification ID)
 * - type: string (notification class name)
 * - notifiable_type: string (User, Admin, etc.)
 * - notifiable_id: string|int (user ID)
 * - data: json (notification data)
 * - read_at: timestamp (null = unread)
 * - created_at: timestamp
 *
 * Performance:
 * - O(1) insert per notification
 * - Indexed on (notifiable_id, read_at) for fast queries
 * - Bulk cleanup of old notifications
 *
 * Usage:
 * ```php
 * // Get unread notifications
 * $notifications = $connection->table('notifications')
 *     ->where('notifiable_id', $userId)
 *     ->whereNull('read_at')
 *     ->orderBy('created_at', 'DESC')
 *     ->get();
 *
 * // Mark as read
 * $connection->table('notifications')
 *     ->where('id', $notificationId)
 *     ->update(['read_at' => time()]);
 * ```
 *
 * @package Toporia\Framework\Notification\Channels
 */
final class DatabaseChannel implements ChannelInterface
{
    private string $table;

    public function __construct(
        private readonly Connection $connection,
        array $config = []
    ) {
        $this->table = $config['table'] ?? 'notifications';
    }

    /**
     * {@inheritdoc}
     */
    public function send(NotifiableInterface $notifiable, NotificationInterface $notification): void
    {
        // Get notifiable identifier
        $notifiableId = $notifiable->routeNotificationFor('database');

        if (!$notifiableId) {
            return; // No identifier configured
        }

        // Build notification data
        $data = $notification->toChannel($notifiable, 'database');

        if (!is_array($data)) {
            throw new \InvalidArgumentException(
                'Database notification must return array from toDatabase() method'
            );
        }

        // Store in database
        $this->connection->table($this->table)->insert([
            'id' => $notification->getId(),
            'type' => get_class($notification),
            'notifiable_type' => get_class($notifiable),
            'notifiable_id' => (string) $notifiableId,
            'data' => json_encode($data),
            'read_at' => null,
            'created_at' => time()
        ]);
    }

    /**
     * Mark notification as read.
     *
     * @param string $notificationId
     * @return void
     */
    public function markAsRead(string $notificationId): void
    {
        $this->connection->table($this->table)
            ->where('id', $notificationId)
            ->update(['read_at' => time()]);
    }

    /**
     * Mark all notifications as read for a notifiable.
     *
     * @param string|int $notifiableId
     * @param string|null $notifiableType
     * @return void
     */
    public function markAllAsRead(string|int $notifiableId, ?string $notifiableType = null): void
    {
        $query = $this->connection->table($this->table)
            ->where('notifiable_id', (string) $notifiableId)
            ->whereNull('read_at');

        if ($notifiableType) {
            $query->where('notifiable_type', $notifiableType);
        }

        $query->update(['read_at' => time()]);
    }

    /**
     * Delete old notifications.
     *
     * @param int $days Delete notifications older than N days
     * @return int Number of deleted notifications
     */
    public function deleteOld(int $days = 30): int
    {
        $timestamp = time() - ($days * 86400);

        return $this->connection->table($this->table)
            ->where('created_at', '<', $timestamp)
            ->delete();
    }
}
