<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers;

use App\Application\Product\CreateProduct\CreateProductCommand;
use App\Application\Product\CreateProduct\CreateProductHandler;
use App\Domain\Product\ProductRepository;
use App\Infrastructure\Persistence\Models\ProductModel;
use Toporia\Framework\Http\Request;

/**
 * Products Controller (Presentation Layer).
 *
 * Clean Architecture Compliance:
 * - Controllers belong to Presentation layer
 * - Should use Application layer (Handlers) for business logic
 * - Should inject dependencies via constructor (not manual instantiation)
 * - Should NOT access Models directly (use Repository pattern)
 */
final class ProductsController extends BaseController
{
    /**
     * Constructor with dependency injection.
     *
     * @param CreateProductHandler $createHandler
     * @param ProductRepository $productRepository
     */
    public function __construct(
        private readonly CreateProductHandler $createHandler,
        private readonly ProductRepository $productRepository
    ) {
        parent::__construct();
    }

    /**
     * Show create product form.
     *
     * @return string
     */
    public function create(): string
    {
        return $this->view('products/create', ['title' => 'Create Product']);
    }

    /**
     * Store a new product.
     *
     * Clean Architecture: Controller -> Handler -> Repository -> Domain
     *
     * @return void
     */
    public function store(): void
    {
        $payload = $this->request->input();

        // Create command (Application layer DTO)
        $cmd = new CreateProductCommand(
            title: (string)($payload['title'] ?? ''),
            sku: $payload['sku'] ?? null
        );

        // Execute via handler (injected via DI, not manual instantiation)
        $product = ($this->createHandler)($cmd);

        // Fire event
        event('ProductCreated', ['id' => $product->id, 'title' => $product->title]);

        // Return response
        $this->response->json([
            'message' => 'created',
            'data' => [
                'id' => $product->id,
                'title' => $product->title
            ]
        ], 201);
    }

    /**
     * Show a product by ID.
     *
     * Note: This method still uses ProductModel directly for demonstration.
     * In a strict Clean Architecture, this should use a Query/Handler pattern.
     *
     * @param Request $request
     * @param string $id
     * @return mixed
     */
    public function show(Request $request, string $id)
    {
        // TODO: Refactor to use GetProductQuery/Handler pattern
        // For now, using Model directly for backward compatibility
        $product = ProductModel::get()
            ->map(function ($item) {
                $item->add = 1;
                return $item;
            })->toArray();

        return response()->json($product);
    }
}
