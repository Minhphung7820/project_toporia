<?php

declare(strict_types=1);

namespace Toporia\Framework\Search;

use Toporia\Framework\Search\Contracts\SearchClientInterface;
use Toporia\Framework\Search\Contracts\SearchIndexerInterface;
use Toporia\Framework\Search\Contracts\SearchQueryBuilderInterface;
use Toporia\Framework\Search\Query\SearchQueryBuilder;

final class SearchManager
{
    /**
     * @param array<string, array<string, mixed>> $indices
     */
    public function __construct(
        private readonly SearchClientInterface $client,
        private readonly SearchIndexerInterface $indexer,
        private readonly array $indices = []
    ) {
    }

    public function client(): SearchClientInterface
    {
        return $this->client;
    }

    public function indexer(): SearchIndexerInterface
    {
        return $this->indexer;
    }

    public function query(): SearchQueryBuilderInterface
    {
        return new SearchQueryBuilder();
    }

    public function ensureIndices(): void
    {
        foreach ($this->indices as $definition) {
            if (!isset($definition['name'])) {
                continue;
            }
            $this->client->ensureIndex($definition['name'], $definition);
        }
    }

    public function search(string $index, array $query): array
    {
        return $this->client->search($index, $query);
    }
}

