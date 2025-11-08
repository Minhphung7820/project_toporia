<?php

declare(strict_types=1);

namespace Toporia\Framework\Events;

/**
 * Base event implementation.
 *
 * Provides common functionality for all events including
 * propagation control.
 */
abstract class Event implements EventInterface
{
    /**
     * @var bool Whether propagation has been stopped.
     */
    private bool $propagationStopped = false;

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return static::class;
    }

    /**
     * {@inheritdoc}
     */
    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }

    /**
     * {@inheritdoc}
     */
    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }
}
