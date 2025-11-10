<?php

declare(strict_types=1);

namespace Toporia\Framework\Support\Accessors;

use Toporia\Framework\Foundation\ServiceAccessor;
use Toporia\Framework\Storage\StorageManager;
use Toporia\Framework\Storage\Contracts\FilesystemInterface;

/**
 * Storage Service Accessor
 *
 * Provides static-like access to the Storage system.
 *
 * Usage:
 * ```php
 * use Toporia\Framework\Support\Accessors\Storage;
 *
 * // Get default disk
 * Storage::put('file.txt', 'content');
 * $content = Storage::get('file.txt');
 *
 * // Get specific disk
 * $s3 = Storage::disk('s3');
 * $s3->put('uploads/photo.jpg', $data);
 *
 * // All FilesystemInterface methods available
 * Storage::exists('file.txt');
 * Storage::delete('file.txt');
 * Storage::files('uploads');
 * ```
 *
 * Performance: Zero overhead - proxies to container service
 * SOLID: Facade pattern over dependency injection
 */
final class Storage extends ServiceAccessor
{
    /**
     * Get the service name in the container.
     *
     * @return string
     */
    protected static function getServiceName(): string
    {
        return 'storage';
    }

    /**
     * Get a filesystem disk instance.
     *
     * @param string|null $name Disk name (null = default)
     * @return FilesystemInterface
     */
    public static function disk(?string $name = null): FilesystemInterface
    {
        /** @var StorageManager $storage */
        $storage = static::getService();
        return $storage->disk($name);
    }

    /**
     * Store file contents.
     *
     * @param string $path File path
     * @param mixed $contents File contents (string or resource)
     * @param array $options Options (visibility, etc.)
     * @return bool
     */
    public static function put(string $path, mixed $contents, array $options = []): bool
    {
        return static::disk()->put($path, $contents, $options);
    }

    /**
     * Get file contents.
     *
     * @param string $path File path
     * @return string|null
     */
    public static function get(string $path): ?string
    {
        return static::disk()->get($path);
    }

    /**
     * Get file as stream resource.
     *
     * @param string $path File path
     * @return resource|null
     */
    public static function readStream(string $path)
    {
        return static::disk()->readStream($path);
    }

    /**
     * Check if file exists.
     *
     * @param string $path File path
     * @return bool
     */
    public static function exists(string $path): bool
    {
        return static::disk()->exists($path);
    }

    /**
     * Delete file(s).
     *
     * @param string|array $paths File path(s)
     * @return bool
     */
    public static function delete(string|array $paths): bool
    {
        return static::disk()->delete($paths);
    }

    /**
     * Copy file.
     *
     * @param string $from Source path
     * @param string $to Destination path
     * @return bool
     */
    public static function copy(string $from, string $to): bool
    {
        return static::disk()->copy($from, $to);
    }

    /**
     * Move file.
     *
     * @param string $from Source path
     * @param string $to Destination path
     * @return bool
     */
    public static function move(string $from, string $to): bool
    {
        return static::disk()->move($from, $to);
    }

    /**
     * Get file size.
     *
     * @param string $path File path
     * @return int|null
     */
    public static function size(string $path): ?int
    {
        return static::disk()->size($path);
    }

    /**
     * Get last modified timestamp.
     *
     * @param string $path File path
     * @return int|null
     */
    public static function lastModified(string $path): ?int
    {
        return static::disk()->lastModified($path);
    }

    /**
     * Get MIME type.
     *
     * @param string $path File path
     * @return string|null
     */
    public static function mimeType(string $path): ?string
    {
        return static::disk()->mimeType($path);
    }

    /**
     * List files in directory.
     *
     * @param string $directory Directory path
     * @param bool $recursive Recursive listing
     * @return array
     */
    public static function files(string $directory = '', bool $recursive = false): array
    {
        return static::disk()->files($directory, $recursive);
    }

    /**
     * List subdirectories.
     *
     * @param string $directory Directory path
     * @param bool $recursive Recursive listing
     * @return array
     */
    public static function directories(string $directory = '', bool $recursive = false): array
    {
        return static::disk()->directories($directory, $recursive);
    }

    /**
     * Create directory.
     *
     * @param string $path Directory path
     * @return bool
     */
    public static function makeDirectory(string $path): bool
    {
        return static::disk()->makeDirectory($path);
    }

    /**
     * Delete directory.
     *
     * @param string $directory Directory path
     * @return bool
     */
    public static function deleteDirectory(string $directory): bool
    {
        return static::disk()->deleteDirectory($directory);
    }

    /**
     * Get public URL.
     *
     * @param string $path File path
     * @return string
     */
    public static function url(string $path): string
    {
        return static::disk()->url($path);
    }

    /**
     * Get temporary URL (signed).
     *
     * @param string $path File path
     * @param int $expiration Expiration in seconds
     * @return string
     */
    public static function temporaryUrl(string $path, int $expiration): string
    {
        return static::disk()->temporaryUrl($path, $expiration);
    }
}
