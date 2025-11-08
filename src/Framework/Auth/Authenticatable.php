<?php

declare(strict_types=1);

namespace Toporia\Framework\Auth;

/**
 * Authenticatable Interface - Contract for user authentication.
 *
 * Any entity that can be authenticated must implement this interface.
 * This allows flexibility - users can come from database, LDAP, API, etc.
 *
 * Following Interface Segregation Principle.
 */
interface Authenticatable
{
    /**
     * Get the unique identifier for the user.
     *
     * @return int|string User ID.
     */
    public function getAuthIdentifier(): int|string;

    /**
     * Get the name of the unique identifier (e.g., 'id', 'user_id').
     *
     * @return string Identifier name.
     */
    public function getAuthIdentifierName(): string;

    /**
     * Get the password for the user (hashed).
     *
     * @return string Hashed password.
     */
    public function getAuthPassword(): string;

    /**
     * Get the remember token (for "remember me" functionality).
     *
     * @return string|null Remember token.
     */
    public function getRememberToken(): ?string;

    /**
     * Set the remember token.
     *
     * @param string $token Remember token.
     * @return void
     */
    public function setRememberToken(string $token): void;
}
