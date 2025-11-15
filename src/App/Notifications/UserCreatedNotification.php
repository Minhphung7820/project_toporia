<?php

declare(strict_types=1);

namespace App\Notifications;

use Toporia\Framework\Notification\Notification;
use Toporia\Framework\Notification\Contracts\NotifiableInterface;
use Toporia\Framework\Notification\Messages\MailMessage;

/**
 * User Created Notification
 *
 * Sent when a new user is created in the system.
 * Notifies admin email about new user registration.
 *
 * Usage:
 * ```php
 * $user->notify(new UserCreatedNotification());
 * ```
 *
 * @package App\Notifications
 */
final class UserCreatedNotification extends Notification
{
    /**
     * @param string $userName Name of the created user
     * @param string $userEmail Email of the created user
     * @param string $recipientEmail Email address to send notification TO (recipient)
     */
    public function __construct(
        private readonly string $userName,
        private readonly string $userEmail,
        private readonly string $recipientEmail = 'minhphung485@gmail.com'
    ) {
        parent::__construct();
    }

    /**
     * Override notification routing to send to specific recipient email.
     *
     * This controls WHERE the email is sent TO (recipient),
     * not WHERE it's sent FROM (sender - configured in .env).
     *
     * @param NotifiableInterface $notifiable
     * @param string $channel
     * @return mixed
     */
    public function routeNotificationFor(NotifiableInterface $notifiable, string $channel): mixed
    {
        // Send TO recipient email (admin) instead of user's email
        if ($channel === 'mail') {
            return $this->recipientEmail;
        }

        // For other channels, use default routing
        return $notifiable->routeNotificationFor($channel);
    }

    /**
     * Get notification channels.
     *
     * @param NotifiableInterface $notifiable
     * @return array
     */
    public function via(NotifiableInterface $notifiable): array
    {
        return ['mail'];
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
            ->subject('New User Created - ' . $this->userName)
            ->greeting('New User Registration!')
            ->line('A new user has been created in the system.')
            ->line('**User Details:**')
            ->line('Name: ' . $this->userName)
            ->line('Email: ' . $this->userEmail)
            ->line('Created at: ' . date('Y-m-d H:i:s'))
            ->action('View All Users', $this->url('/admin/users'))
            ->salutation('Toporia Framework System');
    }

    /**
     * Helper function to generate URLs.
     *
     * @param string $path
     * @return string
     */
    private function url(string $path): string
    {
        $baseUrl = env('APP_URL', 'http://localhost:8000');
        return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
    }
}
