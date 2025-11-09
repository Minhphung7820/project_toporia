<?php

declare(strict_types=1);

namespace Toporia\Framework\Support\Pagination;

use Toporia\Framework\Support\Collection;

/**
 * Paginator - Immutable value object representing a paginated result set.
 *
 * This class follows Clean Architecture and SOLID principles:
 *
 * Clean Architecture:
 * - Value Object pattern from Domain layer
 * - No dependencies on infrastructure (framework-agnostic)
 * - Immutable state ensures data integrity
 *
 * SOLID Principles:
 * - Single Responsibility: Only handles pagination data representation
 * - Open/Closed: Extensible through inheritance, immutable core
 * - Liskov Substitution: Can be substituted in any context expecting pagination
 * - Interface Segregation: Provides only pagination-specific methods
 * - Dependency Inversion: Accepts generic Collection, not specific implementation
 *
 * High Reusability:
 * - Works with any Collection type (RowCollection, ModelCollection, etc.)
 * - JSON serializable for API responses
 * - Array accessible for template rendering
 * - Immutable for thread safety
 *
 * @template TValue
 */
class Paginator implements \JsonSerializable
{
    /**
     * @param Collection<int, TValue> $items Items for current page
     * @param int $total Total number of items across all pages
     * @param int $perPage Number of items per page
     * @param int $currentPage Current page number (1-indexed)
     * @param string|null $path Base URL path for pagination links
     */
    public function __construct(
        private readonly Collection $items,
        private readonly int $total,
        private readonly int $perPage,
        private readonly int $currentPage = 1,
        private readonly ?string $path = null
    ) {}

    /**
     * Get the items for the current page.
     *
     * @return Collection<int, TValue>
     */
    public function items(): Collection
    {
        return $this->items;
    }

    /**
     * Get the total number of items.
     */
    public function total(): int
    {
        return $this->total;
    }

    /**
     * Get the number of items per page.
     */
    public function perPage(): int
    {
        return $this->perPage;
    }

    /**
     * Get the current page number.
     */
    public function currentPage(): int
    {
        return $this->currentPage;
    }

    /**
     * Get the last page number.
     */
    public function lastPage(): int
    {
        return (int) ceil($this->total / $this->perPage);
    }

    /**
     * Get the number of the first item on the page.
     */
    public function firstItem(): int
    {
        if ($this->total === 0) {
            return 0;
        }
        return ($this->currentPage - 1) * $this->perPage + 1;
    }

    /**
     * Get the number of the last item on the page.
     */
    public function lastItem(): int
    {
        return min($this->firstItem() + $this->items->count() - 1, $this->total);
    }

    /**
     * Determine if there are more pages.
     */
    public function hasMorePages(): bool
    {
        return $this->currentPage < $this->lastPage();
    }

    /**
     * Determine if there are no items.
     */
    public function isEmpty(): bool
    {
        return $this->items->isEmpty();
    }

    /**
     * Determine if there are items.
     */
    public function isNotEmpty(): bool
    {
        return !$this->isEmpty();
    }

    /**
     * Get the URL for a given page number.
     *
     * @param int $page Page number
     * @return string|null
     */
    public function url(int $page): ?string
    {
        if ($this->path === null) {
            return null;
        }

        return $this->path . '?page=' . $page;
    }

    /**
     * Get the URL for the next page.
     */
    public function nextPageUrl(): ?string
    {
        if (!$this->hasMorePages()) {
            return null;
        }

        return $this->url($this->currentPage + 1);
    }

    /**
     * Get the URL for the previous page.
     */
    public function previousPageUrl(): ?string
    {
        if ($this->currentPage <= 1) {
            return null;
        }

        return $this->url($this->currentPage - 1);
    }

    /**
     * Convert the paginator to an array.
     *
     * This format is compatible with Laravel's pagination format.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'data' => $this->items->toArray(),
            'current_page' => $this->currentPage,
            'per_page' => $this->perPage,
            'total' => $this->total,
            'last_page' => $this->lastPage(),
            'from' => $this->firstItem(),
            'to' => $this->lastItem(),
            'path' => $this->path,
            'first_page_url' => $this->url(1),
            'last_page_url' => $this->url($this->lastPage()),
            'next_page_url' => $this->nextPageUrl(),
            'prev_page_url' => $this->previousPageUrl(),
        ];
    }

    /**
     * Convert the paginator to JSON.
     *
     * Implements JsonSerializable for automatic JSON encoding.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Convert the paginator to JSON string.
     */
    public function toJson(int $options = 0): string
    {
        return json_encode($this->jsonSerialize(), $options);
    }

    /**
     * Get the items as a plain array (shortcut).
     *
     * @return array<int, mixed>
     */
    public function all(): array
    {
        return $this->items->all();
    }

    /**
     * Apply a callback to each item.
     *
     * @param callable $callback
     * @return static New paginator with transformed items
     */
    public function map(callable $callback): static
    {
        return new static(
            $this->items->map($callback),
            $this->total,
            $this->perPage,
            $this->currentPage,
            $this->path
        );
    }

    /**
     * Magic method to make items iterable.
     *
     * Allows: foreach ($paginator as $item)
     *
     * @return \Traversable<int, TValue>
     */
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->items->all());
    }
}
