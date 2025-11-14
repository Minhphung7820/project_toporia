<?php

declare(strict_types=1);

namespace Toporia\Framework\Search;

use Toporia\Framework\Search\Contracts\SearchIndexerInterface;
use Toporia\Framework\Search\Contracts\SearchableModelInterface;

trait Searchable
{
    /**
     * Hook: Called after model is created.
     *
     * Note: If your model already has a created() method,
     * call $this->pushToSearch() from within it instead.
     */
    protected function created(): void
    {
        $this->pushToSearch();
    }

    /**
     * Hook: Called after model is updated.
     *
     * Note: If your model already has an updated() method,
     * call $this->pushToSearch() from within it instead.
     */
    protected function updated(): void
    {
        $this->pushToSearch();
    }

    /**
     * Hook: Called after model is deleted.
     *
     * Note: If your model already has a deleted() method,
     * call $this->removeFromSearch() from within it instead.
     */
    protected function deleted(): void
    {
        $this->removeFromSearch();
    }

    public function pushToSearch(): void
    {
        if (!$this instanceof SearchableModelInterface) {
            return;
        }

        /** @var SearchIndexerInterface $indexer */
        $indexer = container(SearchIndexerInterface::class);
        $indexer->upsert(
            static::searchIndexName(),
            $this->getSearchDocumentId(),
            $this->toSearchDocument()
        );
    }

    public function removeFromSearch(): void
    {
        if (!$this instanceof SearchableModelInterface) {
            return;
        }

        /** @var SearchIndexerInterface $indexer */
        $indexer = container(SearchIndexerInterface::class);
        $indexer->remove(
            static::searchIndexName(),
            $this->getSearchDocumentId()
        );
    }
}
