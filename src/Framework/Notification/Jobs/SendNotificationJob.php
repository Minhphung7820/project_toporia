<?php

declare(strict_types=1);

namespace Toporia\Framework\Notification\Jobs;

use Toporia\Framework\Queue\Job;
use Toporia\Framework\Notification\Contracts\{NotifiableInterface, NotificationInterface};

/**
 * Send Notification Job
 *
 * Queue job for sending notifications asynchronously.
 * Automatically dispatched when notification has shouldQueue() = true.
 *
 * Performance:
 * - O(C) where C = number of channels
 * - Non-blocking for web requests
 * - Supports retry on failure
 *
 * Usage:
 * ```php
 * // Automatic via NotificationManager
 * $user->notify((new WelcomeNotification)->onQueue('notifications'));
 *
 * // Manual dispatch
 * SendNotificationJob::dispatch($user, $notification);
 * ```
 *
 * @package Toporia\Framework\Notification\Jobs
 */
final class SendNotificationJob extends Job
{
    /**
     * @param array $notifiableData Serialized notifiable data
     * @param string $notifiableClass Notifiable class name
     * @param NotificationInterface $notification Notification instance
     */
    public function __construct(
        private readonly array $notifiableData,
        private readonly string $notifiableClass,
        private readonly NotificationInterface $notification
    ) {
        parent::__construct();
    }

    /**
     * Execute the job.
     *
     * Reconstructs the notifiable and sends notification via NotificationManager.
     *
     * IMPORTANT: Calls sendNow() directly to avoid infinite queue loop.
     * Using send() would check shouldQueue() again and create another job!
     *
     * @return void
     */
    public function handle(): void
    {
        // Reconstruct notifiable from serialized data
        $notifiable = $this->reconstructNotifiable();

        // Send notification immediately (bypass queue check)
        // We're already in the queue worker, so we must send NOW
        app('notification')->sendNow($notifiable, $this->notification);
    }

    /**
     * Handle job failure.
     *
     * @param \Throwable $exception
     * @return void
     */
    public function failed(\Throwable $exception): void
    {
        error_log(sprintf(
            "Failed to send notification %s: %s",
            $this->notification->getId(),
            $exception->getMessage()
        ));

        // TODO: Dispatch NotificationFailed event
    }

    /**
     * Reconstruct notifiable from serialized data.
     *
     * For ORM models, refetch from database to ensure fresh data.
     * For other notifiables, use cached data.
     *
     * @return NotifiableInterface
     * @throws \RuntimeException If notifiable cannot be reconstructed
     */
    private function reconstructNotifiable(): NotifiableInterface
    {
        // Check if notifiable is an ORM Model
        if (is_subclass_of($this->notifiableClass, \Toporia\Framework\Database\ORM\Model::class)) {
            // Refetch from database for fresh data
            $id = $this->notifiableData['id'] ?? null;

            if (!$id) {
                throw new \RuntimeException('Cannot reconstruct model without ID');
            }

            $model = $this->notifiableClass::find($id);

            if (!$model) {
                throw new \RuntimeException("Model {$this->notifiableClass}#{$id} not found");
            }

            return $model;
        }

        // For non-models, reconstruct from cached data
        // This assumes the class has a method to reconstruct from array
        if (method_exists($this->notifiableClass, 'fromArray')) {
            return $this->notifiableClass::fromArray($this->notifiableData);
        }

        throw new \RuntimeException(
            "Cannot reconstruct notifiable {$this->notifiableClass}. " .
            "Implement fromArray() method or ensure it's an ORM Model."
        );
    }

    /**
     * Create job from notifiable instance.
     *
     * Static factory method for easier job creation.
     *
     * @param NotifiableInterface $notifiable
     * @param NotificationInterface $notification
     * @return self
     */
    public static function make(NotifiableInterface $notifiable, NotificationInterface $notification): self
    {
        // Serialize notifiable data
        $data = self::serializeNotifiable($notifiable);

        return new self(
            notifiableData: $data,
            notifiableClass: get_class($notifiable),
            notification: $notification
        );
    }

    /**
     * Serialize notifiable for queue storage.
     *
     * @param NotifiableInterface $notifiable
     * @return array
     */
    private static function serializeNotifiable(NotifiableInterface $notifiable): array
    {
        // For ORM models, just store ID
        if ($notifiable instanceof \Toporia\Framework\Database\ORM\Model) {
            return ['id' => $notifiable->id];
        }

        // For other notifiables, try toArray()
        if (method_exists($notifiable, 'toArray')) {
            return $notifiable->toArray();
        }

        // Fallback: serialize all public properties
        return get_object_vars($notifiable);
    }
}
