<?php

declare(strict_types=1);

namespace Toporia\Framework\Http\Client;

use Toporia\Framework\Http\Client\Exceptions\HttpClientException;

/**
 * HTTP Response
 *
 * Immutable response object from HTTP request.
 */
final class HttpResponse implements HttpResponseInterface
{
    public function __construct(
        private int $status,
        private string $body,
        private array $headers = [],
        private array $cookies = []
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function status(): int
    {
        return $this->status;
    }

    /**
     * {@inheritdoc}
     */
    public function successful(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }

    /**
     * {@inheritdoc}
     */
    public function redirect(): bool
    {
        return $this->status >= 300 && $this->status < 400;
    }

    /**
     * {@inheritdoc}
     */
    public function clientError(): bool
    {
        return $this->status >= 400 && $this->status < 500;
    }

    /**
     * {@inheritdoc}
     */
    public function serverError(): bool
    {
        return $this->status >= 500;
    }

    /**
     * {@inheritdoc}
     */
    public function body(): string
    {
        return $this->body;
    }

    /**
     * {@inheritdoc}
     */
    public function json(bool $assoc = true): mixed
    {
        return json_decode($this->body, $assoc);
    }

    /**
     * {@inheritdoc}
     */
    public function header(string $name): ?string
    {
        $name = strtolower($name);

        foreach ($this->headers as $key => $value) {
            if (strtolower($key) === $name) {
                return is_array($value) ? implode(', ', $value) : $value;
            }
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * {@inheritdoc}
     */
    public function cookies(): array
    {
        return $this->cookies;
    }

    /**
     * {@inheritdoc}
     */
    public function throw(): self
    {
        if (!$this->successful()) {
            throw new HttpClientException(
                "HTTP request failed with status {$this->status}: {$this->body}",
                $this->status
            );
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function onSuccess(callable $callback): self
    {
        if ($this->successful()) {
            $callback($this);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function onError(callable $callback): self
    {
        if (!$this->successful()) {
            $callback($this);
        }

        return $this;
    }
}
