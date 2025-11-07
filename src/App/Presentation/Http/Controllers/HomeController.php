<?php
namespace App\Presentation\Http\Controllers;

final class HomeController extends BaseController
{
    public function index(): string
    {
        return $this->view('home/index', ['user' => auth()->user()]);
    }
    public function dashboard(): string
    {
        return $this->view('home/index', ['user' => auth()->user()]);
    }
}
