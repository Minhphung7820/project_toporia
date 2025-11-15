<?php

declare(strict_types=1);

namespace Toporia\Framework\Http;

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
     * Check if the request is over HTTPS.
     *
     * @return bool
     */
    public function isSecure(): bool
    {
        // Check HTTPS server variable
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }

        // Check forwarded protocol header (proxy/load balancer)
        if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
            return true;
        }

        // Check standard port
        if (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) {
            return true;
        }

        return false;
    }

    /**
     * Get the host from the request.
     *
     * @return string
     */
    public function host(): string
    {
        // Check forwarded host header first (proxy/load balancer)
        if (isset($_SERVER['HTTP_X_FORWARDED_HOST'])) {
            $hosts = explode(',', $_SERVER['HTTP_X_FORWARDED_HOST']);
            return trim($hosts[0]);
        }

        // Check standard host header
        if (isset($_SERVER['HTTP_HOST'])) {
            return $_SERVER['HTTP_HOST'];
        }

        // Fallback to server name
        return $_SERVER['SERVER_NAME'] ?? 'localhost';
    }

    /**
     * {@inheritdoc}
     */
    public function raw(): string
    {
        return $this->rawBody;
    }

    /**
     * Get client IP address.
     *
     * Checks common proxy headers for the real client IP.
     * Falls back to REMOTE_ADDR if no proxy headers found.
     *
     * @return string Client IP address.
     */
    public function ip(): string
    {
        // Check proxy headers (in order of priority)
        $proxyHeaders = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
        ];

        foreach ($proxyHeaders as $header) {
            if (!empty($_SERVER[$header])) {
                // X-Forwarded-For can contain multiple IPs (client, proxy1, proxy2...)
                // Take the first one (the original client)
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);

                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        // Fallback to REMOTE_ADDR
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
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
