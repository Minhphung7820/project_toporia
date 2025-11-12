<?php

declare(strict_types=1);

namespace Toporia\Framework\Security;

/**
 * XSS Service Wrapper
 *
 * Wraps static XssProtection methods into an instance-based service
 * for dependency injection and ServiceAccessor compatibility.
 *
 * This follows Clean Architecture principles:
 * - Single Responsibility: Only wraps XssProtection static methods
 * - Dependency Inversion: Can be swapped with mock in tests
 * - Interface Segregation: Provides clean service interface
 *
 * Performance:
 * - Zero overhead - direct delegation to static methods
 * - O(1) method calls (no caching needed, static methods are fast)
 * - Memory efficient (no state, just method forwarding)
 *
 * @see XssProtection For detailed method documentation
 */
final class XssService
{
    /**
     * Escape HTML special characters
     *
     * @param string|null $value The value to escape
     * @param bool $doubleEncode Whether to encode existing HTML entities
     * @return string
     */
    public function escape(?string $value, bool $doubleEncode = true): string
    {
        return XssProtection::escape($value, $doubleEncode);
    }

    /**
     * Clean user input by stripping all HTML tags
     *
     * @param string|null $value The value to clean
     * @return string
     */
    public function clean(?string $value): string
    {
        return XssProtection::clean($value);
    }

    /**
     * Sanitize HTML while allowing specific tags
     *
     * @param string|null $value The HTML to sanitize
     * @param string|null $allowedTags Allowed tags (e.g., '<p><a><strong>')
     * @param bool $basicFormatting Use basic formatting tags if $allowedTags is null
     * @return string
     */
    public function sanitize(?string $value, ?string $allowedTags = null, bool $basicFormatting = true): string
    {
        return XssProtection::sanitize($value, $allowedTags, $basicFormatting);
    }

    /**
     * Purify HTML for rich text editors
     *
     * More permissive than sanitize() but still removes dangerous elements.
     *
     * @param string|null $value The HTML to purify
     * @return string
     */
    public function purify(?string $value): string
    {
        return XssProtection::purify($value);
    }

    /**
     * Escape for use in JavaScript strings
     *
     * @param string|null $value The value to escape
     * @return string
     */
    public function escapeJs(?string $value): string
    {
        return XssProtection::escapeJs($value);
    }

    /**
     * Escape for use in URL parameters
     *
     * @param string|null $value The value to escape
     * @return string
     */
    public function escapeUrl(?string $value): string
    {
        return XssProtection::escapeUrl($value);
    }

    /**
     * Clean array of values recursively
     *
     * @param array $data
     * @param bool $stripTags Whether to strip all tags
     * @return array
     */
    public function cleanArray(array $data, bool $stripTags = true): array
    {
        return XssProtection::cleanArray($data, $stripTags);
    }
}
