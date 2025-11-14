<?php

declare(strict_types=1);

namespace Toporia\Framework\Observer\Contracts;

/**
 * Observer Interface
 *
 * Defines the contract for observers that watch for changes in observable objects.
 *
 * SOLID Principles:
 * - Single Responsibility: Only defines contract for observing
 * - Interface Segregation: Focused interface for observers
 * - Dependency Inversion: Observables depend on this abstraction
 *
 * Performance:
 * - Lightweight interface (no overhead)
 * - Type-safe method signatures
 * - Enables efficient observer management
 *
 * @package Toporia\Framework\Observer\Contracts
 */
interface ObserverInterface
{
    /**
     * Handle the update notification from an observable.
     *
     * This method is called when the observed object changes state.
     *
     * @param ObservableInterface $observable The observable object that changed
     * @param string $event The event that occurred (e.g., 'created', 'updated', 'deleted')
     * @param array<string, mixed> $data Additional data about the change
     * @return void
     */
    public function update(ObservableInterface $observable, string $event, array $data = []): void;
}

