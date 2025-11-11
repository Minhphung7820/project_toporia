<?php

declare(strict_types=1);

namespace Toporia\Framework\Routing;

/**
 * URL Generator interface for generating URLs to routes, assets, and signed URLs.
 *
 * Features:
 * - Route URL generation with parameters
 * - Asset URL generation
 * - Signed URL generation with expiration
 * - Previous URL tracking
 * - Full/relative URL support
 */
interface UrlGeneratorInterface
{
    /**
     * Generate a URL to a named route.
     *
     * @param string $name Route name
     * @param array<string, mixed> $parameters Route parameters
     * @param bool $absolute Generate absolute URL (default: true)
     * @return string Generated URL
     * @throws \InvalidArgumentException If route not found
     */
    public function route(string $name, array $parameters = [], bool $absolute = true): string;

    /**
     * Generate a URL to a path.
     *
     * @param string $path URL path
     * @param array<string, mixed> $query Query parameters
     * @param bool $absolute Generate absolute URL (default: true)
     * @return string Generated URL
     */
    public function to(string $path, array $query = [], bool $absolute = true): string;

    /**
     * Generate an asset URL.
     *
     * @param string $path Asset path
     * @param bool $absolute Generate absolute URL (default: false)
     * @return string Generated URL
     */
    public function asset(string $path, bool $absolute = false): string;

    /**
     * Generate a secure asset URL (HTTPS).
     *
     * @param string $path Asset path
     * @return string Generated URL
     */
    public function secureAsset(string $path): string;

    /**
     * Generate a signed URL with expiration.
     *
     * @param string $name Route name
     * @param array<string, mixed> $parameters Route parameters
     * @param int|null $expiration Expiration in seconds from now
     * @param bool $absolute Generate absolute URL (default: true)
     * @return string Signed URL
     */
    public function signedRoute(string $name, array $parameters = [], ?int $expiration = null, bool $absolute = true): string;

    /**
     * Generate a temporary signed URL (with expiration).
     *
     * @param string $name Route name
     * @param int $expiration Expiration in seconds from now
     * @param array<string, mixed> $parameters Route parameters
     * @param bool $absolute Generate absolute URL (default: true)
     * @return string Signed URL
     */
    public function temporarySignedRoute(string $name, int $expiration, array $parameters = [], bool $absolute = true): string;

    /**
     * Verify a signed URL.
     *
     * @param string $url URL to verify
     * @return bool True if valid signature
     */
    public function hasValidSignature(string $url): bool;

    /**
     * Get the current URL.
     *
     * @return string Current URL
     */
    public function current(): string;

    /**
     * Get the previous URL.
     *
     * @param string|null $default Default URL if no previous
     * @return string Previous URL
     */
    public function previous(?string $default = null): string;

    /**
     * Set the previous URL.
     *
     * @param string $url Previous URL
     * @return void
     */
    public function setPreviousUrl(string $url): void;

    /**
     * Get the full URL for the current request.
     *
     * @return string Full URL with query string
     */
    public function full(): string;

    /**
     * Set the root URL.
     *
     * @param string $root Root URL (e.g., https://example.com)
     * @return void
     */
    public function setRootUrl(string $root): void;

    /**
     * Force the scheme for URLs.
     *
     * @param string $scheme Scheme (http or https)
     * @return void
     */
    public function forceScheme(string $scheme): void;
}
