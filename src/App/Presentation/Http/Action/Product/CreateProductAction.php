<?php

namespace App\Presentation\Http\Action\Product;

use Framework\Presentation\Action\AbstractAction;
use Framework\Presentation\Responder\AbstractResponder;
use Framework\Http\Request;
use Framework\Http\Response;
use App\Application\Product\CreateProduct\CreateProductCommand;
use App\Application\Product\CreateProduct\CreateProductHandler;
use App\Infrastructure\Persistence\InMemoryProductRepository;

final class CreateProductAction extends AbstractAction
{
    public function __construct(private AbstractResponder $responder) {}

    protected function handle(Request $request, Response $response, ...$vars)
    {
        $payload = $request->input();

        $cmd = new CreateProductCommand(
            title: (string)($payload['title'] ?? ''),
            sku: $payload['sku'] ?? null,
        );

        $handler = new CreateProductHandler(new InMemoryProductRepository());
        $product = $handler($cmd);

        return $this->responder->jsonCreated($response, [
            'id' => $product->id,
            'title' => $product->title
        ]);
    }
}
