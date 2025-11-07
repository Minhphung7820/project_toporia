<?php

declare(strict_types=1);

namespace Framework\Events;

/**
 * Event Dispatcher interface.
 *
 * Manages event listeners and dispatches events to them.
 */
interface EventDispatcherInterface
{
    /**
     * Register an event listener.
     *
     * @param string $eventName Event name or class.
     * @param callable $listener Callable listener.
     * @param int $priority Higher priority listeners execute first (default: 0).
     * @return void
     */
    public function listen(string $eventName, callable $listener, int $priority = 0): void;

    /**
     * Dispatch an event to all registered listeners.
     *
     * @param string|EventInterface $event Event name or event object.
     * @param array $payload Event data (used if event is string).
     * @return EventInterface The event object after dispatch.
     */
    public function dispatch(string|EventInterface $event, array $payload = []): EventInterface;

    /**
     * Check if an event has listeners.
     *
     * @param string $eventName Event name.
     * @return bool
     */
    public function hasListeners(string $eventName): bool;

    /**
     * Remove all listeners for an event.
     *
     * @param string $eventName Event name.
     * @return void
     */
    public function removeListeners(string $eventName): void;

    /**
     * Get all listeners for an event.
     *
     * @param string $eventName Event name.
     * @return array<callable>
     */
    public function getListeners(string $eventName): array;
}
