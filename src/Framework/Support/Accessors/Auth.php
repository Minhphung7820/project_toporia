<?php

declare(strict_types=1);

namespace Toporia\Framework\Support\Accessors;

use Toporia\Framework\Foundation\ServiceAccessor;
use Toporia\Framework\Auth\AuthManagerInterface;
use Toporia\Framework\Auth\GuardInterface;
use Toporia\Framework\Auth\Authenticatable;

/**
 * Auth Service Accessor
 *
 * Provides static-like access to the authentication manager.
 *
 * @method static GuardInterface guard(?string $name = null) Get guard instance
 * @method static void setDefaultGuard(string $name) Set default guard
 * @method static string getDefaultGuard() Get default guard name
 * @method static bool hasGuard(string $name) Check if guard exists
 *
 * @see AuthManagerInterface
 *
 * @example
 * // Check if user is authenticated
 * if (Auth::guard()->check()) {
 *     $user = Auth::guard()->user();
 * }
 *
 * // Attempt login
 * if (Auth::guard()->attempt(['email' => $email, 'password' => $password])) {
 *     // Success
 * }
 *
 * // Logout
 * Auth::guard()->logout();
 *
 * // Use specific guard
 * $token = Auth::guard('api')->user();
 */
final class Auth extends ServiceAccessor
{
    protected static function getServiceName(): string
    {
        return 'auth';
    }

    /**
     * Check if user is authenticated (shortcut).
     *
     * @return bool
     */
    public static function check(): bool
    {
        return static::guard()->check();
    }

    /**
     * Check if user is guest (shortcut).
     *
     * @return bool
     */
    public static function guest(): bool
    {
        return static::guard()->guest();
    }

    /**
     * Get authenticated user (shortcut).
     *
     * @return Authenticatable|null
     */
    public static function user(): ?Authenticatable
    {
        return static::guard()->user();
    }

    /**
     * Get authenticated user ID (shortcut).
     *
     * @return int|string|null
     */
    public static function id(): int|string|null
    {
        return static::guard()->id();
    }

    /**
     * Attempt authentication (shortcut).
     *
     * @param array<string, mixed> $credentials
     * @return bool
     */
    public static function attempt(array $credentials): bool
    {
        return static::guard()->attempt($credentials);
    }

    /**
     * Login user (shortcut).
     *
     * @param Authenticatable $user
     * @return void
     */
    public static function login(Authenticatable $user): void
    {
        static::guard()->login($user);
    }

    /**
     * Logout user (shortcut).
     *
     * @return void
     */
    public static function logout(): void
    {
        static::guard()->logout();
    }
}
