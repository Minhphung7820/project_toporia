<?php

declare(strict_types=1);

namespace Toporia\Framework\Search\Contracts;

/**
 * Low-level search client abstraction (Elasticsearch, OpenSearch, etc.)
 */
interface SearchClientInterface
{
    /**
     * Index a single document.
     *
     * @param string $index
     * @param string|int $id
     * @param array<string, mixed> $body
     */
    public function index(string $index, string|int $id, array $body): void;

    /**
     * Bulk index documents.
     *
     * @param iterable<array<string, mixed>> $operations
     */
    public function bulk(iterable $operations): void;

    /**
     * Delete document.
     *
     * @param string $index
     * @param string|int $id
     */
    public function delete(string $index, string|int $id): void;

    /**
     * Search.
     *
     * @param string $index
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    public function search(string $index, array $query): array;

    /**
     * Ensure index exists with settings/mappings.
     *
     * @param string $index
     * @param array<string, mixed> $definition
     */
    public function ensureIndex(string $index, array $definition): void;
}

