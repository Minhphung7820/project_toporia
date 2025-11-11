<?php

declare(strict_types=1);

namespace Toporia\Framework\Support\Accessors;

use Toporia\Framework\Routing\UrlGeneratorInterface;

/**
 * Static accessor for URL Generator (Laravel-compatible).
 *
 * Provides static methods for convenient URL generation throughout the application.
 *
 * Example usage:
 * ```php
 * use Toporia\Framework\Support\Accessors\URL;
 *
 * $url = URL::to('/products');
 * $route = URL::route('product.show', ['id' => 1]);
 * $asset = URL::asset('css/app.css');
 * $signed = URL::signedRoute('unsubscribe', ['email' => $email], 3600);
 * ```
 *
 * Performance: O(1) - delegates to singleton UrlGenerator instance
 */
final class URL
{
    /**
     * Generate a URL to a path.
     *
     * @param string $path URL path
     * @param array<string, mixed> $query Query parameters
     * @param bool $absolute Generate absolute URL (default: true)
     * @return string Generated URL
     */
    public static function to(string $path, array $query = [], bool $absolute = true): string
    {
        return self::instance()->to($path, $query, $absolute);
    }

    /**
     * Generate a URL to a named route.
     *
     * @param string $name Route name
     * @param array<string, mixed> $parameters Route parameters
     * @param bool $absolute Generate absolute URL (default: true)
     * @return string Generated URL
     */
    public static function route(string $name, array $parameters = [], bool $absolute = true): string
    {
        return self::instance()->route($name, $parameters, $absolute);
    }

    /**
     * Generate an asset URL.
     *
     * @param string $path Asset path
     * @param bool $absolute Generate absolute URL (default: false)
     * @return string Generated URL
     */
    public static function asset(string $path, bool $absolute = false): string
    {
        return self::instance()->asset($path, $absolute);
    }

    /**
     * Generate a secure asset URL (HTTPS).
     *
     * @param string $path Asset path
     * @return string Generated URL
     */
    public static function secureAsset(string $path): string
    {
        return self::instance()->secureAsset($path);
    }

    /**
     * Generate a signed URL to a named route.
     *
     * @param string $name Route name
     * @param array<string, mixed> $parameters Route parameters
     * @param int|null $expiration Expiration in seconds from now
     * @param bool $absolute Generate absolute URL (default: true)
     * @return string Signed URL
     */
    public static function signedRoute(string $name, array $parameters = [], ?int $expiration = null, bool $absolute = true): string
    {
        return self::instance()->signedRoute($name, $parameters, $expiration, $absolute);
    }

    /**
     * Generate a temporary signed URL to a named route.
     *
     * @param string $name Route name
     * @param int $expiration Expiration in seconds from now
     * @param array<string, mixed> $parameters Route parameters
     * @param bool $absolute Generate absolute URL (default: true)
     * @return string Signed URL
     */
    public static function temporarySignedRoute(string $name, int $expiration, array $parameters = [], bool $absolute = true): string
    {
        return self::instance()->temporarySignedRoute($name, $expiration, $parameters, $absolute);
    }

    /**
     * Verify a signed URL.
     *
     * @param string $url URL to verify
     * @return bool True if valid signature
     */
    public static function hasValidSignature(string $url): bool
    {
        return self::instance()->hasValidSignature($url);
    }

    /**
     * Get the current URL.
     *
     * @return string Current URL
     */
    public static function current(): string
    {
        return self::instance()->current();
    }

    /**
     * Get the previous URL.
     *
     * @param string|null $default Default URL if no previous
     * @return string Previous URL
     */
    public static function previous(?string $default = null): string
    {
        return self::instance()->previous($default);
    }

    /**
     * Get the full URL for the current request with query string.
     *
     * @return string Full URL
     */
    public static function full(): string
    {
        return self::instance()->full();
    }

    /**
     * Set the root URL.
     *
     * @param string $root Root URL (e.g., https://example.com)
     * @return void
     */
    public static function setRootUrl(string $root): void
    {
        self::instance()->setRootUrl($root);
    }

    /**
     * Force the scheme for URLs.
     *
     * @param string $scheme Scheme (http or https)
     * @return void
     */
    public static function forceScheme(string $scheme): void
    {
        self::instance()->forceScheme($scheme);
    }

    /**
     * Get the UrlGenerator instance from the container.
     *
     * @return UrlGeneratorInterface
     */
    private static function instance(): UrlGeneratorInterface
    {
        return app('url');
    }
}
