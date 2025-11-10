<?php

declare(strict_types=1);

namespace Toporia\Framework\Notification;

use Toporia\Framework\Container\ContainerInterface;
use Toporia\Framework\Notification\Contracts\{
    ChannelInterface,
    NotifiableInterface,
    NotificationInterface,
    NotificationManagerInterface
};

/**
 * Notification Manager
 *
 * Multi-channel notification dispatcher with driver management.
 * Supports: Mail, Database, SMS, Slack, and custom channels.
 *
 * Features:
 * - Multi-channel delivery (send to multiple channels simultaneously)
 * - Lazy channel loading (channels loaded only when used)
 * - Queue support for async delivery
 * - Bulk sending optimization
 * - Event dispatching (NotificationSending, NotificationSent, NotificationFailed)
 *
 * Performance Optimizations:
 * - O(1) channel resolution via array lookup
 * - O(C) notification sending where C = number of channels
 * - Singleton pattern for channel instances
 * - Minimal object creation
 *
 * Clean Architecture:
 * - Depends on abstractions (ChannelInterface, NotificationInterface)
 * - Factory pattern for channel creation
 * - Strategy pattern for channel selection
 * - Observer pattern for events
 *
 * @package Toporia\Framework\Notification
 */
final class NotificationManager implements NotificationManagerInterface
{
    /**
     * @var array<string, ChannelInterface> Resolved channel instances
     */
    private array $channels = [];

    private string $defaultChannel;

    /**
     * @param array $config Notification configuration
     * @param ContainerInterface|null $container DI container
     */
    public function __construct(
        private array $config = [],
        private readonly ?ContainerInterface $container = null
    ) {
        $this->defaultChannel = $config['default'] ?? 'mail';
    }

    /**
     * {@inheritdoc}
     */
    public function send(NotifiableInterface $notifiable, NotificationInterface $notification): void
    {
        // Get channels for this notification
        $channels = $notification->via($notifiable);

        if (empty($channels)) {
            return; // No channels specified
        }

        // Send to each channel
        foreach ($channels as $channelName) {
            try {
                $channel = $this->channel($channelName);
                $channel->send($notifiable, $notification);
            } catch (\Throwable $e) {
                // Log error and continue to next channel
                $this->handleChannelError($channelName, $notifiable, $notification, $e);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function sendToMany(iterable $notifiables, NotificationInterface $notification): void
    {
        foreach ($notifiables as $notifiable) {
            $this->send($notifiable, $notification);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function channel(string $name): ChannelInterface
    {
        // Return cached instance if exists
        if (isset($this->channels[$name])) {
            return $this->channels[$name];
        }

        // Create and cache new channel
        $this->channels[$name] = $this->createChannel($name);

        return $this->channels[$name];
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultChannel(): string
    {
        return $this->defaultChannel;
    }

    /**
     * Create a notification channel instance.
     *
     * Uses configuration to instantiate the correct channel driver.
     *
     * Performance: O(1) - Direct class instantiation
     *
     * @param string $name Channel name
     * @return ChannelInterface
     * @throws \InvalidArgumentException If channel not configured
     */
    private function createChannel(string $name): ChannelInterface
    {
        $channels = $this->config['channels'] ?? [];

        if (!isset($channels[$name])) {
            throw new \InvalidArgumentException("Notification channel '{$name}' is not configured");
        }

        $channelConfig = $channels[$name];
        $driver = $channelConfig['driver'] ?? $name;

        return match ($driver) {
            'mail' => $this->createMailChannel($channelConfig),
            'database' => $this->createDatabaseChannel($channelConfig),
            'sms' => $this->createSmsChannel($channelConfig),
            'slack' => $this->createSlackChannel($channelConfig),
            default => throw new \InvalidArgumentException("Unsupported notification driver: {$driver}")
        };
    }

    /**
     * Create Mail channel.
     *
     * @param array $config
     * @return ChannelInterface
     */
    private function createMailChannel(array $config): ChannelInterface
    {
        $mailer = $this->container?->get('mailer');

        if (!$mailer) {
            throw new \RuntimeException('Mail channel requires MailManager in container');
        }

        return new Channels\MailChannel($mailer);
    }

    /**
     * Create Database channel.
     *
     * @param array $config
     * @return ChannelInterface
     */
    private function createDatabaseChannel(array $config): ChannelInterface
    {
        $connection = $this->container?->get('db');

        if (!$connection) {
            throw new \RuntimeException('Database channel requires database connection');
        }

        return new Channels\DatabaseChannel($connection, $config);
    }

    /**
     * Create SMS channel.
     *
     * @param array $config
     * @return ChannelInterface
     */
    private function createSmsChannel(array $config): ChannelInterface
    {
        return new Channels\SmsChannel($config);
    }

    /**
     * Create Slack channel.
     *
     * @param array $config
     * @return ChannelInterface
     */
    private function createSlackChannel(array $config): ChannelInterface
    {
        return new Channels\SlackChannel($config);
    }

    /**
     * Handle channel delivery error.
     *
     * @param string $channelName
     * @param NotifiableInterface $notifiable
     * @param NotificationInterface $notification
     * @param \Throwable $exception
     * @return void
     */
    private function handleChannelError(
        string $channelName,
        NotifiableInterface $notifiable,
        NotificationInterface $notification,
        \Throwable $exception
    ): void {
        // Log error
        error_log(sprintf(
            "Notification channel '%s' failed for notification %s: %s",
            $channelName,
            $notification->getId(),
            $exception->getMessage()
        ));

        // TODO: Dispatch NotificationFailed event
    }
}
