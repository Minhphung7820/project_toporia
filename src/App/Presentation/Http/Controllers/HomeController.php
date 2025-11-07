<?php

namespace App\Presentation\Http\Controllers;

final class HomeController extends BaseController
{
    public function index()
    {
        echo "dà";
    }
    public function dashboard(): string
    {
        return $this->view('home/index', ['user' => auth()->user()]);
    }
}
