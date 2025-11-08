<?php

declare(strict_types=1);

namespace Toporia\Framework\Auth;

/**
 * Guard Interface - Authentication guard contract.
 *
 * Guards are responsible for authenticating users and managing authentication state.
 * Different guards can use different mechanisms (session, token, etc.)
 *
 * Following Interface Segregation Principle - focused, minimal interface.
 */
interface GuardInterface
{
    /**
     * Check if a user is currently authenticated.
     *
     * @return bool True if user is authenticated.
     */
    public function check(): bool;

    /**
     * Check if no user is authenticated (guest).
     *
     * @return bool True if no user is authenticated.
     */
    public function guest(): bool;

    /**
     * Get the currently authenticated user.
     *
     * @return Authenticatable|null Authenticated user or null.
     */
    public function user(): ?Authenticatable;

    /**
     * Get the ID of the currently authenticated user.
     *
     * @return int|string|null User ID or null if not authenticated.
     */
    public function id(): int|string|null;

    /**
     * Attempt to authenticate a user with credentials.
     *
     * @param array<string, mixed> $credentials User credentials (username, password, etc.)
     * @return bool True if authentication successful.
     */
    public function attempt(array $credentials): bool;

    /**
     * Log in a user instance.
     *
     * @param Authenticatable $user User to log in.
     * @return void
     */
    public function login(Authenticatable $user): void;

    /**
     * Log out the currently authenticated user.
     *
     * @return void
     */
    public function logout(): void;

    /**
     * Validate credentials without logging in.
     *
     * @param array<string, mixed> $credentials Credentials to validate.
     * @return bool True if credentials are valid.
     */
    public function validate(array $credentials): bool;
}
