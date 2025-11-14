<?php

declare(strict_types=1);

namespace App\Domain\Product;

use Toporia\Framework\Database\ORM\Model;
use Toporia\Framework\Search\Searchable;
use Toporia\Framework\Search\Contracts\SearchableModelInterface;

/**
 * Product ORM Model.
 *
 * Represents a product entity with Active Record pattern.
 *
 * Connection Configuration:
 * - By default, uses the global default connection
 * - Can specify a different connection by setting $connection property
 * - Example: protected static ?string $connection = 'mysql';
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
class ProductModel extends Model implements SearchableModelInterface
{
    use Searchable;

    /**
     * {@inheritdoc}
     */
    protected static string $table = 'products';

    /**
     * Database connection name for this model.
     *
     * Uncomment to use a specific connection:
     * protected static ?string $connection = 'mysql';
     * protected static ?string $connection = 'analytics';
     *
     * If null (default), uses the global default connection.
     *
     * @var string|null
     */
    // protected static ?string $connection = null;

    /**
     * {@inheritdoc}
     */
    protected static array $fillable = [
        'id',
        'title',
        'sku',
        'description',
        'price',
        'stock',
        'parent_id',
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
     * Attributes to hide from array/JSON output.
     *
     * Example: Hide internal tracking fields from API responses
     * Uncomment to hide timestamps:
     *
     * protected static array $hidden = ['created_at', 'updated_at'];
     */
    // protected static array $hidden = [];

    /**
     * Computed attributes to append to array/JSON output.
     *
     * These will automatically call the corresponding accessor methods.
     *
     * Examples:
     * - 'formatted_price': Shows price with currency symbol
     * - 'stock_status': Human-readable stock status
     * - 'availability': Computed availability message
     */
    protected static array $appends = ['formatted_price', 'stock_status'];

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

        // Sync with Elasticsearch (Searchable trait)
        $this->pushToSearch();
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
     * Hook: Called after updated.
     *
     * @return void
     */
    protected function updated(): void
    {
        // Sync with Elasticsearch (Searchable trait)
        $this->pushToSearch();
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

    /**
     * Relationship: Product can have many child products (variants, etc.).
     *
     * @return \Toporia\Framework\Database\ORM\Relations\HasMany
     */
    public function childrens()
    {
        return $this->hasMany(ProductModel::class, 'parent_id', 'id');
    }

    // =========================================================================
    // COMPUTED ATTRIBUTES (Accessors)
    // =========================================================================

    /**
     * Accessor: Get formatted price with currency symbol.
     *
     * This is a computed attribute defined in $appends.
     * Usage: $product->formatted_price or included in toArray()/toJson()
     *
     * SOLID Principles:
     * - Single Responsibility: Only formats price, doesn't modify data
     * - Open/Closed: Add new formats without modifying existing code
     * - Dependency Inversion: Currency symbol could come from config
     *
     * @return string|null Formatted price (e.g., "$99.99") or null if price not loaded
     */
    public function getFormattedPriceAttribute(): ?string
    {
        if ($this->price === null) {
            return null;
        }

        // TODO: Get currency from config or user preferences
        $currency = '$';
        return $currency . number_format($this->price, 2);
    }

    /**
     * Accessor: Get human-readable stock status.
     *
     * This is a computed attribute defined in $appends.
     *
     * @return string|null Stock status message or null if stock not loaded
     */
    public function getStockStatusAttribute(): ?string
    {
        if ($this->stock === null) {
            return null;
        }

        if ($this->stock === 0) {
            return 'Out of Stock';
        } elseif ($this->stock <= 5) {
            return 'Low Stock';
        } elseif ($this->stock <= 20) {
            return 'In Stock';
        } else {
            return 'Well Stocked';
        }
    }

    // =========================================================================
    // ELASTICSEARCH INTEGRATION
    // =========================================================================

    /**
     * {@inheritdoc}
     */
    public static function searchIndexName(): string
    {
        return config('search.indices.products.name', 'products');
    }

    /**
     * {@inheritdoc}
     */
    public function toSearchDocument(): array
    {
        return [
            'id' => (string) $this->id,
            'title' => $this->title ?? '',
            'description' => $this->description ?? '',
            'sku' => $this->sku ?? '',
            'price' => $this->price ?? 0.0,
            'stock' => $this->stock ?? 0,
            'is_active' => $this->is_active ?? false,
            'status' => $this->is_active ? 'active' : 'inactive',
            'created_at' => $this->created_at ?? date('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at ?? date('Y-m-d H:i:s'),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getSearchDocumentId(): string|int
    {
        return $this->id;
    }
}
