<?php

declare(strict_types=1);

namespace Toporia\Framework\Http\Client;

use Toporia\Framework\Http\Client\Exceptions\HttpClientException;

/**
 * REST HTTP Client
 *
 * cURL-based HTTP client for RESTful APIs.
 */
final class RestClient implements HttpClientInterface
{
    private string $baseUrl = '';
    private array $headers = [];
    private int $timeout = 30;
    private int $retryTimes = 0;
    private int $retrySleep = 100;
    private ?string $contentType = null;
    private ?string $accept = null;

    /**
     * {@inheritdoc}
     */
    public function get(string $url, array $query = [], array $headers = []): HttpResponseInterface
    {
        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        return $this->send('GET', $url, null, $headers);
    }

    /**
     * {@inheritdoc}
     */
    public function post(string $url, mixed $data = null, array $headers = []): HttpResponseInterface
    {
        return $this->send('POST', $url, $data, $headers);
    }

    /**
     * {@inheritdoc}
     */
    public function put(string $url, mixed $data = null, array $headers = []): HttpResponseInterface
    {
        return $this->send('PUT', $url, $data, $headers);
    }

    /**
     * {@inheritdoc}
     */
    public function patch(string $url, mixed $data = null, array $headers = []): HttpResponseInterface
    {
        return $this->send('PATCH', $url, $data, $headers);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $url, array $headers = []): HttpResponseInterface
    {
        return $this->send('DELETE', $url, null, $headers);
    }

    /**
     * {@inheritdoc}
     */
    public function withBaseUrl(string $baseUrl): self
    {
        $clone = clone $this;
        $clone->baseUrl = rtrim($baseUrl, '/');
        return $clone;
    }

    /**
     * {@inheritdoc}
     */
    public function withHeaders(array $headers): self
    {
        $clone = clone $this;
        $clone->headers = array_merge($clone->headers, $headers);
        return $clone;
    }

    /**
     * {@inheritdoc}
     */
    public function withToken(string $token): self
    {
        return $this->withHeaders(['Authorization' => "Bearer {$token}"]);
    }

    /**
     * {@inheritdoc}
     */
    public function withBasicAuth(string $username, string $password): self
    {
        $encoded = base64_encode("{$username}:{$password}");
        return $this->withHeaders(['Authorization' => "Basic {$encoded}"]);
    }

    /**
     * {@inheritdoc}
     */
    public function timeout(int $seconds): self
    {
        $clone = clone $this;
        $clone->timeout = $seconds;
        return $clone;
    }

    /**
     * {@inheritdoc}
     */
    public function retry(int $times, int $sleep = 100): self
    {
        $clone = clone $this;
        $clone->retryTimes = $times;
        $clone->retrySleep = $sleep;
        return $clone;
    }

    /**
     * {@inheritdoc}
     */
    public function acceptJson(): self
    {
        $clone = clone $this;
        $clone->accept = 'application/json';
        return $clone;
    }

    /**
     * {@inheritdoc}
     */
    public function asJson(): self
    {
        $clone = clone $this;
        $clone->contentType = 'application/json';
        return $clone;
    }

    /**
     * {@inheritdoc}
     */
    public function asForm(): self
    {
        $clone = clone $this;
        $clone->contentType = 'application/x-www-form-urlencoded';
        return $clone;
    }

    /**
     * {@inheritdoc}
     */
    public function asMultipart(): self
    {
        $clone = clone $this;
        $clone->contentType = 'multipart/form-data';
        return $clone;
    }

    /**
     * Send HTTP request
     *
     * @param string $method
     * @param string $url
     * @param mixed $data
     * @param array $headers
     * @return HttpResponseInterface
     */
    private function send(string $method, string $url, mixed $data = null, array $headers = []): HttpResponseInterface
    {
        $attempts = 0;
        $maxAttempts = $this->retryTimes + 1;

        while ($attempts < $maxAttempts) {
            try {
                return $this->executeRequest($method, $url, $data, $headers);
            } catch (HttpClientException $e) {
                $attempts++;

                if ($attempts >= $maxAttempts) {
                    throw $e;
                }

                // Sleep before retry
                usleep($this->retrySleep * 1000);
            }
        }

        throw new HttpClientException('Max retry attempts reached');
    }

    /**
     * Execute single HTTP request
     *
     * @param string $method
     * @param string $url
     * @param mixed $data
     * @param array $headers
     * @return HttpResponseInterface
     */
    private function executeRequest(string $method, string $url, mixed $data, array $headers): HttpResponseInterface
    {
        // Build full URL
        $fullUrl = $this->buildUrl($url);

        // Initialize cURL
        $ch = curl_init();

        // Set URL
        curl_setopt($ch, CURLOPT_URL, $fullUrl);

        // Set method
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        // Return response
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // Return headers
        curl_setopt($ch, CURLOPT_HEADER, true);

        // Timeout
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);

        // Follow redirects
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        // Build headers
        $requestHeaders = $this->buildHeaders($headers);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $requestHeaders);

        // Set request body
        if ($data !== null && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $body = $this->prepareBody($data);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        // Execute request
        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new HttpClientException("cURL error: {$error}");
        }

        // Get response info
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

        curl_close($ch);

        // Parse response
        $responseHeaders = substr($response, 0, $headerSize);
        $responseBody = substr($response, $headerSize);

        $parsedHeaders = $this->parseHeaders($responseHeaders);

        return new HttpResponse(
            $statusCode,
            $responseBody,
            $parsedHeaders,
            []
        );
    }

    /**
     * Build full URL
     *
     * @param string $url
     * @return string
     */
    private function buildUrl(string $url): string
    {
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        if (empty($this->baseUrl)) {
            throw new HttpClientException('Base URL is required for relative URLs');
        }

        return $this->baseUrl . '/' . ltrim($url, '/');
    }

    /**
     * Build request headers
     *
     * @param array $additionalHeaders
     * @return array
     */
    private function buildHeaders(array $additionalHeaders): array
    {
        $headers = array_merge($this->headers, $additionalHeaders);

        // Add Content-Type
        if ($this->contentType !== null && !isset($headers['Content-Type'])) {
            $headers['Content-Type'] = $this->contentType;
        }

        // Add Accept
        if ($this->accept !== null && !isset($headers['Accept'])) {
            $headers['Accept'] = $this->accept;
        }

        // Convert to cURL format
        $curlHeaders = [];
        foreach ($headers as $key => $value) {
            $curlHeaders[] = "{$key}: {$value}";
        }

        return $curlHeaders;
    }

    /**
     * Prepare request body
     *
     * @param mixed $data
     * @return string
     */
    private function prepareBody(mixed $data): string
    {
        if (is_string($data)) {
            return $data;
        }

        if ($this->contentType === 'application/json') {
            return json_encode($data);
        }

        if ($this->contentType === 'application/x-www-form-urlencoded') {
            return http_build_query($data);
        }

        // Default: JSON
        return json_encode($data);
    }

    /**
     * Parse response headers
     *
     * @param string $headerString
     * @return array
     */
    private function parseHeaders(string $headerString): array
    {
        $headers = [];
        $lines = explode("\r\n", $headerString);

        foreach ($lines as $line) {
            if (strpos($line, ':') !== false) {
                [$key, $value] = explode(':', $line, 2);
                $headers[trim($key)] = trim($value);
            }
        }

        return $headers;
    }
}
