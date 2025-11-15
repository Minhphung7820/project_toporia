<?php

namespace App\Domain\Product;

interface ProductRepository
{
    public function nextId(): int;
    public function store(Product $product): Product;
    public function findById(int $id): ?Product;
}
