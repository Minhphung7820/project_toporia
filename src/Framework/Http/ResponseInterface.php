<?php

declare(strict_types=1);

namespace Framework\Http;

/**
 * HTTP Response interface.
 */
interface ResponseInterface
{
    /**
     * Set the HTTP status code.
     *
     * @param int $code HTTP status code.
     * @return self
     */
    public function setStatus(int $code): self;

    /**
     * Set a response header.
     *
     * @param string $name Header name.
     * @param string $value Header value.
     * @return self
     */
    public function header(string $name, string $value): self;

    /**
     * Send an HTML response.
     *
     * @param string $content HTML content.
     * @param int $status HTTP status code.
     * @return void
     */
    public function html(string $content, int $status = 200): void;

    /**
     * Send a JSON response.
     *
     * @param mixed $data Data to encode as JSON.
     * @param int $status HTTP status code.
     * @return void
     */
    public function json(mixed $data, int $status = 200): void;

    /**
     * Send a redirect response.
     *
     * @param string $url Target URL.
     * @param int $status HTTP status code (default 302).
     * @return void
     */
    public function redirect(string $url, int $status = 302): void;

    /**
     * Send the response output.
     *
     * @param string $content Response body.
     * @return void
     */
    public function send(string $content): void;
}
