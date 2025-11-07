<?php

declare(strict_types=1);

namespace Framework\Database\Query;

use Framework\Support\Collection;

/**
 * Typed collection representing raw database rows.
 *
 * - Extends Support\Collection to reuse the fluent functional API (map, filter, reduce, ...)
 * - Each item is an associative array: array<string,mixed>
 * - Provides small helpers commonly needed for row resultsets
 *
 * @extends Collection<int, array<string,mixed>>
 */
class RowCollection extends Collection
{
  /**
   * Return a collection of a given column's values (like pluck).
   *
   * @param string $key Column name to extract from each row.
   * @return static New collection with values or null where missing.
   */
  public function pluckCol(string $key): static
  {
    return $this->map(fn(array $row) => $row[$key] ?? null);
  }

  /**
   * Return the first row where $row[$key] === $value.
   *
   * @param string $key
   * @param mixed  $value
   * @return array<string,mixed>|null
   */
  public function firstWhere(string $key, mixed $value): ?array
  {
    foreach ($this->all() as $row) {
      if (($row[$key] ?? null) === $value) return $row;
    }
    return null;
  }

  /**
   * Convert to a plain array of rows.
   *
   * @return array<int, array<string,mixed>>
   */
  public function toArray(): array
  {
    /** @var array<int, array<string,mixed>> $items */
    $items = parent::all();
    return $items;
  }
}
