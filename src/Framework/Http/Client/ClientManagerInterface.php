<?php

declare(strict_types=1);

namespace Toporia\Framework\Http\Client;

/**
 * HTTP Client Manager Interface
 *
 * Manages multiple HTTP client instances with different configurations.
 */
interface ClientManagerInterface extends HttpClientInterface
{
    /**
     * Get HTTP client instance
     *
     * @param string|null $name Client name (null for default)
     * @return HttpClientInterface
     */
    public function client(?string $name = null): HttpClientInterface;

    /**
     * Get GraphQL client instance
     *
     * @param string|null $name Client name (null for default)
     * @return GraphQLClient
     */
    public function graphql(?string $name = null): GraphQLClient;

    /**
     * Get default client name
     *
     * @return string
     */
    public function getDefaultClient(): string;
}
