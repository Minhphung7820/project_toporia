<?php

declare(strict_types=1);

namespace Toporia\Framework\Search\Contracts;

interface SearchQueryBuilderInterface
{
    public function term(string $field, mixed $value): self;

    public function match(string $field, string $query): self;

    public function range(string $field, array $range): self;

    public function sort(string $field, string $direction = 'asc'): self;

    public function paginate(int $page, int $perPage): self;

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;
}

