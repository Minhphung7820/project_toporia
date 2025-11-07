<?php

declare(strict_types=1);

namespace Framework\Http;

/**
 * HTTP Request implementation.
 *
 * Encapsulates all data from an incoming HTTP request including:
 * - HTTP method
 * - URI path
 * - Query parameters
 * - Request body/input
 * - Headers
 */
final class Request implements RequestInterface
{
    /**
     * @var string HTTP method.
     */
    private string $method;

    /**
     * @var string Request URI path.
     */
    private string $path;

    /**
     * @var array<string, mixed> Query parameters.
     */
    private array $query;

    /**
     * @var array<string, mixed> Request body data.
     */
    private array $body;

    /**
     * @var array<string, string> Request headers.
     */
    private array $headers;

    /**
     * @var string Raw request body.
     */
    private string $rawBody;

    /**
     * Create a Request instance from PHP globals.
     *
     * @return self
     */
    public static function capture(): self
    {
        $request = new self();

        // Method
        $request->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        // Path
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $request->path = rtrim($uri, '/') ?: '/';

        // Query parameters
        $request->query = $_GET ?? [];

        // Headers
        $request->headers = self::extractHeaders();

        // Raw body
        $request->rawBody = file_get_contents('php://input') ?: '';

        // Parse body based on content type
        $contentType = $request->headers['content-type'] ?? '';

        if (str_contains($contentType, 'application/json')) {
            $request->body = json_decode($request->rawBody, true) ?: [];
        } else {
            $request->body = $_POST ?? [];
        }

        return $request;
    }

    /**
     * {@inheritdoc}
     */
    public function method(): string
    {
        return $this->method;
    }

    /**
     * {@inheritdoc}
     */
    public function path(): string
    {
        return $this->path;
    }

    /**
     * {@inheritdoc}
     */
    public function query(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->query;
        }

        return $this->query[$key] ?? $default;
    }

    /**
     * {@inheritdoc}
     */
    public function input(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->body;
        }

        return $this->body[$key] ?? $default;
    }

    /**
     * {@inheritdoc}
     */
    public function header(string $name, ?string $default = null): ?string
    {
        $name = strtolower($name);
        return $this->headers[$name] ?? $default;
    }

    /**
     * {@inheritdoc}
     */
    public function isAjax(): bool
    {
        return strtolower($this->header('x-requested-with', '')) === 'xmlhttprequest';
    }

    /**
     * {@inheritdoc}
     */
    public function expectsJson(): bool
    {
        $accept = $this->header('accept', '');
        return str_contains($accept, 'application/json') || $this->isAjax();
    }

    /**
     * {@inheritdoc}
     */
    public function raw(): string
    {
        return $this->rawBody;
    }

    /**
     * Extract headers from $_SERVER superglobal.
     *
     * @return array<string, string>
     */
    private static function extractHeaders(): array
    {
        $headers = [];

        foreach ($_SERVER as $key => $value) {
            // HTTP_ prefix headers
            if (str_starts_with($key, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$name] = $value;
                continue;
            }

            // Common headers without HTTP_ prefix
            if (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
                $name = strtolower(str_replace('_', '-', $key));
                $headers[$name] = $value;
            }
        }

        return $headers;
    }

    /**
     * Check if the request has specific input key.
     *
     * @param string $key Input key.
     * @return bool
     */
    public function has(string $key): bool
    {
        return isset($this->body[$key]);
    }

    /**
     * Get all input data.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->body;
    }

    /**
     * Get only specified input keys.
     *
     * @param array<string> $keys Keys to retrieve.
     * @return array<string, mixed>
     */
    public function only(array $keys): array
    {
        return array_intersect_key($this->body, array_flip($keys));
    }

    /**
     * Get all input except specified keys.
     *
     * @param array<string> $keys Keys to exclude.
     * @return array<string, mixed>
     */
    public function except(array $keys): array
    {
        return array_diff_key($this->body, array_flip($keys));
    }
}
