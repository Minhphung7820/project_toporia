<?php

declare(strict_types=1);

namespace Toporia\Framework\Observer;

use Toporia\Framework\Observer\Contracts\ObservableInterface;
use Toporia\Framework\Observer\Contracts\ObserverInterface;

/**
 * Abstract Observer
 *
 * Base class for observers with common functionality.
 * Provides default implementations and helper methods.
 *
 * Performance:
 * - Lightweight base class
 * - No overhead if not used
 * - Efficient method dispatch
 *
 * SOLID Principles:
 * - Single Responsibility: Base observer functionality only
 * - Open/Closed: Extensible via inheritance
 * - Liskov Substitution: Implements ObserverInterface correctly
 *
 * Usage:
 * ```php
 * class MyObserver extends AbstractObserver
 * {
 *     public function update(ObservableInterface $observable, string $event, array $data = []): void
 *     {
 *         match($event) {
 *             'created' => $this->handleCreated($observable, $data),
 *             'updated' => $this->handleUpdated($observable, $data),
 *             default => null,
 *         };
 *     }
 *
 *     private function handleCreated(ObservableInterface $observable, array $data): void
 *     {
 *         // Handle created event
 *     }
 * }
 * ```
 *
 * @package Toporia\Framework\Observer
 */
abstract class AbstractObserver implements ObserverInterface
{
    /**
     * Observer priority (higher = executed first).
     *
     * @var int
     */
    protected int $priority = 0;

    /**
     * Get observer priority.
     *
     * @return int
     */
    public function getPriority(): int
    {
        return $this->priority;
    }

    /**
     * Set observer priority.
     *
     * @param int $priority
     * @return $this
     */
    public function setPriority(int $priority): self
    {
        $this->priority = $priority;
        return $this;
    }

    /**
     * Check if this observer should handle a specific event.
     *
     * Override this method to filter events.
     *
     * @param string $event Event name
     * @return bool
     */
    protected function shouldHandle(string $event): bool
    {
        return true; // Default: handle all events
    }

    /**
     * {@inheritdoc}
     */
    public function update(ObservableInterface $observable, string $event, array $data = []): void
    {
        if (!$this->shouldHandle($event)) {
            return;
        }

        // Delegate to specific handler method if exists
        $method = 'handle' . ucfirst($event);
        if (method_exists($this, $method)) {
            $this->{$method}($observable, $data);
            return;
        }

        // Fallback to generic handler
        $this->handle($observable, $event, $data);
    }

    /**
     * Generic handler for all events.
     *
     * Override this method for generic event handling.
     *
     * @param ObservableInterface $observable The observable object
     * @param string $event Event name
     * @param array<string, mixed> $data Event data
     * @return void
     */
    protected function handle(ObservableInterface $observable, string $event, array $data): void
    {
        // Default: do nothing (override in subclasses)
    }

    // =========================================================================
    // CONDITIONAL HELPER METHODS (Laravel-like API)
    // =========================================================================

    /**
     * Check if any field is dirty (changed).
     *
     * Laravel-like API:
     * - $observer->isDirty() - check if any field changed
     * - $observer->isDirty('price') - check if specific field changed
     * - $observer->isDirty(['price', 'stock']) - check if any of these fields changed
     *
     * @param string|array<string>|null $field Field name(s) to check (null = any field)
     * @param array<string, mixed> $data Event data
     * @return bool
     */
    protected function isDirty(string|array|null $field = null, array $data = []): bool
    {
        // No field specified: check if any field is dirty
        if ($field === null) {
            return (bool) ($data['is_dirty'] ?? false);
        }

        // Single field: check if specific field is dirty
        if (is_string($field)) {
            $dirty = $data['dirty'] ?? [];
            return isset($dirty[$field]);
        }

        // Array of fields: check if any of these fields are dirty
        if (is_array($field)) {
            return $this->isDirtyAny($field, $data);
        }

        return false;
    }

    /**
     * Check if a specific field is dirty (changed).
     *
     * Alias for isDirty() with field parameter.
     * Kept for backward compatibility and clarity.
     *
     * @param string $field Field name to check
     * @param array<string, mixed> $data Event data
     * @return bool
     */
    protected function isDirtyField(string $field, array $data = []): bool
    {
        return $this->isDirty($field, $data);
    }

