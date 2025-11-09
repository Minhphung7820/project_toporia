<?php

declare(strict_types=1);

namespace Toporia\Framework\Support\Accessors;

use Toporia\Framework\Foundation\ServiceAccessor;
use Toporia\Framework\Http\Client\ClientManagerInterface;
use Toporia\Framework\Http\Client\HttpClientInterface;
use Toporia\Framework\Http\Client\GraphQLClient;
use Toporia\Framework\Http\Client\HttpResponseInterface;

/**
 * HTTP Accessor
 *
 * Static-like access to HTTP client services.
 *
 * @method static HttpClientInterface client(?string $name = null)
 * @method static GraphQLClient graphql(?string $name = null)
 * @method static HttpResponseInterface get(string $url, array $query = [], array $headers = [])
 * @method static HttpResponseInterface post(string $url, mixed $data = null, array $headers = [])
 * @method static HttpResponseInterface put(string $url, mixed $data = null, array $headers = [])
 * @method static HttpResponseInterface patch(string $url, mixed $data = null, array $headers = [])
 * @method static HttpResponseInterface delete(string $url, array $headers = [])
 * @method static HttpClientInterface withBaseUrl(string $baseUrl)
 * @method static HttpClientInterface withHeaders(array $headers)
 * @method static HttpClientInterface withToken(string $token)
 * @method static HttpClientInterface withBasicAuth(string $username, string $password)
 * @method static HttpClientInterface timeout(int $seconds)
 * @method static HttpClientInterface retry(int $times, int $sleep = 100)
 * @method static HttpClientInterface acceptJson()
 * @method static HttpClientInterface asJson()
 * @method static HttpClientInterface asForm()
 * @method static HttpClientInterface asMultipart()
 */
final class Http extends ServiceAccessor
{
    /**
     * {@inheritdoc}
     */
    protected static function getServiceIdentifier(): string
    {
        return ClientManagerInterface::class;
    }
}
