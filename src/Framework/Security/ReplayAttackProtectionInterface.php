<?php

declare(strict_types=1);

namespace Toporia\Framework\Security;

/**
 * Replay Attack Protection Interface
 *
 * Provides methods to prevent replay attacks by:
 * - Generating unique nonces (Number Used Once)
 * - Validating nonces with timestamp
 * - Tracking used nonces to prevent reuse
 *
 * Clean Architecture:
 * - Interface-based design for testability
 * - Dependency Inversion: Depend on abstraction
 */
interface ReplayAttackProtectionInterface
{
    /**
     * Generate a new nonce with timestamp.
     *
     * Performance: O(1) - Direct token generation
     *
     * @param int $ttl Time-to-live in seconds (default: 300 = 5 minutes)
     * @return string Nonce token (format: timestamp:random_token)
     */
    public function generateNonce(int $ttl = 300): string;

    /**
     * Validate a nonce and mark it as used.
     *
     * Checks:
     * 1. Nonce format is valid
     * 2. Nonce hasn't expired (timestamp check)
     * 3. Nonce hasn't been used before
     *
     * Performance: O(1) - Cache/session lookup
     *
     * @param string $nonce The nonce to validate
     * @return bool True if valid and not used, false otherwise
     */
    public function validateNonce(string $nonce): bool;

    /**
     * Check if a nonce is valid without consuming it.
     *
     * Useful for checking nonce validity before processing.
     *
     * Performance: O(1) - Cache/session lookup
     *
     * @param string $nonce The nonce to check
     * @return bool True if valid, false otherwise
     */
    public function isValidNonce(string $nonce): bool;

    /**
     * Clean up expired nonces.
     *
     * Removes nonces that have expired from storage.
     * Should be called periodically (e.g., via scheduled task).
     *
     * Performance: O(N) where N = number of stored nonces
     *
     * @return int Number of nonces cleaned up
     */
    public function cleanupExpired(): int;
}
