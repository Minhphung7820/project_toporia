<?php

declare(strict_types=1);

namespace Framework\Http;

/**
 * HTTP Response implementation.
 *
 * Handles HTTP response generation including:
 * - Status codes
 * - Headers
 * - Content output (HTML, JSON, redirects)
 */
final class Response implements ResponseInterface
{
    /**
     * @var int HTTP status code.
     */
    private int $status = 200;

    /**
     * @var array<string, string> Response headers.
     */
    private array $headers = [];

    /**
     * @var bool Whether headers have been sent.
     */
    private bool $headersSent = false;

    public function __construct()
    {
        $this->headers['Content-Type'] = 'text/html; charset=UTF-8';
    }

    /**
     * {@inheritdoc}
     */
    public function setStatus(int $code): self
    {
        $this->status = $code;
        http_response_code($code);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;

        if (!$this->headersSent) {
            header($name . ': ' . $value, replace: true);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function html(string $content, int $status = 200): void
    {
        $this->setStatus($status);
        $this->header('Content-Type', 'text/html; charset=UTF-8');
        $this->send($content);
    }

    /**
     * {@inheritdoc}
     */
    public function json(mixed $data, int $status = 200): void
    {
        $this->setStatus($status);
        $this->header('Content-Type', 'application/json');

        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            $this->setStatus(500);
            $json = json_encode([
                'error' => 'Failed to encode JSON',
                'message' => json_last_error_msg()
            ]);
        }

        $this->send($json);
    }

    /**
     * {@inheritdoc}
     */
    public function redirect(string $url, int $status = 302): void
    {
        $this->setStatus($status);
        $this->header('Location', $url);
        $this->send('');
    }

    /**
     * {@inheritdoc}
     */
    public function send(string $content): void
    {
        $this->headersSent = true;
        echo $content;
    }

    /**
     * Send a file download response.
     *
     * @param string $path File path.
     * @param string|null $name Download filename (optional).
     * @return void
     */
    public function download(string $path, ?string $name = null): void
    {
        if (!file_exists($path)) {
            $this->html('<h1>404 File Not Found</h1>', 404);
            return;
        }

        $name = $name ?? basename($path);
        $this->header('Content-Type', 'application/octet-stream');
        $this->header('Content-Disposition', 'attachment; filename="' . $name . '"');
        $this->header('Content-Length', (string) filesize($path));

        $this->send(file_get_contents($path));
    }

    /**
     * Send a no-content response.
     *
     * @return void
     */
    public function noContent(): void
    {
        $this->setStatus(204);
        $this->send('');
    }

    /**
     * Get all response headers.
     *
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Get the current status code.
     *
     * @return int
     */
    public function getStatus(): int
    {
        return $this->status;
    }
}
