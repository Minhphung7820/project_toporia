<?php

declare(strict_types=1);

namespace Toporia\Framework\Database\Query;

use Toporia\Framework\Support\Collection;

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
   * Find first row where column matches value.
   *
   * Supports three forms:
   * - firstWhere('status', 'active')  // key = value
   * - firstWhere('price', '>', 100)   // key operator value
   * - firstWhere(fn($row) => $row['active'])  // callback
   *
   * @param string|callable $key
   * @param mixed $operator
   * @param mixed $value
   * @return mixed
   */
  public function firstWhere(string|callable $key, mixed $operator = null, mixed $value = null): mixed
  {
    // Callback form
    if (is_callable($key)) {
      foreach ($this->all() as $k => $row) {
        if ($key($row, $k)) return $row;
      }
      return null;
    }

    // Two-argument form: firstWhere('status', 'active')
    if ($value === null) {
      $value = $operator;
      $operator = '=';
    }

    // Three-argument form: firstWhere('price', '>', 100)
    foreach ($this->all() as $row) {
      $actual = $row[$key] ?? null;

      $matches = match($operator) {
        '=' => $actual === $value,
        '==' => $actual == $value,
        '!=' => $actual != $value,
        '!==' => $actual !== $value,
        '<' => $actual < $value,
        '>' => $actual > $value,
        '<=' => $actual <= $value,
        '>=' => $actual >= $value,
        default => $actual === $value,
      };

      if ($matches) return $row;
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
