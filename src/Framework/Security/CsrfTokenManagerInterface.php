<?php

declare(strict_types=1);

namespace Toporia\Framework\Security;

/**
 * Interface for CSRF token management
 *
 * Provides methods for generating, validating and managing CSRF tokens
 * to prevent Cross-Site Request Forgery attacks.
 */
interface CsrfTokenManagerInterface
{
    /**
     * Generate a new CSRF token
     *
     * @param string $key Token identifier (e.g., form name)
     * @return string The generated token
     */
    public function generate(string $key = '_token'): string;

    /**
     * Validate a CSRF token
     *
     * @param string $token The token to validate
     * @param string $key Token identifier
     * @return bool True if valid, false otherwise
     */
    public function validate(string $token, string $key = '_token'): bool;

    /**
     * Regenerate the CSRF token
     *
     * @param string $key Token identifier
     * @return string The new token
     */
    public function regenerate(string $key = '_token'): string;

    /**
     * Remove a CSRF token
     *
     * @param string $key Token identifier
     * @return void
     */
    public function remove(string $key = '_token'): void;

    /**
     * Get the current token without generating a new one
     *
     * @param string $key Token identifier
     * @return string|null The token or null if not exists
     */
    public function getToken(string $key = '_token'): ?string;
}
