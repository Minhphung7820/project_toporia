<?php

namespace App\Presentation\Http\Controllers;

use App\Domain\Product\ProductModel;
use Toporia\Framework\Support\Accessors\Auth;
use Toporia\Framework\Support\Accessors\DB;
use Toporia\Framework\Support\Result;
use Toporia\Framework\Support\Str;

final class HomeController extends BaseController
{
    public function index()
    {
        // ========================================================================
        // RELATIONSHIP QUERY METHODS - Clean Architecture, SOLID, High Reusability
        // ========================================================================

        // 1. Basic eager loading
        // $products = ProductModel::with('childrens')->paginate(12);

        // 2. Eager loading with column selection
        // $products = ProductModel::with('childrens:id,title,parent_id,price')->paginate(12);

        // 3. Eager loading with callback (array syntax)
        // $products = ProductModel::with(['childrens' => function ($q) {
        //     $q->where('is_active', 1)->orderBy('price', 'DESC');
        // }])->paginate(12);

        // 4. Filter by relationship existence
        // $products = ProductModel::query()->whereHas('childrens')->paginate(12);

        // 5. Filter by relationship with constraints
        // $products = ProductModel::query()
        //     ->whereHas('childrens', function ($q) {
        //         $q->where('is_active', 1);
        //     })
        //     ->paginate(12);

        // 6. Count related models (all)
        // $products = ProductModel::query()->withCount('childrens')->paginate(12);

        // 7. Count with constraints (only count active children)
        // $products = ProductModel::query()
        //     ->withCount(['childrens' => function ($q) {
        //         $q->where('is_active', 1);
        //     }])
        //     ->paginate(12);

        // 8. Combined: Only products WITH active children + count them
        $products = ProductModel::query()
            ->whereHas('childrens', function ($q) {
                $q->where('is_active', 1);
            })
            ->withCount(['childrens' => function ($q) {
                $q->where('is_active', 1);
            }])
            ->paginate(12);

        return $this->response->json([
            'products' => $products
        ]);
    }

    public function dashboard(): string
    {
        return $this->view('home/index', ['user' => auth()->user()]);
    }
}
