<?php

declare(strict_types=1);

namespace Toporia\Framework\Database\ORM;

use Toporia\Framework\Support\Collection;

/**
 * Typed collection for Model instances.
 *
 * - Extends the base Support\Collection, preserving the fluent functional API (map, filter, reduce, ...).
 * - Adds convenience helpers specific to Model semantics (keys, in-memory find, batch save).
 *
 * @template T of Model
 * @extends Collection<int, T>
 */
class ModelCollection extends Collection
{
  /**
   * Return the array of primary keys for all models in the collection.
   *
   * @return array<int, int|string>
   */
  public function modelKeys(): array
  {
    return $this->map(fn(Model $m) => $m->getKey())->values()->all();
  }

  /**
   * Find the first model with a matching primary key.
   *
   * @param int|string $key
   * @return T|null
   */
  public function find(int|string $key): ?Model
  {
    foreach ($this->all() as $m) {
      if ($m->getKey() === $key) return $m;
    }
    return null;
  }

  /**
   * Save all models in the collection (if they implement ->save()).
   *
   * @return int Number of successful saves.
   */
  public function save(): int
  {
    $ok = 0;
    foreach ($this->all() as $m) {
      if (method_exists($m, 'save') && $m->save()) $ok++;
    }
    return $ok;
  }

  /**
   * Convert the collection to an array of model arrays.
   *
   * @return array<int, array<string,mixed>>
   */
  public function toArray(): array
  {
    return $this->map(
      fn(Model $m) => method_exists($m, 'toArray') ? $m->toArray() : get_object_vars($m)
    )->values()->all();
  }
}
