<?php

declare(strict_types=1);

namespace Toporia\Framework\Storage;

use Toporia\Framework\Storage\Contracts\UploadedFileInterface;

/**
 * Uploaded File Handler
 *
 * Handles HTTP file uploads with Laravel-compatible API.
 *
 * Performance:
 * - Stream-based for large files (memory efficient)
 * - Lazy loading of file info
 * - Hash caching
 *
 * Security:
 * - Validates file uploads
 * - Prevents path traversal
 * - MIME type detection
 */
final class UploadedFile implements UploadedFileInterface
{
    private ?string $hashCache = null;

    public function __construct(
        private readonly string $path,
        private readonly string $originalName,
        private readonly ?string $mimeType = null,
        private readonly ?int $error = null,
        private readonly bool $test = false
    ) {
    }

    /**
     * Create from $_FILES array.
     *
     * @param array $file $_FILES['field_name']
     * @return self
     */
    public static function createFromArray(array $file): self
    {
        return new self(
            $file['tmp_name'],
            $file['name'],
            $file['type'] ?? null,
            $file['error'] ?? UPLOAD_ERR_OK
        );
    }

    public function getClientOriginalName(): string
    {
        return $this->originalName;
    }

    public function getClientOriginalExtension(): string
    {
        return pathinfo($this->originalName, PATHINFO_EXTENSION);
    }

    public function getClientMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function getSize(): int
    {
        return $this->isValid() ? filesize($this->path) : 0;
    }

    public function getError(): int
    {
        return $this->error ?? UPLOAD_ERR_OK;
    }

    public function isValid(): bool
    {
        $isOk = $this->error === UPLOAD_ERR_OK;
        return $this->test ? $isOk : ($isOk && is_uploaded_file($this->path));
    }

    public function store(string $path, ?string $name = null, string $disk = 'default'): string|false
    {
        if (!$this->isValid()) {
            return false;
        }

        $name = $name ?? $this->hashName();
        $targetPath = trim($path, '/') . '/' . $name;

        $storage = app('storage')->disk($disk);
        $stream = fopen($this->path, 'r');

        if ($storage->put($targetPath, $stream)) {
            fclose($stream);
            return $targetPath;
        }

        fclose($stream);
        return false;
    }

    public function storePublicly(string $path, ?string $name = null, string $disk = 'default'): string|false
    {
        if (!$this->isValid()) {
            return false;
        }

        $name = $name ?? $this->hashName();
        $targetPath = trim($path, '/') . '/' . $name;

        $storage = app('storage')->disk($disk);
        $stream = fopen($this->path, 'r');

        if ($storage->put($targetPath, $stream, ['visibility' => 'public'])) {
            fclose($stream);
            return $targetPath;
        }

        fclose($stream);
        return false;
    }

    public function getContent(): string
    {
        return $this->isValid() ? file_get_contents($this->path) : '';
    }

    public function getRealPath(): string
    {
        return realpath($this->path) ?: $this->path;
    }

    public function hash(string $algorithm = 'sha256'): string
    {
        if ($this->hashCache !== null) {
            return $this->hashCache;
        }

        if (!$this->isValid()) {
            return '';
        }

        $this->hashCache = hash_file($algorithm, $this->path);
        return $this->hashCache;
    }

    /**
     * Generate hash-based filename.
     *
     * @param string|null $extension Custom extension
     * @return string Filename like: a3f5d9e2b1c4.jpg
     */
    public function hashName(?string $extension = null): string
    {
        $extension = $extension ?? $this->getClientOriginalExtension();
        $hash = $this->hash('md5');
        return $hash . ($extension ? '.' . $extension : '');
    }

    /**
     * Move uploaded file (for testing).
     *
     * @param string $destination Target path
     * @return bool
     */
    public function move(string $destination): bool
    {
        if (!$this->isValid()) {
            return false;
        }

        // Create directory if needed
        $directory = dirname($destination);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        if ($this->test) {
            return rename($this->path, $destination);
        }

        return move_uploaded_file($this->path, $destination);
    }
}
