<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Product\ProductRepository;
use App\Domain\Product\Product;
use App\Infrastructure\Persistence\Models\ProductModel;

/**
 * Eloquent Product Repository Implementation.
 *
 * Clean Architecture:
 * - This is the Infrastructure layer implementation
 * - Uses ProductModel (Active Record) for database operations
 * - Converts between Domain Entity (Product) and ORM Model (ProductModel)
 * - Implements ProductRepository interface from Domain layer
 */
final class EloquentProductRepository implements ProductRepository
{
    /**
     * Get next available ID.
     *
     * @return int
     */
    public function nextId(): int
    {
        /** @var ProductModel|null $lastProduct */
        $lastProduct = ProductModel::query()
            ->orderBy('id', 'DESC')
            ->first();

        return $lastProduct !== null ? (int)$lastProduct->id + 1 : 1;
    }

    /**
     * Store a product.
     *
     * Converts Domain Entity -> ORM Model -> Save -> Domain Entity
     *
     * @param Product $product Domain entity
     * @return Product Persisted domain entity with ID
     */
    public function store(Product $product): Product
    {
        // Convert Domain Entity to ORM Model
        $model = ProductModel::create([
            'title' => $product->title,
            'sku' => $product->sku,
        ]);

        // Convert ORM Model back to Domain Entity
        return new Product(
            id: $model->id,
            title: $model->title,
            sku: $model->sku
        );
    }

    /**
     * Find product by ID.
     *
     * Converts ORM Model -> Domain Entity
     *
     * @param int $id Product ID
     * @return Product|null Domain entity or null if not found
     */
    public function findById(int $id): ?Product
    {
        $model = ProductModel::find($id);

        if (!$model) {
            return null;
        }

        // Convert ORM Model to Domain Entity
        return new Product(
            id: $model->id,
            title: $model->title,
            sku: $model->sku
        );
    }
}
