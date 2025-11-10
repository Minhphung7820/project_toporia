<?php

declare(strict_types=1);

namespace Toporia\Framework\Hashing;

use Toporia\Framework\Hashing\Contracts\HasherInterface;

/**
 * Bcrypt Hasher
 *
 * Bcrypt implementation using PHP's password_hash() with PASSWORD_BCRYPT.
 * Industry standard, battle-tested, widely supported.
 *
 * Algorithm: bcrypt (Blowfish cipher)
 * Cost: 4-31 (default: 12)
 * Output: 60 characters
 * Format: $2y$[cost]$[salt][hash]
 *
 * Security Features:
 * - Automatic salt generation (128-bit)
 * - Adaptive cost (configurable work factor)
 * - Timing-safe comparison
 * - Truncates at 72 characters (bcrypt limitation)
 *
 * Performance Characteristics:
 * - Cost 10: ~100ms per hash
 * - Cost 12: ~250ms per hash (recommended)
 * - Cost 14: ~1000ms per hash
 * - Higher cost = more secure but slower
 *
 * When to Use:
 * - Default choice for most applications
 * - Maximum compatibility (PHP 5.5+)
 * - Standard web application passwords
 *
 * @package Toporia\Framework\Hashing
 */
final class BcryptHasher implements HasherInterface
{
    /**
     * Default bcrypt cost factor.
     * Recommended: 12 (good balance of security and performance)
     */
    private const DEFAULT_COST = 12;

    /**
     * Minimum allowed cost.
     */
    private const MIN_COST = 4;

    /**
     * Maximum allowed cost.
     */
    private const MAX_COST = 31;

    /**
     * @param int $cost Default cost factor (4-31)
     */
    public function __construct(
        private readonly int $cost = self::DEFAULT_COST
    ) {
        $this->validateCost($cost);
    }

    /**
     * {@inheritdoc}
     */
    public function make(string $value, array $options = []): string
    {
        $cost = $options['cost'] ?? $this->cost;
        $this->validateCost($cost);

        // Hash using bcrypt algorithm
        $hash = password_hash($value, PASSWORD_BCRYPT, [
            'cost' => $cost,
        ]);

        if ($hash === false) {
            throw new \RuntimeException('Bcrypt hashing failed');
        }

        return $hash;
    }

    /**
     * {@inheritdoc}
     *
     * Uses password_verify() which is timing-safe.
     */
    public function check(string $value, string $hashedValue, array $options = []): bool
    {
        if (strlen($hashedValue) === 0) {
            return false;
        }

        // Timing-safe comparison
        return password_verify($value, $hashedValue);
    }

    /**
     * {@inheritdoc}
     */
    public function needsRehash(string $hashedValue, array $options = []): bool
    {
        $cost = $options['cost'] ?? $this->cost;
        $this->validateCost($cost);

        return password_needs_rehash($hashedValue, PASSWORD_BCRYPT, [
            'cost' => $cost,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function info(string $hashedValue): array
    {
        $info = password_get_info($hashedValue);

        return [
            'algo' => $this->getAlgorithmName($info['algo']),
            'algoName' => $info['algoName'] ?? 'unknown',
            'options' => $info['options'] ?? [],
        ];
    }

    /**
     * Validate cost parameter.
     *
     * @param int $cost Cost factor
     * @return void
     * @throws \InvalidArgumentException If cost invalid
     */
    private function validateCost(int $cost): void
    {
        if ($cost < self::MIN_COST || $cost > self::MAX_COST) {
            throw new \InvalidArgumentException(
                "Bcrypt cost must be between " . self::MIN_COST . " and " . self::MAX_COST .
                    ", {$cost} given"
            );
        }
    }

    /**
     * Get algorithm name from constant.
     *
     * @param int|string $algo Algorithm constant
     * @return string Algorithm name
     */
    private function getAlgorithmName(int|string $algo): string
    {
        return match ($algo) {
            PASSWORD_BCRYPT, '2y' => 'bcrypt',
            PASSWORD_ARGON2I, 'argon2i' => 'argon2i',
            PASSWORD_ARGON2ID, 'argon2id' => 'argon2id',
            default => 'unknown'
        };
    }

    /**
     * Set default cost.
     *
     * Useful for testing or adjusting security level.
     *
     * @param int $cost Cost factor
     * @return static
     */
    public function setDefaultCost(int $cost): static
    {
        $this->validateCost($cost);
        return new static($cost);
    }
}
