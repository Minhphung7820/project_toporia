<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers;

use App\Domain\Product\ProductModel;
use Toporia\Framework\Http\Request;
use Toporia\Framework\Http\Response;

/**
 * Home Controller
 *
 * Demo: Modern approach WITHOUT extending BaseController.
 * Uses method injection + helper functions for maximum flexibility.
 */
final class HomeController
{
    use ControllerHelpers; // Optional: adds view(), json(), etc. helper methods

    /**
     * Product listing with pagination.
     *
     * Demo: Request injection (Laravel-style) + helper methods from trait
     */
    public function index(Request $request)
    {

        $products = ProductModel::query()
            ->where(function ($q) {
                $q->where('stock', '>', 0);
                $q->where('is_active', 0);
            })
            ->paginate(12);

        // Using trait helper method
        return $this->json([
            'products' => $products,
            'request_path' => $request->path(),
            'method' => $request->method()
        ]);
    }

    /**
     * Dashboard view.
     *
     * Demo: Using trait's view() method + helper functions
     */
    public function dashboard()
    {
        $user = auth()->user();

        // Using trait helper method
        return $this->view('home/index', [
            'user' => $user,
            'path' => request()->path()
        ]);
    }

    /**
     * API endpoint example.
     *
     * Demo: Pure method injection, no trait needed
     */
    public function api(Request $request, Response $response)
    {
        return $response->json([
            'message' => 'Hello from API',
            'query' => $request->query('search'),
        ]);
    }
}
