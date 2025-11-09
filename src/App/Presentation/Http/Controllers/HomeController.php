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
        // Option 1: Use default connection
        $products =  ProductModel::with(['childrens:id,title,parent_id'])->paginate(12);

        // Option 2: Use specific connection (switch to different database)
        // $products = DB::connection('mysql')->table('products')->get();
        // $analytics = DB::connection('analytics')->table('events')->get();

        return $this->response->json([
            'products' => $products
        ]);
    }

    public function dashboard(): string
    {
        return $this->view('home/index', ['user' => auth()->user()]);
    }
}
