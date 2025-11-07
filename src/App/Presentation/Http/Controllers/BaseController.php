<?php
namespace App\Presentation\Http\Controllers;

use Framework\Http\Request;
use Framework\Http\Response;

abstract class BaseController
{
    public function __construct(protected Request $request, protected Response $response){}

    protected function view(string $path, array $data = []): string
    {
        extract($data);
        ob_start();
        include __DIR__ . '/../../Views/' . $path . '.php';
        return ob_get_clean();
    }
}
