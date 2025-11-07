<?php
namespace App\Application\Product\CreateProduct;

use App\Domain\Product\Product;
use App\Domain\Product\ProductRepository;

final class CreateProductHandler
{
    public function __construct(private ProductRepository $repo) {}

    public function __invoke(CreateProductCommand $cmd): Product
    {
        $product = new Product(null, $cmd->title, $cmd->sku);
        return $this->repo->store($product);
    }
}
