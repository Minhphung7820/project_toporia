<?php

declare(strict_types=1);

namespace Toporia\Framework\Http;

/**
 * Cookie Jar
 *
 * Manages HTTP cookies with encryption support.
 * Provides a fluent interface for creating and managing cookies.
 */
final class CookieJar
{
    private array $queued = [];
    private ?string $encryptionKey = null;

    public function __construct(?string $encryptionKey = null)
    {
        $this->encryptionKey = $encryptionKey;
    }

    /**
     * Get a cookie value
     *
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    public function get(string $name, mixed $default = null): mixed
    {
        if (!isset($_COOKIE[$name])) {
            return $default;
        }

        $value = $_COOKIE[$name];

        // Decrypt if encryption is enabled
        if ($this->encryptionKey !== null) {
            $value = $this->decrypt($value);
        }

        return $value;
    }

    /**
     * Check if a cookie exists
     *
     * @param string $name
     * @return bool
     */
    public function has(string $name): bool
    {
        return isset($_COOKIE[$name]);
    }

    /**
     * Queue a cookie to be sent
     *
     * @param Cookie $cookie
     * @return self
     */
    public function queue(Cookie $cookie): self
    {
        $this->queued[$cookie->name] = $cookie;
        return $this;
    }

    /**
     * Create and queue a cookie
     *
     * @param string $name
     * @param string $value
     * @param int $minutes
     * @param array $options
     * @return self
     */
    public function make(string $name, string $value, int $minutes = 60, array $options = []): self
    {
        // Encrypt value if encryption is enabled
        if ($this->encryptionKey !== null) {
            $value = $this->encrypt($value);
        }

        $cookie = Cookie::make($name, $value, $minutes, $options);
        return $this->queue($cookie);
    }

    /**
     * Create and queue a cookie that lasts forever
     *
     * @param string $name
     * @param string $value
     * @param array $options
     * @return self
     */
    public function forever(string $name, string $value, array $options = []): self
    {
        return $this->make($name, $value, 60 * 24 * 365 * 5, $options);
    }

    /**
     * Queue a cookie for deletion
     *
     * @param string $name
     * @param array $options
     * @return self
     */
    public function forget(string $name, array $options = []): self
    {
        $cookie = Cookie::forget($name, $options);
        return $this->queue($cookie);
    }

    /**
     * Send all queued cookies
     *
     * @return void
     */
    public function sendQueued(): void
    {
        foreach ($this->queued as $cookie) {
            $cookie->send();
        }

        $this->queued = [];
    }

    /**
     * Get all queued cookies
     *
     * @return array
     */
    public function getQueued(): array
    {
        return $this->queued;
    }

    /**
     * Encrypt a cookie value
     *
     * @param string $value
     * @return string
     */
    private function encrypt(string $value): string
    {
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($value, 'AES-256-CBC', $this->encryptionKey, 0, $iv);
        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt a cookie value
     *
     * @param string $value
     * @return string|null
     */
    private function decrypt(string $value): ?string
    {
        $data = base64_decode($value);
        if ($data === false || strlen($data) < 16) {
            return null;
        }

        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);

        $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $this->encryptionKey, 0, $iv);
        return $decrypted !== false ? $decrypted : null;
    }
}
