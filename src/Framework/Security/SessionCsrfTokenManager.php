<?php

declare(strict_types=1);

namespace Toporia\Framework\Security;

/**
 * Session-based CSRF token manager
 *
 * Stores CSRF tokens in the session for validation.
 * Uses cryptographically secure random tokens.
 */
final class SessionCsrfTokenManager implements CsrfTokenManagerInterface
{
    private const SESSION_KEY_PREFIX = '_csrf_';

    public function generate(string $key = '_token'): string
    {
        $token = $this->generateRandomToken();
        $this->storeToken($key, $token);
        return $token;
    }

    public function validate(string $token, string $key = '_token'): bool
    {
        $storedToken = $this->getToken($key);

        if ($storedToken === null) {
            return false;
        }

        // Use hash_equals to prevent timing attacks
        return hash_equals($storedToken, $token);
    }

    public function regenerate(string $key = '_token'): string
    {
        $this->remove($key);
        return $this->generate($key);
    }

    public function remove(string $key = '_token'): void
    {
        $sessionKey = $this->getSessionKey($key);
        unset($_SESSION[$sessionKey]);
    }

    public function getToken(string $key = '_token'): ?string
    {
        $sessionKey = $this->getSessionKey($key);
        return $_SESSION[$sessionKey] ?? null;
    }

    /**
     * Generate a cryptographically secure random token
     *
     * @return string
     */
    private function generateRandomToken(): string
    {
        return bin2hex(random_bytes(32)); // 64 character hex string
    }

    /**
     * Store token in session
     *
     * @param string $key
     * @param string $token
     * @return void
     */
    private function storeToken(string $key, string $token): void
    {
        $sessionKey = $this->getSessionKey($key);
        $_SESSION[$sessionKey] = $token;
    }

    /**
     * Get the session key for a given token key
     *
     * @param string $key
     * @return string
     */
    private function getSessionKey(string $key): string
    {
        return self::SESSION_KEY_PREFIX . $key;
    }
}
