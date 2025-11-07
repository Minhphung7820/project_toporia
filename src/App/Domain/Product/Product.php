<?php
namespace App\Domain\Product;

final class Product
{
    public function __construct(
        public readonly ?int $id,
        public string $title,
        public ?string $sku,
    ) {}
}
