<?php

declare(strict_types=1);

namespace App\Notifications;

use Toporia\Framework\Notification\Notification;
use Toporia\Framework\Notification\Contracts\NotifiableInterface;
use Toporia\Framework\Notification\Messages\{MailMessage, SlackMessage};

/**
 * Welcome Notification
 *
 * Sent to new users after registration.
 * Multi-channel: Email + Database
 *
 * Usage:
 * ```php
 * $user->notify(new WelcomeNotification());
 * ```
 *
 * @package App\Notifications
 */
final class WelcomeNotification extends Notification
{
    /**
     * Get notification channels.
     *
     * @param NotifiableInterface $notifiable
     * @return array
     */
    public function via(NotifiableInterface $notifiable): array
    {
        return ['mail', 'database'];
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
            ->subject('Welcome to Our Platform!')
            ->greeting('Hello!')
            ->line('Thank you for registering with us.')
            ->line('We\'re excited to have you on board!')
            ->action('Get Started', url('/dashboard'))
            ->line('If you have any questions, feel free to reach out to our support team.')
            ->salutation('Best regards, The Team');
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
            'title' => 'Welcome!',
            'message' => 'Thank you for registering. Get started by exploring your dashboard.',
            'icon' => '👋',
            'action_url' => url('/dashboard'),
            'action_text' => 'Get Started'
        ];
    }
}

/**
 * Helper function to generate URLs.
 *
 * @param string $path
 * @return string
 */
function url(string $path): string
{
    $baseUrl = env('APP_URL', 'http://localhost:8000');
    return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
}
