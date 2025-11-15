<?php

namespace App\Presentation\Http\Action\Product;

use Toporia\Framework\Presentation\Action\AbstractAction;
use Toporia\Framework\Presentation\Responder\AbstractResponder;
use Toporia\Framework\Http\Request;
use Toporia\Framework\Http\Response;
use App\Application\Product\CreateProduct\CreateProductCommand;
use App\Application\Product\CreateProduct\CreateProductHandler;
use App\Infrastructure\Persistence\InMemoryProductRepository;

final class CreateProductAction extends AbstractAction
{
    public function __construct(private AbstractResponder $responder) {}

    protected function handle(Request $request, Response $response, mixed ...$vars): mixed
    {
        $payload = $request->input();

        $cmd = new CreateProductCommand(
            title: (string)($payload['title'] ?? ''),
            sku: $payload['sku'] ?? null,
        );

        $handler = new CreateProductHandler(new InMemoryProductRepository());
        $product = $handler($cmd);

        $this->responder->created($response, [
            'id' => $product->id,
            'title' => $product->title
        ]);

        return $response;
    }
}
