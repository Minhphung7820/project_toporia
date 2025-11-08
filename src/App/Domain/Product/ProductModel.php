<?php

declare(strict_types=1);

namespace App\Domain\Product;

use Toporia\Framework\Database\ORM\Model;

/**
 * Product ORM Model.
 *
 * Represents a product entity with Active Record pattern.
 *
 * @property int $id
 * @property string $title
 * @property string|null $sku
 * @property string|null $description
 * @property float $price
 * @property int $stock
 * @property bool $is_active
 * @property string $created_at
 * @property string $updated_at
 */
class ProductModel extends Model
{
    /**
     * {@inheritdoc}
     */
    protected static string $table = 'products';

    /**
     * {@inheritdoc}
     */
    protected static array $fillable = [
        'title',
        'sku',
        'description',
        'price',
        'stock',
        'is_active'
    ];

    /**
     * {@inheritdoc}
     */
    protected static array $casts = [
        'price' => 'float',
        'stock' => 'int',
        'is_active' => 'bool'
    ];

    /**
     * Hook: Called before creating.
     *
     * @return void
     */
    protected function creating(): void
    {
        // Example: Set default values
        if (!isset($this->is_active)) {
            $this->is_active = true;
        }

        if (!isset($this->stock)) {
            $this->stock = 0;
        }
    }

    /**
     * Hook: Called after created.
     *
     * @return void
     */
    protected function created(): void
    {
        // Example: Dispatch event, clear cache, etc.
        // event(new ProductCreated($this));
    }

    /**
     * Hook: Called before updating.
     *
     * @return void
     */
    protected function updating(): void
    {
        // Example: Validate price
        if (isset($this->price) && $this->price < 0) {
            throw new \InvalidArgumentException('Price cannot be negative');
        }
    }

    /**
     * Check if product is in stock.
     *
     * @return bool
     */
    public function inStock(): bool
    {
        return $this->stock > 0;
    }

    /**
     * Decrease stock quantity.
     *
     * @param int $quantity Quantity to decrease.
     * @return bool
     */
    public function decreaseStock(int $quantity): bool
    {
        if ($this->stock < $quantity) {
            return false;
        }

        $this->stock -= $quantity;
        return $this->save();
    }

    /**
     * Increase stock quantity.
     *
     * @param int $quantity Quantity to increase.
     * @return bool
     */
    public function increaseStock(int $quantity): bool
    {
        $this->stock += $quantity;
        return $this->save();
    }

    /**
     * Scope: Get only active products.
     *
     * Usage: ProductModel::active()->get()
     *
     * @return \Framework\Database\Query\QueryBuilder
     */
    public static function active()
    {
        return static::query()->where('is_active', true);
    }

    /**
     * Scope: Get low stock products.
     *
     * @param int $threshold Stock threshold.
     * @return \Framework\Database\Query\QueryBuilder
     */
    public static function lowStock(int $threshold = 10)
    {
        return static::query()
            ->where('stock', '<=', $threshold)
            ->where('is_active', true);
    }
}
