<?php
namespace App\Infrastructure\Persistence;

use App\Domain\Product\ProductRepository;
use App\Domain\Product\Product;

final class InMemoryProductRepository implements ProductRepository
{
    private array $db = [];
    private int $id = 1;

    public function nextId(): int { return $this->id; }

    public function store(Product $product): Product
    {
        $id = $this->id++;
        $clone = new Product($id, $product->title, $product->sku);
        $this->db[$id] = $clone;
        return $clone;
    }

    public function findById(int $id): ?Product
    {
        return $this->db[$id] ?? null;
    }
}
