<?php

namespace App\Infrastructure\Auth;

final class SessionAuth
{
    private string $key = 'user';

    public function check(): bool
    {
        return isset($_SESSION[$this->key]);
    }
    public function user(): ?array
    {
        return $_SESSION[$this->key] ?? null;
    }
    public function login(array $user): void
    {
        $_SESSION[$this->key] = $user;
    }
    public function logout(): void
    {
        unset($_SESSION[$this->key]);
    }
}
