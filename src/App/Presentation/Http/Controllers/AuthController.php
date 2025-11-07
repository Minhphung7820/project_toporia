<?php
namespace App\Presentation\Http\Controllers;

final class AuthController extends BaseController
{
    public function showLogin(): string
    {
        return $this->view('auth/login', ['title' => 'Login']);
    }
    public function login(): void
    {
        $email = $this->request->input('email');
        if (!$email) {
            $this->response->html('<p>Missing email</p>', 422); return;
        }
        auth()->login(['id' => 1, 'email' => $email, 'name' => 'Demo User']);
        event('UserLoggedIn', ['email' => $email]);
        $this->response->html('<p>Logged in. Go to <a href="/dashboard">Dashboard</a></p>');
    }
    public function logout(): void
    {
        auth()->logout();
        $this->response->html('<p>Logged out. <a href="/">Home</a></p>');
    }
}
