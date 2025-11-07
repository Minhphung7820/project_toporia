<?php
namespace App\Application\Product\CreateProduct;

final class CreateProductCommand
{
    public function __construct(
        public string $title,
        public ?string $sku
    ) {}
}
