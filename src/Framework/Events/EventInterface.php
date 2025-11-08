<?php

declare(strict_types=1);

namespace Toporia\Framework\Events;

/**
 * Event object interface.
 *
 * Events are objects that encapsulate information about something
 * that happened in the application.
 */
interface EventInterface
{
    /**
     * Get the event name.
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Check if event propagation has been stopped.
     *
     * @return bool
     */
    public function isPropagationStopped(): bool;

    /**
     * Stop event propagation to other listeners.
     *
     * @return void
     */
    public function stopPropagation(): void;
}