    /**
     * Get all dirty (changed) fields.
     *
     * Laravel-like API: $observer->getDirty()
     *
     * @param array<string, mixed> $data Event data
     * @return array<string, mixed> Dirty fields with new values
     */
    protected function getDirty(array $data = []): array
    {
        return $data['dirty'] ?? [];
    }

    /**
     * Check if a field was changed (alias for isDirtyField).
     *
     * Laravel-like API: $observer->wasChanged('price')
     *
     * @param string|null $field Field name (null = any field)
     * @param array<string, mixed> $data Event data
     * @return bool
     */
    protected function wasChanged(?string $field = null, array $data = []): bool
    {
        if ($field === null) {
            return $this->isDirty($data);
        }

        return $this->isDirtyField($field, $data);
    }

    /**
     * Get the original value of a field (before change).
     *
     * Laravel-like API: $observer->getOriginal('price')
     *
     * @param string|null $field Field name (null = all original values)
     * @param array<string, mixed> $data Event data
     * @return mixed Original value(s)
     */
    protected function getOriginal(?string $field = null, array $data = [])
    {
        $original = $data['original'] ?? [];

        if ($field === null) {
            return $original;
        }

        return $original[$field] ?? null;
    }

    /**
     * Check if a field had a specific value before change.
     *
     * Laravel-like API: $observer->was('price', 100)
     *
     * @param string $field Field name
     * @param mixed $value Value to check
     * @param array<string, mixed> $data Event data
     * @return bool
     */
    protected function was(string $field, mixed $value, array $data = []): bool
    {
        $original = $this->getOriginal($field, $data);
        return $original === $value;
    }

    /**
     * Check if a field has a specific value now (after change).
     *
     * Laravel-like API: $observer->is('price', 200)
     *
     * @param string $field Field name
     * @param mixed $value Value to check
     * @param array<string, mixed> $data Event data
     * @return bool
     */
    protected function is(string $field, mixed $value, array $data = []): bool
    {
        $attributes = $data['attributes'] ?? [];
        return ($attributes[$field] ?? null) === $value;
    }

    /**
     * Check if any of the specified fields are dirty.
     *
     * Laravel-like API: $observer->isDirty(['price', 'stock'])
     *
     * @param array<string> $fields Field names to check
     * @param array<string, mixed> $data Event data
     * @return bool
     */
    protected function isDirtyAny(array $fields, array $data = []): bool
    {
        $dirty = $this->getDirty($data);
        return !empty(array_intersect($fields, array_keys($dirty)));
    }

    /**
     * Check if all of the specified fields are dirty.
     *
     * @param array<string> $fields Field names to check
     * @param array<string, mixed> $data Event data
     * @return bool
     */
    protected function isDirtyAll(array $fields, array $data = []): bool
    {
        $dirty = $this->getDirty($data);
        return count(array_intersect($fields, array_keys($dirty))) === count($fields);
    }

    /**
     * Get the old value of a dirty field.
     *
     * Laravel-like API: $observer->getOldValue('price')
     *
     * @param string $field Field name
     * @param array<string, mixed> $data Event data
     * @return mixed Old value or null if not dirty
     */
    protected function getOldValue(string $field, array $data = [])
    {
        if (!$this->isDirtyField($field, $data)) {
            return null;
        }

        return $this->getOriginal($field, $data);
    }

    /**
     * Get the new value of a dirty field.
     *
     * @param string $field Field name
     * @param array<string, mixed> $data Event data
     * @return mixed New value or null if not dirty
     */
    protected function getNewValue(string $field, array $data = [])
    {
        if (!$this->isDirtyField($field, $data)) {
            return null;
        }

        $attributes = $data['attributes'] ?? [];
        return $attributes[$field] ?? null;
    }

    /**
     * Check if observable is a Model instance.
     *
     * @param ObservableInterface $observable The observable object
     * @return bool
     */
    protected function isModel(ObservableInterface $observable): bool
    {
        return $observable instanceof \Toporia\Framework\Database\ORM\Model;
    }
}
