<?php

declare(strict_types=1);

namespace Toporia\Framework\Notification\Events;

use Toporia\Framework\Events\Event;
use Toporia\Framework\Notification\Contracts\{NotifiableInterface, NotificationInterface};

/**
 * Notification Failed Event
 *
 * Dispatched when a notification fails to send via a specific channel.
 * Useful for monitoring, logging, and retry logic.
 *
 * Use Cases:
 * - Log failures to monitoring system (Sentry, Bugsnag)
 * - Implement retry logic with backoff
 * - Send alerts to administrators
 * - Track delivery metrics
 *
 * Usage:
 * ```php
 * event()->listen(NotificationFailed::class, function($event) {
 *     logger()->error("Notification failed", [
 *         'notification' => $event->notification->getId(),
 *         'channel' => $event->channel,
 *         'error' => $event->exception->getMessage()
 *     ]);
 * });
 * ```
 *
 * @package Toporia\Framework\Notification\Events
 */
final class NotificationFailed extends Event
{
    /**
     * @param NotifiableInterface $notifiable The entity that should receive the notification
     * @param NotificationInterface $notification The notification that failed
     * @param string $channel The channel that failed (mail, sms, slack, etc.)
     * @param \Throwable $exception The exception that caused the failure
     */
    public function __construct(
        public readonly NotifiableInterface $notifiable,
        public readonly NotificationInterface $notification,
        public readonly string $channel,
        public readonly \Throwable $exception
    ) {
        parent::__construct();
    }

    /**
     * Get event name for dispatching.
     *
     * @return string
     */
    public function getName(): string
    {
        return 'notification.failed';
    }

    /**
     * Convert event to array for logging/serialization.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'notification_id' => $this->notification->getId(),
            'notification_class' => get_class($this->notification),
            'notifiable_class' => get_class($this->notifiable),
            'channel' => $this->channel,
            'error_message' => $this->exception->getMessage(),
            'error_class' => get_class($this->exception),
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
}
