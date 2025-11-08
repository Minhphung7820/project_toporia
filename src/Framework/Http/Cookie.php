<?php

declare(strict_types=1);

namespace Toporia\Framework\Http;

/**
 * Cookie Value Object
 *
 * Represents an HTTP cookie with security options.
 * Immutable value object following Clean Architecture principles.
 */
final class Cookie
{
    public function __construct(
        public readonly string $name,
        public readonly string $value = '',
        public readonly int $expires = 0,
        public readonly string $path = '/',
        public readonly string $domain = '',
        public readonly bool $secure = false,
        public readonly bool $httpOnly = true,
        public readonly string $sameSite = 'Lax' // Lax, Strict, None
    ) {}

    /**
     * Create a cookie that expires in specified minutes
     *
     * @param string $name
     * @param string $value
     * @param int $minutes
     * @param array $options
     * @return self
     */
    public static function make(string $name, string $value, int $minutes = 60, array $options = []): self
    {
        return new self(
            name: $name,
            value: $value,
            expires: time() + ($minutes * 60),
            path: $options['path'] ?? '/',
            domain: $options['domain'] ?? '',
            secure: $options['secure'] ?? false,
            httpOnly: $options['httpOnly'] ?? true,
            sameSite: $options['sameSite'] ?? 'Lax'
        );
    }

    /**
     * Create a cookie that lasts forever (5 years)
     *
     * @param string $name
     * @param string $value
     * @param array $options
     * @return self
     */
    public static function forever(string $name, string $value, array $options = []): self
    {
        return self::make($name, $value, 60 * 24 * 365 * 5, $options);
    }

    /**
     * Create a cookie that expires immediately (for deletion)
     *
     * @param string $name
     * @param array $options
     * @return self
     */
    public static function forget(string $name, array $options = []): self
    {
        return new self(
            name: $name,
            value: '',
            expires: time() - 3600,
            path: $options['path'] ?? '/',
            domain: $options['domain'] ?? '',
            secure: $options['secure'] ?? false,
            httpOnly: $options['httpOnly'] ?? true,
            sameSite: $options['sameSite'] ?? 'Lax'
        );
    }

    /**
     * Send the cookie to the browser
     *
     * @return bool
     */
    public function send(): bool
    {
        return setcookie(
            $this->name,
            $this->value,
            [
                'expires' => $this->expires,
                'path' => $this->path,
                'domain' => $this->domain,
                'secure' => $this->secure,
                'httponly' => $this->httpOnly,
                'samesite' => $this->sameSite,
            ]
        );
    }

    /**
     * Convert to array
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'value' => $this->value,
            'expires' => $this->expires,
            'path' => $this->path,
            'domain' => $this->domain,
            'secure' => $this->secure,
            'httpOnly' => $this->httpOnly,
            'sameSite' => $this->sameSite,
        ];
    }
}
