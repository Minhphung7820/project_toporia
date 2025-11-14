<?php

declare(strict_types=1);

namespace Toporia\Framework\Search\Contracts;

interface SearchIndexerInterface
{
    /**
     * Index or update a document.
     *
     * @param string $index
     * @param string|int $id
     * @param array<string, mixed> $document
     */
    public function upsert(string $index, string|int $id, array $document): void;

    /**
     * Remove a document from index.
     *
     * @param string $index
     * @param string|int $id
     */
    public function remove(string $index, string|int $id): void;

    /**
     * Bulk sync multiple documents.
     *
     * @param string $index
     * @param iterable<array{ id: string|int, body: array<string, mixed> }> $documents
     */
    public function bulkUpsert(string $index, iterable $documents): void;
}

