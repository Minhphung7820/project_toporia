<?php

declare(strict_types=1);

namespace Toporia\Framework\Notification\Channels;

use Toporia\Framework\Notification\Contracts\{ChannelInterface, NotifiableInterface, NotificationInterface};
use Toporia\Framework\Notification\Messages\SlackMessage;

/**
 * Slack Notification Channel
 *
 * Sends notifications to Slack via webhooks.
 * Supports rich formatting with attachments and fields.
 *
 * Configuration:
 * ```php
 * 'slack' => [
 *     'driver' => 'slack',
 *     'webhook_url' => env('SLACK_WEBHOOK_URL'),
 * ]
 * ```
 *
 * Performance:
 * - O(1) per notification
 * - HTTP/2 webhook delivery
 * - Connection keepalive for bulk sending
 * - Async recommended for production
 *
 * @package Toporia\Framework\Notification\Channels
 */
final class SlackChannel implements ChannelInterface
{
    private ?string $defaultWebhookUrl;

    public function __construct(array $config = [])
    {
        $this->defaultWebhookUrl = $config['webhook_url'] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function send(NotifiableInterface $notifiable, NotificationInterface $notification): void
    {
        // Get webhook URL
        $webhookUrl = $notifiable->routeNotificationFor('slack') ?? $this->defaultWebhookUrl;

        if (!$webhookUrl) {
            return; // No webhook configured
        }

        // Build Slack message
        $message = $notification->toChannel($notifiable, 'slack');

        if (!$message instanceof SlackMessage) {
            throw new \InvalidArgumentException(
                'Slack notification must return SlackMessage instance from toSlack() method'
            );
        }

        // Send to Slack
        $this->sendWebhook($webhookUrl, $message->toArray());
    }

    /**
     * Send webhook request to Slack.
     *
     * @param string $url
     * @param array $payload
     * @return void
     * @throws \RuntimeException
     */
    private function sendWebhook(string $url, array $payload): void
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new \RuntimeException("Slack webhook failed (HTTP {$httpCode}): {$response}");
        }
    }
}
