<?php

declare(strict_types=1);

namespace Toporia\Framework\Support\Accessors;

use Toporia\Framework\Hashing\HashManager;
use Toporia\Framework\Hashing\Contracts\HasherInterface;

/**
 * Hash Accessor (Facade)
 *
 * Static accessor for HashManager providing Laravel-style API.
 * Enables static method calls for convenient hashing operations.
 *
 * Usage:
 * ```php
 * use Toporia\Framework\Support\Accessors\Hash;
 *
 * // Hash password
 * $hash = Hash::make('secret');
 *
 * // Verify password
 * if (Hash::check('secret', $hash)) {
 *     // Password correct
 * }
 *
 * // Check if needs rehash
 * if (Hash::needsRehash($hash)) {
 *     $newHash = Hash::make('secret');
 * }
 * ```
 *
 * Performance:
 * - O(1) instance resolution (singleton)
 * - Same performance as direct HashManager usage
 * - No overhead from static calls
 *
 * @method static string make(string $value, array $options = [])
 * @method static bool check(string $value, string $hashedValue, array $options = [])
 * @method static bool needsRehash(string $hashedValue, array $options = [])
 * @method static array info(string $hashedValue)
 * @method static bool isHashed(string $value)
 * @method static HasherInterface driver(?string $name = null)
 * @method static string getDefaultDriver()
 * @method static array getAvailableDrivers()
 *
 * @package Toporia\Framework\Support\Accessors
 */
final class Hash
{
    /**
     * Cached HashManager instance.
     *
     * @var HashManager|null
     */
    private static ?HashManager $instance = null;

    /**
     * Get HashManager instance.
     *
     * Uses singleton pattern for performance.
     *
     * @return HashManager
     */
    private static function getInstance(): HashManager
    {
        if (self::$instance === null) {
            self::$instance = app('hash');
        }

        return self::$instance;
    }

    /**
     * Forward static calls to HashManager instance.
     *
     * @param string $method Method name
     * @param array $arguments Method arguments
     * @return mixed
     */
    public static function __callStatic(string $method, array $arguments): mixed
    {
        return self::getInstance()->$method(...$arguments);
    }

    /**
     * Reset cached instance (for testing).
     *
     * @internal
     * @return void
     */
    public static function clearResolvedInstance(): void
    {
        self::$instance = null;
    }
}
