<?php

declare(strict_types=1);

namespace Toporia\Framework\Hashing\Contracts;

/**
 * Hasher Interface
 *
 * Contract for password hashing implementations.
 * All hashers must implement secure one-way hashing with automatic salting.
 *
 * Security Requirements:
 * - One-way hashing (irreversible)
 * - Automatic salt generation
 * - Timing-safe comparison
 * - Configurable work factor (cost)
 *
 * Performance:
 * - make(): O(1) but slow by design (security > speed)
 * - check(): O(1) but slow by design (prevents timing attacks)
 * - needsRehash(): O(1) - fast string comparison
 *
 * SOLID Principles:
 * - Interface Segregation: Minimal, focused interface
 * - Liskov Substitution: All implementations interchangeable
 * - Dependency Inversion: Depend on abstraction, not concretion
 *
 * @package Toporia\Framework\Hashing\Contracts
 */
interface HasherInterface
{
    /**
     * Hash the given value.
     *
     * Creates a one-way hash with automatic salt generation.
     * The result includes algorithm identifier, cost, salt, and hash.
     *
     * Performance: O(1) but intentionally slow (50-250ms by design)
     * The slowness is a security feature (prevents brute force).
     *
     * @param string $value Plain text value to hash
     * @param array $options Hashing options (cost, memory, time, etc.)
     * @return string Hashed value
     * @throws \RuntimeException If hashing fails
     */
    public function make(string $value, array $options = []): string;

    /**
     * Check the given plain value against a hash.
     *
     * Uses timing-safe comparison to prevent timing attacks.
     *
     * Performance: O(1) - constant time comparison (security feature)
     *
     * @param string $value Plain text value
     * @param string $hashedValue Hashed value
     * @param array $options Additional options
     * @return bool True if match, false otherwise
     */
    public function check(string $value, string $hashedValue, array $options = []): bool;

    /**
     * Check if the given hash has been hashed using the given options.
     *
     * Determines if hash needs to be rehashed with new cost/algorithm.
     * Useful for upgrading security when algorithm improves.
     *
     * Performance: O(1) - fast string parsing
     *
     * @param string $hashedValue Hashed value to check
     * @param array $options Current hashing options
     * @return bool True if rehash needed, false otherwise
     */
    public function needsRehash(string $hashedValue, array $options = []): bool;

    /**
     * Get information about the given hashed value.
     *
     * Returns algorithm name, options (cost, etc.), and other metadata.
     *
     * Performance: O(1) - fast string parsing
     *
     * @param string $hashedValue Hashed value
     * @return array Hash information (algo, options)
     */
    public function info(string $hashedValue): array;
}
