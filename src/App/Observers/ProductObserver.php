<?php

declare(strict_types=1);

namespace App\Observers;

use Toporia\Framework\Observer\AbstractObserver;
use Toporia\Framework\Observer\Contracts\ObservableInterface;
use Toporia\Framework\Support\Accessors\Log;
use App\Domain\Product\ProductModel;

/**
 * Product Observer
 *
 * Example observer for ProductModel that demonstrates observer pattern usage.
 *
 * Performance:
 * - Lightweight observer (minimal overhead)
 * - Event-specific handling (only handles relevant events)
 * - Efficient method dispatch
 *
 * SOLID Principles:
 * - Single Responsibility: Only handles Product model events
 * - Open/Closed: Extensible via inheritance
 * - Dependency Inversion: Depends on ObservableInterface abstraction
 *
 * @package App\Observers
 */
final class ProductObserver extends AbstractObserver
{
    /**
     * Observer priority (higher = executed first).
     *
     * @var int
     */
    protected int $priority = 0;

    /**
     * {@inheritdoc}
     */
    protected function shouldHandle(string $event): bool
    {
        // Only handle lifecycle events
        return in_array($event, ['created', 'updated', 'deleted', 'saving', 'saved']);
    }

    /**
     * Handle created event.
     *
     * @param ObservableInterface $observable The observable object
     * @param array<string, mixed> $data Event data
     * @return void
     */
    protected function handleCreated(ObservableInterface $observable, array $data): void
    {
        if (!$observable instanceof ProductModel) {
            return;
        }

        $productId = $observable->id ?? 'unknown';
        Log::info("ProductObserver: Product created", [
            'product_id' => $productId,
            'title' => $observable->title ?? null,
            'price' => $observable->price ?? null,
        ]);

        // Example: Send notification, update cache, etc.
        // $this->sendNotification($observable);
        // $this->updateCache($observable);
    }

    /**
     * Handle updated event.
     *
     * Demonstrates conditional observer execution using dirty field checks.
     *
     * @param ObservableInterface $observable The observable object
     * @param array<string, mixed> $data Event data
     * @return void
     */
    protected function handleUpdated(ObservableInterface $observable, array $data): void
    {
        if (!$observable instanceof ProductModel) {
            return;
        }

        // Only proceed if something actually changed
        if (!$this->isDirty(null, $data)) {
            return; // No changes, skip observer
        }

        $productId = $observable->id ?? 'unknown';

        // Example 1: Only handle if price changed (Laravel-like syntax)
        if ($this->isDirty('price', $data)) {
            $oldPrice = $this->getOldValue('price', $data);
            $newPrice = $this->getNewValue('price', $data);

            Log::info("ProductObserver: Price changed", [
                'product_id' => $productId,
                'old_price' => $oldPrice,
                'new_price' => $newPrice,
            ]);

            // Example: Send price change notification
            // $this->notifyPriceChange($observable, $oldPrice, $newPrice);
        }

        // Example 2: Only handle if stock changed
        if ($this->wasChanged('stock', $data)) {
            $oldStock = $this->getOriginal('stock', $data);
            $newStock = $data['attributes']['stock'] ?? null;

            Log::info("ProductObserver: Stock changed", [
                'product_id' => $productId,
                'old_stock' => $oldStock,
                'new_stock' => $newStock,
            ]);

            // Example: Check if stock went to zero
            if ($this->was('stock', 0, $data) || ($newStock === 0 && $oldStock > 0)) {
                Log::info("ProductObserver: Product out of stock", [
                    'product_id' => $productId,
                ]);
                // $this->notifyOutOfStock($observable);
            }
        }

        // Example 3: Handle if any of these fields changed
        if ($this->isDirtyAny(['price', 'stock', 'title'], $data)) {
            $dirtyFields = $this->getDirty($data);
            Log::info("ProductObserver: Important fields changed", [
                'product_id' => $productId,
                'dirty_fields' => array_keys($dirtyFields),
            ]);
        }

        // Example 4: Check if price was changed from a specific value
        if ($this->isDirty('price', $data) && $this->was('price', 0, $data)) {
            Log::info("ProductObserver: Price changed from 0 (was free)", [
                'product_id' => $productId,
                'new_price' => $this->getNewValue('price', $data),
            ]);
        }

        // Example 5: Check if price is now a specific value
        if ($this->isDirty('price', $data) && $this->is('price', 0, $data)) {
            Log::info("ProductObserver: Price is now 0 (now free)", [
                'product_id' => $productId,
            ]);
        }

        // Log all changes
        $dirty = $this->getDirty($data);
        if (!empty($dirty)) {
            $changes = [];
            foreach ($dirty as $field => $newValue) {
                $changes[$field] = [
                    'old' => $this->getOriginal($field, $data),
                    'new' => $newValue,
                ];
            }

            Log::info("ProductObserver: Product updated", [
                'product_id' => $productId,
                'changes' => $changes,
            ]);
        }

        // Example: Invalidate cache, update search index, etc.
        // $this->invalidateCache($observable);
        // $this->updateSearchIndex($observable);
    }

    /**
     * Handle deleted event.
     *
     * @param ObservableInterface $observable The observable object
     * @param array<string, mixed> $data Event data
     * @return void
     */
    protected function handleDeleted(ObservableInterface $observable, array $data): void
    {
        if (!$observable instanceof ProductModel) {
            return;
        }

        $productId = $observable->id ?? 'unknown';
        Log::info("ProductObserver: Product deleted", [
            'product_id' => $productId,
        ]);

        // Example: Clean up related data, remove from cache, etc.
        // $this->cleanupRelatedData($observable);
        // $this->removeFromCache($observable);
    }

    /**
     * Handle saving event (before save).
     *
     * @param ObservableInterface $observable The observable object
     * @param array<string, mixed> $data Event data
     * @return void
     */
    protected function handleSaving(ObservableInterface $observable, array $data): void
    {
        if (!$observable instanceof ProductModel) {
            return;
        }

        // Example: Validate, normalize data, etc.
        // $this->validate($observable);
        // $this->normalizeData($observable);
    }

    /**
     * Handle saved event (after save).
     *
     * @param ObservableInterface $observable The observable object
     * @param array<string, mixed> $data Event data
     * @return void
     */
    protected function handleSaved(ObservableInterface $observable, array $data): void
    {
        if (!$observable instanceof ProductModel) {
            return;
        }

        // Example: Post-save operations
        // $this->updateRelations($observable);
    }
}
