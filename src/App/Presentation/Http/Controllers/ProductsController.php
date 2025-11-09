<?php

namespace App\Presentation\Http\Controllers;

use App\Application\Product\CreateProduct\CreateProductCommand;
use App\Application\Product\CreateProduct\CreateProductHandler;
use App\Domain\Product\ProductModel;
use App\Infrastructure\Persistence\InMemoryProductRepository;
use Toporia\Framework\Http\Request;

final class ProductsController extends BaseController
{
    public function create(): string
    {
        return $this->view('products/create', ['title' => 'Create Product']);
    }

    public function store(): void
    {
        $payload = $this->request->input();
        $cmd = new CreateProductCommand(
            title: (string)($payload['title'] ?? ''),
            sku: $payload['sku'] ?? null
        );
        // Normally inject via container. For demo: wire quickly.
        $handler = new CreateProductHandler(new InMemoryProductRepository());
        $product = $handler($cmd);
        event('ProductCreated', ['id' => $product->id, 'title' => $product->title]);
        $this->response->json(['message' => 'created', 'data' => ['id' => $product->id, 'title' => $product->title]], 201);
    }

    public function show(Request $request, string $id)
    {
        $product = ProductModel::get()
            ->map(function ($item) {
                $item->add = 1;
                return $item;
            })->toArray();
        return response()->json($product);
    }
}
