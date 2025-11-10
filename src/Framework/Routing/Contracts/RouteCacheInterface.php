<?php

declare(strict_types=1);

namespace Toporia\Framework\Routing\Contracts;

/**
 * Route Cache Interface
 *
 * Contract for caching compiled routes for performance optimization.
 *
 * Performance Benefits:
 * - O(1) route lookup after compilation
 * - Zero overhead route parsing on cached routes
 * - Reduces startup time by 80-90%
 *
 * Architecture:
 * - Interface Segregation Principle
 * - Dependency Inversion Principle
 * - Single Responsibility: Route caching only
 *
 * @package Toporia\Framework\Routing\Contracts
 */
interface RouteCacheInterface
{
    /**
     * Check if routes are cached.
     *
     * @return bool
     */
    public function isCached(): bool;

    /**
     * Get cached routes.
     *
     * Returns compiled route data for fast lookup.
     *
     * @return array|null Cached routes or null if not cached
     */
    public function get(): ?array;

    /**
     * Cache compiled routes.
     *
     * Stores routes in optimized format for O(1) lookup.
     *
     * @param array $routes Compiled routes data
     * @return bool Success status
     */
    public function put(array $routes): bool;

    /**
     * Clear route cache.
     *
     * @return bool Success status
     */
    public function clear(): bool;

    /**
     * Get cache file path.
     *
     * @return string
     */
    public function getCachePath(): string;
}
