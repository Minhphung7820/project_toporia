<?php

declare(strict_types=1);

namespace Toporia\Framework\Search\Query;

use Toporia\Framework\Search\Contracts\SearchQueryBuilderInterface;

final class SearchQueryBuilder implements SearchQueryBuilderInterface
{
    private array $must = [];
    private array $filter = [];
    private array $sort = [];
    private int $page = 1;
    private int $perPage = 15;

    public function term(string $field, mixed $value): self
    {
        $this->filter[] = ['term' => [$field => $value]];
        return $this;
    }

    public function match(string $field, string $query): self
    {
        $this->must[] = ['match' => [$field => $query]];
        return $this;
    }

    public function range(string $field, array $range): self
    {
        $this->filter[] = ['range' => [$field => $range]];
        return $this;
    }

    public function sort(string $field, string $direction = 'asc'): self
    {
        $this->sort[] = [$field => ['order' => $direction]];
        return $this;
    }

    public function paginate(int $page, int $perPage): self
    {
        $this->page = max(1, $page);
        $this->perPage = max(1, $perPage);
        return $this;
    }

    public function toArray(): array
    {
        $query = [
            'bool' => [
                'must' => $this->must,
                'filter' => $this->filter,
            ],
        ];

        return [
            'query' => $query,
            'from' => ($this->page - 1) * $this->perPage,
            'size' => $this->perPage,
            'sort' => $this->sort,
        ];
    }
}

