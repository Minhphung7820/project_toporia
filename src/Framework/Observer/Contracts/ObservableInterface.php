<?php

declare(strict_types=1);

namespace Toporia\Framework\Observer\Contracts;

/**
 * Observable Interface (Subject)
 *
 * Defines the contract for objects that can be observed.
 * Observables notify observers when their state changes.
 *
 * SOLID Principles:
 * - Single Responsibility: Only defines contract for being observed
 * - Interface Segregation: Focused interface for observables
 * - Dependency Inversion: Observers depend on this abstraction
 *
 * Performance:
 * - Lightweight interface (no overhead)
 * - Enables efficient observer management
 * - Supports lazy observer initialization
 *
 * @package Toporia\Framework\Observer\Contracts
 */
interface ObservableInterface
{
    /**
     * Attach an observer to this observable.
     *
     * @param ObserverInterface $observer The observer to attach
     * @param string|null $event Specific event to observe (null = all events)
     * @return void
     */
    public function attach(ObserverInterface $observer, ?string $event = null): void;

    /**
     * Detach an observer from this observable.
     *
     * @param ObserverInterface $observer The observer to detach
     * @param string|null $event Specific event to stop observing (null = all events)
     * @return void
     */
    public function detach(ObserverInterface $observer, ?string $event = null): void;

    /**
     * Notify all observers about a state change.
     *
     * @param string $event The event that occurred
     * @param array<string, mixed> $data Additional data about the change
     * @return void
     */
    public function notify(string $event, array $data = []): void;

    /**
     * Get all observers for a specific event.
     *
     * @param string|null $event Event name (null = all events)
     * @return array<ObserverInterface>
     */
    public function getObservers(?string $event = null): array;

    /**
     * Check if this observable has any observers.
     *
     * @param string|null $event Event name (null = any event)
     * @return bool
     */
    public function hasObservers(?string $event = null): bool;
}

