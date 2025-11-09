<?php

declare(strict_types=1);

namespace Toporia\Framework\Http\Client;

/**
 * HTTP Response Interface
 *
 * Represents response from HTTP request.
 */
interface HttpResponseInterface
{
    /**
     * Get response status code
     *
     * @return int
     */
    public function status(): int;

    /**
     * Check if response was successful (2xx)
     *
     * @return bool
     */
    public function successful(): bool;

    /**
     * Check if response was a redirect (3xx)
     *
     * @return bool
     */
    public function redirect(): bool;

    /**
     * Check if response was client error (4xx)
     *
     * @return bool
     */
    public function clientError(): bool;

    /**
     * Check if response was server error (5xx)
     *
     * @return bool
     */
    public function serverError(): bool;

    /**
     * Get response body as string
     *
     * @return string
     */
    public function body(): string;

    /**
     * Get response as JSON decoded array
     *
     * @param bool $assoc Decode as associative array
     * @return mixed
     */
    public function json(bool $assoc = true): mixed;

    /**
     * Get specific header value
     *
     * @param string $name
     * @return string|null
     */
    public function header(string $name): ?string;

    /**
     * Get all headers
     *
     * @return array
     */
    public function headers(): array;

    /**
     * Get response cookies
     *
     * @return array
     */
    public function cookies(): array;

    /**
     * Throw exception if response was not successful
     *
     * @return self
     * @throws HttpClientException
     */
    public function throw(): self;

    /**
     * Execute callback if response was successful
     *
     * @param callable $callback
     * @return self
     */
    public function onSuccess(callable $callback): self;

    /**
     * Execute callback if response was error
     *
     * @param callable $callback
     * @return self
     */
    public function onError(callable $callback): self;
}
