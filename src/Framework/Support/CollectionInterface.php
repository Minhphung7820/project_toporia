<?php

declare(strict_types=1);

namespace Framework\Support;

/**
 * Collection Interface
 *
 * Defines contract for immutable collection operations.
 * Inspired by functional programming principles.
 */
interface CollectionInterface extends \IteratorAggregate, \Countable, \ArrayAccess
{
    /**
     * Create new collection from items.
     */
    public static function make(mixed $items = []): self;

    /**
     * Create collection from range.
     */
    public static function range(int $start, int $end, int $step = 1): self;

    /**
     * Create collection by repeating value.
     */
    public static function times(int $count, callable $callback): self;

    /**
     * Get all items as array.
     */
    public function all(): array;

    /**
     * Get first item or default.
     */
    public function first(callable $callback = null, mixed $default = null): mixed;

    /**
     * Get last item or default.
     */
    public function last(callable $callback = null, mixed $default = null): mixed;

    /**
     * Map over items.
     */
    public function map(callable $callback): self;

    /**
     * Filter items using callback.
     */
    public function filter(callable $callback = null): self;

    /**
     * Reduce collection to single value.
     */
    public function reduce(callable $callback, mixed $initial = null): mixed;

    /**
     * Check if any item passes test.
     */
    public function some(callable $callback): bool;

    /**
     * Check if all items pass test.
     */
    public function every(callable $callback): bool;

    /**
     * Flatten multi-dimensional collection.
     */
    public function flatten(int $depth = INF): self;

    /**
     * Get unique items.
     */
    public function unique(string|callable|null $key = null): self;

    /**
     * Sort collection.
     */
    public function sort(callable $callback = null): self;

    /**
     * Take first N items.
     */
    public function take(int $limit): self;

    /**
     * Skip first N items.
     */
    public function skip(int $offset): self;

    /**
     * Chunk collection into smaller collections.
     */
    public function chunk(int $size): self;

    /**
     * Group items by key or callback.
     */
    public function groupBy(string|callable $key): self;

    /**
     * Partition into two collections based on callback.
     */
    public function partition(callable $callback): array;

    /**
     * Zip with other collections.
     */
    public function zip(mixed ...$arrays): self;

    /**
     * Get items as JSON.
     */
    public function toJson(int $options = 0): string;

    /**
     * Check if collection is empty.
     */
    public function isEmpty(): bool;

    /**
     * Check if collection is not empty.
     */
    public function isNotEmpty(): bool;

    /**
     * Execute callback on each item.
     */
    public function each(callable $callback): self;

    /**
     * Pipe collection through callback.
     */
    public function pipe(callable $callback): mixed;

    /**
     * Tap into collection without modifying it.
     */
    public function tap(callable $callback): self;
}
