<?php

declare(strict_types=1);

namespace Toporia\Framework\Search\Elasticsearch;

use Toporia\Framework\Search\Contracts\SearchClientInterface;
use Toporia\Framework\Search\Contracts\SearchIndexerInterface;

final class ElasticsearchIndexer implements SearchIndexerInterface
{
    public function __construct(
        private readonly SearchClientInterface $client
    ) {
    }

    public function upsert(string $index, string|int $id, array $document): void
    {
        $this->client->index($index, $id, $document);
    }

    public function remove(string $index, string|int $id): void
    {
        $this->client->delete($index, $id);
    }

    public function bulkUpsert(string $index, iterable $documents): void
    {
        $ops = [];

        foreach ($documents as $doc) {
            $ops[] = [
                'index' => [
                    '_index' => $index,
                    '_id' => (string) $doc['id'],
                ],
            ];
            $ops[] = $doc['body'];
        }

        $this->client->bulk($ops);
    }
}

