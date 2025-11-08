<?php

declare(strict_types=1);

namespace Toporia\Framework\Events;

/**
 * Generic event with payload data.
 *
 * Use this for simple events that don't need custom event classes.
 */
final class GenericEvent extends Event
{
    /**
     * @param string $name Event name.
     * @param array $payload Event data.
     */
    public function __construct(
        private string $name,
        private array $payload = []
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get event payload.
     *
     * @return array
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    /**
     * Get a specific payload value.
     *
     * @param string $key Payload key.
     * @param mixed $default Default value.
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->payload[$key] ?? $default;
    }

    /**
     * Set a payload value.
     *
     * @param string $key Payload key.
     * @param mixed $value Payload value.
     * @return void
     */
    public function set(string $key, mixed $value): void
    {
        $this->payload[$key] = $value;
    }

    /**
     * Check if payload has a key.
     *
     * @param string $key Payload key.
     * @return bool
     */
    public function has(string $key): bool
    {
        return isset($this->payload[$key]);
    }
}
