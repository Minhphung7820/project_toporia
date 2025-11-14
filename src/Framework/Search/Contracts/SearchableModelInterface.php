<?php

declare(strict_types=1);

namespace Toporia\Framework\Search\Contracts;

interface SearchableModelInterface
{
    public static function searchIndexName(): string;

    /**
     * @return array<string, mixed>
     */
    public function toSearchDocument(): array;

    public function getSearchDocumentId(): string|int;
}

