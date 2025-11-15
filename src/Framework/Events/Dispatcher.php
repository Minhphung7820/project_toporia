<?php

declare(strict_types=1);

namespace Toporia\Framework\Events;

use Toporia\Framework\Events\Contracts\EventDispatcherInterface;
use Toporia\Framework\Events\Contracts\EventInterface;

/**
 * Event Dispatcher implementation.
 *
 * Features:
 * - Priority-based listener execution
 * - Event propagation control
 * - Support for both event objects and simple event names
 * - Listener management (add, remove, check)
 */
final class Dispatcher implements EventDispatcherInterface
{
    /**
     * @var array<string, array<int, array<callable>>> Event listeners grouped by event name and priority.
     */
    private array $listeners = [];

    /**
     * @var array<string, array<callable>>|null Sorted listeners cache.
     */
    private ?array $sortedListeners = null;

    /**
     * {@inheritdoc}
     */
    public function listen(string $eventName, callable $listener, int $priority = 0): void
    {
        $this->listeners[$eventName][$priority][] = $listener;

        // Invalidate sorted cache
        $this->sortedListeners = null;
    }

    /**
     * {@inheritdoc}
     */
    public function dispatch(string|EventInterface $event, array $payload = []): EventInterface
    {
        // Convert string to GenericEvent
        if (is_string($event)) {
            $eventName = $event;
            $event = new GenericEvent($eventName, $payload);
        } else {
            $eventName = $event->getName();
        }

        // Get sorted listeners for this event
        $listeners = $this->getListeners($eventName);

        // Dispatch to each listener
        foreach ($listeners as $listener) {
            // Stop if propagation was stopped
            if ($event->isPropagationStopped()) {
                break;
            }

            $listener($event);
        }

        return $event;
    }

    /**
     * {@inheritdoc}
     */
    public function hasListeners(string $eventName): bool
    {
        return !empty($this->listeners[$eventName]);
    }

    /**
     * {@inheritdoc}
     */
    public function removeListeners(string $eventName): void
    {
        unset($this->listeners[$eventName]);
        $this->sortedListeners = null;
    }

    /**
     * {@inheritdoc}
     */
    public function getListeners(string $eventName): array
    {
        if (!isset($this->listeners[$eventName])) {
            return [];
        }

        // Return cached sorted listeners if available
        if (isset($this->sortedListeners[$eventName])) {
            return $this->sortedListeners[$eventName];
        }

        // Sort listeners by priority (higher priority first)
        $prioritizedListeners = $this->listeners[$eventName];
        krsort($prioritizedListeners);

        // Flatten the array
        $sorted = [];
        foreach ($prioritizedListeners as $listeners) {
            foreach ($listeners as $listener) {
                $sorted[] = $listener;
            }
        }

        // Cache the sorted result
        $this->sortedListeners[$eventName] = $sorted;

        return $sorted;
    }

    /**
     * Subscribe multiple listeners at once.
     *
     * @param array<string, callable|array> $subscribers Event => listener mapping.
     *        Value can be callable or [callable, priority].
     * @return void
     */
    public function subscribe(array $subscribers): void
    {
        foreach ($subscribers as $eventName => $listener) {
            if (is_array($listener)) {
                [$callable, $priority] = $listener;
                $this->listen($eventName, $callable, $priority);
            } else {
                $this->listen($eventName, $listener);
            }
        }
    }

    /**
     * Get all registered event names.
     *
     * @return array<string>
     */
    public function getEventNames(): array
    {
        return array_keys($this->listeners);
    }

    /**
     * Count listeners for an event.
     *
     * @param string $eventName Event name.
     * @return int
     */
    public function countListeners(string $eventName): int
    {
        return count($this->getListeners($eventName));
    }
}
