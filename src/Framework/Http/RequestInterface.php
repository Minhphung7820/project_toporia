<?php

declare(strict_types=1);

namespace Toporia\Framework\Http;

/**
 * HTTP Request interface.
 */
interface RequestInterface
{
    /**
     * Get the HTTP method.
     *
     * @return string (GET, POST, PUT, PATCH, DELETE, etc.)
     */
    public function method(): string;

    /**
     * Get the request URI path.
     *
     * @return string
     */
    public function path(): string;

    /**
     * Get query parameter(s).
     *
     * @param string|null $key Specific key or null for all.
     * @param mixed $default Default value if key not found.
     * @return mixed
     */
    public function query(?string $key = null, mixed $default = null): mixed;

    /**
     * Get input data (body/POST).
     *
     * @param string|null $key Specific key or null for all.
     * @param mixed $default Default value if key not found.
     * @return mixed
     */
    public function input(?string $key = null, mixed $default = null): mixed;

    /**
     * Get a header value.
     *
     * @param string $name Header name.
     * @param string|null $default Default value.
     * @return string|null
     */
    public function header(string $name, ?string $default = null): ?string;

    /**
     * Check if request is AJAX.
     *
     * @return bool
     */
    public function isAjax(): bool;

    /**
     * Check if request expects JSON response.
     *
     * @return bool
     */
    public function expectsJson(): bool;

    /**
     * Get the raw request body.
     *
     * @return string
     */
    public function raw(): string;
}
