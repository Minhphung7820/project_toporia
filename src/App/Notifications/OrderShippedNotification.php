<?php

declare(strict_types=1);

namespace App\Notifications;

use Toporia\Framework\Notification\Notification;
use Toporia\Framework\Notification\Contracts\NotifiableInterface;
use Toporia\Framework\Notification\Messages\{MailMessage, SmsMessage};

/**
 * Order Shipped Notification
 *
 * Sent when an order is shipped.
 * Multi-channel: Email + SMS + Database
 *
 * Usage:
 * ```php
 * $user->notify(new OrderShippedNotification($order));
 *
 * // Queue for async delivery
 * $notification = new OrderShippedNotification($order);
 * $notification->onQueue('notifications');
 * $user->notify($notification);
 * ```
 *
 * @package App\Notifications
 */
final class OrderShippedNotification extends Notification
{
    public function __construct(
        private readonly array $order
    ) {
        parent::__construct();
    }

    /**
     * Get notification channels.
     *
     * @param NotifiableInterface $notifiable
     * @return array
     */
    public function via(NotifiableInterface $notifiable): array
    {
        // Send via email, SMS, and database
        return ['mail', 'sms', 'database'];
    }

    /**
     * Build email notification.
     *
     * @param NotifiableInterface $notifiable
     * @return MailMessage
     */
    public function toMail(NotifiableInterface $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your Order Has Been Shipped!')
            ->greeting('Good news!')
            ->line("Your order #{$this->order['id']} has been shipped.")
            ->line("Tracking Number: {$this->order['tracking_number']}")
            ->action('Track Shipment', $this->order['tracking_url'])
            ->line('Thank you for your purchase!')
            ->success();
    }

    /**
     * Build SMS notification.
     *
     * @param NotifiableInterface $notifiable
     * @return SmsMessage
     */
    public function toSms(NotifiableInterface $notifiable): SmsMessage
    {
        return (new SmsMessage)
            ->content(
                "Your order #{$this->order['id']} has shipped! " .
                "Track it here: {$this->order['tracking_url']}"
            )
            ->from('YourStore');
    }

    /**
     * Build database notification data.
     *
     * @param NotifiableInterface $notifiable
     * @return array
     */
    public function toDatabase(NotifiableInterface $notifiable): array
    {
        return [
            'title' => 'Order Shipped',
            'message' => "Your order #{$this->order['id']} has been shipped!",
            'icon' => '📦',
            'order_id' => $this->order['id'],
            'tracking_number' => $this->order['tracking_number'],
            'tracking_url' => $this->order['tracking_url'],
            'action_url' => $this->order['tracking_url'],
            'action_text' => 'Track Shipment'
        ];
    }
}
