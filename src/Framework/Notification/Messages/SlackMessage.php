<?php

declare(strict_types=1);

namespace Toporia\Framework\Notification\Messages;

/**
 * Slack Message
 *
 * Fluent builder for Slack notifications with attachments and fields.
 *
 * Usage:
 * ```php
 * return (new SlackMessage)
 *     ->content('New order received!')
 *     ->attachment(function ($attachment) {
 *         $attachment->title('Order #12345')
 *             ->fields([
 *                 'Customer' => 'John Doe',
 *                 'Total' => '$99.99',
 *                 'Status' => 'Pending'
 *             ])
 *             ->color('good');
 *     });
 * ```
 *
 * @package Toporia\Framework\Notification\Messages
 */
final class SlackMessage
{
    public string $content = '';
    public string $channel = '';
    public string $username = '';
    public string $icon = ':bell:';
    public array $attachments = [];

    /**
     * Set message content.
     *
     * @param string $content
     * @return $this
     */
    public function content(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    /**
     * Set Slack channel.
     *
     * @param string $channel
     * @return $this
     */
    public function channel(string $channel): self
    {
        $this->channel = $channel;
        return $this;
    }

    /**
     * Set bot username.
     *
     * @param string $username
     * @return $this
     */
    public function from(string $username): self
    {
        $this->username = $username;
        return $this;
    }

    /**
     * Set bot icon.
     *
     * @param string $icon Emoji or URL
     * @return $this
     */
    public function icon(string $icon): self
    {
        $this->icon = $icon;
        return $this;
    }

    /**
     * Add attachment with callback.
     *
     * @param callable $callback
     * @return $this
     */
    public function attachment(callable $callback): self
    {
        $attachment = new SlackAttachment();
        $callback($attachment);
        $this->attachments[] = $attachment;

        return $this;
    }

    /**
     * Convert to Slack API payload.
     *
     * @return array
     */
    public function toArray(): array
    {
        $payload = [
            'text' => $this->content,
        ];

        if ($this->channel) {
            $payload['channel'] = $this->channel;
        }

        if ($this->username) {
            $payload['username'] = $this->username;
        }

        if ($this->icon) {
            if (str_starts_with($this->icon, 'http')) {
                $payload['icon_url'] = $this->icon;
            } else {
                $payload['icon_emoji'] = $this->icon;
            }
        }

        if (!empty($this->attachments)) {
            $payload['attachments'] = array_map(
                fn($attachment) => $attachment->toArray(),
                $this->attachments
            );
        }

        return $payload;
    }
}

/**
 * Slack Attachment
 *
 * Represents a Slack message attachment with fields.
 */
class SlackAttachment
{
    public string $title = '';
    public string $text = '';
    public string $color = 'good';
    public array $fields = [];

    public function title(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function text(string $text): self
    {
        $this->text = $text;
        return $this;
    }

    public function color(string $color): self
    {
        $this->color = $color;
        return $this;
    }

    public function fields(array $fields): self
    {
        foreach ($fields as $key => $value) {
            $this->fields[] = [
                'title' => $key,
                'value' => $value,
                'short' => true
            ];
        }

        return $this;
    }

    public function toArray(): array
    {
        return array_filter([
            'title' => $this->title,
            'text' => $this->text,
            'color' => $this->color,
            'fields' => $this->fields,
        ]);
    }
}
