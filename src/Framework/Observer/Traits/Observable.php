<?php

declare(strict_types=1);

namespace Toporia\Framework\Observer\Traits;

use Toporia\Framework\Observer\Contracts\ObservableInterface;
use Toporia\Framework\Observer\Contracts\ObserverInterface;
use Toporia\Framework\Observer\Contracts\ObserverManagerInterface;

/**
 * Observable Trait
 *
 * Provides observable functionality to any class.
 * Implements the Observer pattern for state change notifications.
 *
 * Performance Optimizations:
 * - Lazy observer loading (only when needed)
 * - Observer caching per event
 * - Efficient observer lookup (O(1) for event-specific, O(N) for all events)
 * - Priority-based observer execution
 *
 * SOLID Principles:
 * - Single Responsibility: Only handles observer management
 * - Open/Closed: Can be added to any class via trait
 * - Dependency Inversion: Depends on ObserverInterface abstraction
 *
 * Usage:
 * ```php
 * class MyModel {
 *     use Observable;
 *
 *     public function save() {
 *         // ... save logic ...
 *         $this->notify('saved', ['id' => $this->id]);
 *     }
 * }
 * ```
 *
 * @package Toporia\Framework\Observer\Traits
 */
trait Observable
{
    /**
     * @var array<string, array<int, array<ObserverInterface>>> Observers grouped by event and priority
     */
    private array $observers = [];

    /**
     * @var array<string, array<ObserverInterface>>|null Cached sorted observers per event
     */
    private ?array $sortedObserversCache = null;

    /**
     * @var bool Whether observers are loaded from manager
     */
    private bool $observersLoaded = false;

    /**
     * {@inheritdoc}
     */
    public function attach(ObserverInterface $observer, ?string $event = null): void
    {
        $eventKey = $event ?? '*';
        $priority = 0; // Default priority

        // Extract priority if observer has getPriority method
        if (method_exists($observer, 'getPriority')) {
            $priority = $observer->getPriority();
        }

        $this->observers[$eventKey][$priority][] = $observer;

        // Invalidate cache
        $this->sortedObserversCache = null;
    }

    /**
     * {@inheritdoc}
     */
    public function detach(ObserverInterface $observer, ?string $event = null): void
    {
        $eventKey = $event ?? '*';

        if (!isset($this->observers[$eventKey])) {
            return;
        }

        // Remove observer from all priorities
        foreach ($this->observers[$eventKey] as $priority => $observers) {
            $this->observers[$eventKey][$priority] = array_filter(
                $observers,
                fn($obs) => $obs !== $observer
            );

            // Remove empty priority arrays
            if (empty($this->observers[$eventKey][$priority])) {
                unset($this->observers[$eventKey][$priority]);
            }
        }

        // Remove event key if empty
        if (empty($this->observers[$eventKey])) {
            unset($this->observers[$eventKey]);
        }

        // Invalidate cache
        $this->sortedObserversCache = null;
    }

    /**
     * {@inheritdoc}
     */
    public function notify(string $event, array $data = []): void
    {
        // Lazy load observers from manager if not loaded
        if (!$this->observersLoaded) {
            $this->loadObserversFromManager();
            $this->observersLoaded = true;
        }

        $observers = $this->getObservers($event);

        if (empty($observers)) {
            return; // No observers, skip notification
        }

        // Notify all observers
        foreach ($observers as $observer) {
            try {
                $observer->update($this, $event, $data);
            } catch (\Throwable $e) {
                // Log error but continue with other observers
                error_log("Observer error: {$e->getMessage()}");
                if (function_exists('logger')) {
                    logger()->error("Observer error", [
                        'observer' => get_class($observer),
                        'event' => $event,
                        'exception' => $e,
                    ]);
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getObservers(?string $event = null): array
    {
        // Lazy load observers from manager if not loaded
        if (!$this->observersLoaded) {
            $this->loadObserversFromManager();
            $this->observersLoaded = true;
        }

        $eventKey = $event ?? '*';

        // Return cached sorted observers if available
        if (isset($this->sortedObserversCache[$eventKey])) {
            return $this->sortedObserversCache[$eventKey];
        }

        // Get observers for specific event and wildcard
        $allObservers = [];

        // Event-specific observers
        if (isset($this->observers[$eventKey])) {
            $allObservers = array_merge($allObservers, $this->observers[$eventKey]);
        }

        // Wildcard observers (for all events)
        if ($eventKey !== '*' && isset($this->observers['*'])) {
            $allObservers = array_merge($allObservers, $this->observers['*']);
        }

        if (empty($allObservers)) {
            $this->sortedObserversCache[$eventKey] = [];
            return [];
        }

        // Sort by priority (higher priority first)
        krsort($allObservers);

        // Flatten and cache
        $sorted = [];
        foreach ($allObservers as $priority => $observers) {
            foreach ($observers as $observer) {
                $sorted[] = $observer;
            }
        }

        $this->sortedObserversCache[$eventKey] = $sorted;

        return $sorted;
    }

    /**
     * {@inheritdoc}
     */
    public function hasObservers(?string $event = null): bool
    {
        return !empty($this->getObservers($event));
    }

    /**
     * Load observers from the global observer manager.
     *
     * Performance: Only loads once per instance
     *
     * @return void
     */
    private function loadObserversFromManager(): void
    {
        if (!function_exists('container')) {
            return; // Container not available
        }

        try {
            $manager = container(\Toporia\Framework\Observer\Contracts\ObserverManagerInterface::class);
            $observableClass = static::class;

            // Get all observers for this class
            $observers = $manager->getObservers($observableClass);

            // Attach them
            foreach ($observers as $observer) {
                $this->attach($observer);
            }

            // Also load event-specific observers
            $events = ['created', 'updated', 'deleted', 'saving', 'saved'];
            foreach ($events as $event) {
                $eventObservers = $manager->getObservers($observableClass, $event);
                foreach ($eventObservers as $observer) {
                    $this->attach($observer, $event);
                }
            }
        } catch (\Throwable $e) {
            // Silently fail if manager not available
            // This allows the trait to work even without the manager
        }
    }
}
