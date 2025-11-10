<?php

declare(strict_types=1);

namespace Toporia\Framework\Storage;

use Toporia\Framework\Storage\Contracts\FilesystemInterface;

/**
 * Local Filesystem Driver
 *
 * High-performance local disk storage implementation.
 * Laravel-compatible with optimizations.
 *
 * Performance Optimizations:
 * - Direct filesystem calls (no abstraction overhead)
 * - Stream support for large files (memory efficient)
 * - Atomic operations where possible
 *
 * SOLID Principles:
 * - Single Responsibility: Only handles local filesystem operations
 * - Open/Closed: Extend via inheritance
 * - Liskov Substitution: Implements FilesystemInterface contract
 */
final class LocalFilesystem implements FilesystemInterface
{
    public function __construct(
        private readonly string $root,
        private readonly string $baseUrl = ''
    ) {
        // Ensure root directory exists
        if (!is_dir($this->root)) {
            mkdir($this->root, 0755, true);
        }
    }

    public function put(string $path, mixed $contents, array $options = []): bool
    {
        $fullPath = $this->getFullPath($path);

        // Create directory if needed
        $directory = dirname($fullPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        // Handle stream resource
        if (is_resource($contents)) {
            $destination = fopen($fullPath, 'w');
            stream_copy_to_stream($contents, $destination);
            fclose($destination);
            return true;
        }

        // Handle string content
        $result = file_put_contents($fullPath, $contents, LOCK_EX) !== false;

        // Set visibility if specified
        if ($result && isset($options['visibility'])) {
            $this->setVisibility($path, $options['visibility']);
        }

        return $result;
    }

    public function get(string $path): ?string
    {
        $fullPath = $this->getFullPath($path);

        if (!file_exists($fullPath) || !is_file($fullPath)) {
            return null;
        }

        return file_get_contents($fullPath) ?: null;
    }

    public function readStream(string $path)
    {
        $fullPath = $this->getFullPath($path);

        if (!file_exists($fullPath) || !is_file($fullPath)) {
            return null;
        }

        $stream = fopen($fullPath, 'r');
        return $stream !== false ? $stream : null;
    }

    public function exists(string $path): bool
    {
        return file_exists($this->getFullPath($path));
    }

    public function delete(string|array $paths): bool
    {
        $paths = is_array($paths) ? $paths : [$paths];
        $success = true;

        foreach ($paths as $path) {
            $fullPath = $this->getFullPath($path);
            if (file_exists($fullPath) && is_file($fullPath)) {
                $success = unlink($fullPath) && $success;
            }
        }

        return $success;
    }

    public function copy(string $from, string $to): bool
    {
        $fromPath = $this->getFullPath($from);
        $toPath = $this->getFullPath($to);

        if (!file_exists($fromPath)) {
            return false;
        }

        // Create destination directory
        $directory = dirname($toPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        return copy($fromPath, $toPath);
    }

    public function move(string $from, string $to): bool
    {
        $fromPath = $this->getFullPath($from);
        $toPath = $this->getFullPath($to);

        if (!file_exists($fromPath)) {
            return false;
        }

        // Create destination directory
        $directory = dirname($toPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        return rename($fromPath, $toPath);
    }

    public function size(string $path): ?int
    {
        $fullPath = $this->getFullPath($path);

        if (!file_exists($fullPath) || !is_file($fullPath)) {
            return null;
        }

        $size = filesize($fullPath);
        return $size !== false ? $size : null;
    }

    public function lastModified(string $path): ?int
    {
        $fullPath = $this->getFullPath($path);

        if (!file_exists($fullPath)) {
            return null;
        }

        $time = filemtime($fullPath);
        return $time !== false ? $time : null;
    }

    public function mimeType(string $path): ?string
    {
        $fullPath = $this->getFullPath($path);

        if (!file_exists($fullPath) || !is_file($fullPath)) {
            return null;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $fullPath);
        finfo_close($finfo);

        return $mime !== false ? $mime : null;
    }

    public function files(string $directory = '', bool $recursive = false): array
    {
        $fullPath = $this->getFullPath($directory);

        if (!is_dir($fullPath)) {
            return [];
        }

        $files = [];

        if ($recursive) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($fullPath, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $files[] = $this->getRelativePath($file->getPathname());
                }
            }
        } else {
            $items = scandir($fullPath);
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }

                $itemPath = $fullPath . DIRECTORY_SEPARATOR . $item;
                if (is_file($itemPath)) {
                    $files[] = $this->getRelativePath($itemPath);
                }
            }
        }

        return $files;
    }

    public function directories(string $directory = '', bool $recursive = false): array
    {
        $fullPath = $this->getFullPath($directory);

        if (!is_dir($fullPath)) {
            return [];
        }

        $directories = [];

        if ($recursive) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($fullPath, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $file) {
                if ($file->isDir()) {
                    $directories[] = $this->getRelativePath($file->getPathname());
                }
            }
        } else {
            $items = scandir($fullPath);
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }

                $itemPath = $fullPath . DIRECTORY_SEPARATOR . $item;
                if (is_dir($itemPath)) {
                    $directories[] = $this->getRelativePath($itemPath);
                }
            }
        }

        return $directories;
    }

    public function makeDirectory(string $path): bool
    {
        $fullPath = $this->getFullPath($path);

        if (is_dir($fullPath)) {
            return true;
        }

        return mkdir($fullPath, 0755, true);
    }

    public function deleteDirectory(string $directory): bool
    {
        $fullPath = $this->getFullPath($directory);

        if (!is_dir($fullPath)) {
            return false;
        }

        return $this->deleteDirectoryRecursive($fullPath);
    }

    public function url(string $path): string
    {
        return rtrim($this->baseUrl, '/') . '/' . ltrim($path, '/');
    }

    public function temporaryUrl(string $path, int $expiration): string
    {
        // For local filesystem, generate signed URL using HMAC
        $expires = time() + $expiration;
        $signature = hash_hmac('sha256', $path . $expires, 'app-secret');

        return $this->url($path) . '?expires=' . $expires . '&signature=' . $signature;
    }

    /**
     * Set file visibility (permissions).
     *
     * @param string $path File path
     * @param string $visibility 'public' or 'private'
     * @return bool
     */
    public function setVisibility(string $path, string $visibility): bool
    {
        $fullPath = $this->getFullPath($path);

        if (!file_exists($fullPath)) {
            return false;
        }

        $permissions = $visibility === 'public' ? 0644 : 0600;
        return chmod($fullPath, $permissions);
    }

    /**
     * Get full filesystem path.
     *
     * @param string $path Relative path
     * @return string Absolute path
     */
    private function getFullPath(string $path): string
    {
        return $this->root . DIRECTORY_SEPARATOR . ltrim($path, '/\\');
    }

    /**
     * Get relative path from full path.
     *
     * @param string $fullPath Absolute path
     * @return string Relative path
     */
    private function getRelativePath(string $fullPath): string
    {
        return str_replace($this->root . DIRECTORY_SEPARATOR, '', $fullPath);
    }

    /**
     * Delete directory recursively.
     *
     * @param string $directory Directory path
     * @return bool
     */
    private function deleteDirectoryRecursive(string $directory): bool
    {
        if (!is_dir($directory)) {
            return false;
        }

        $items = scandir($directory);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $item;

            if (is_dir($path)) {
                $this->deleteDirectoryRecursive($path);
            } else {
                unlink($path);
            }
        }

        return rmdir($directory);
    }
}
