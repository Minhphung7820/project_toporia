<?php

declare(strict_types=1);

namespace Framework\Support;

/**
 * LazyCollection - Memory-efficient collection using generators.
 *
 * Unlike regular Collection, LazyCollection doesn't load all items into memory.
 * It uses PHP generators to process items one at a time, making it perfect for:
 * - Large datasets (millions of records)
 * - File processing (CSV, logs, etc.)
 * - Database cursors
 * - Infinite sequences
 * - Streaming data
 *
 * Example:
 * ```php
 * // Process 1 million records without memory issues
 * LazyCollection::make(function() {
 *     foreach (range(1, 1000000) as $number) {
 *         yield $number;
 *     }
 * })
 * ->filter(fn($n) => $n % 2 === 0)
 * ->map(fn($n) => $n * 2)
 * ->take(10)
 * ->all(); // Only processes 20 items!
 * ```
 */
class LazyCollection implements \IteratorAggregate, \Countable
{
    /**
     * @param callable|\Generator|\Traversable|array $source
     */
    public function __construct(
        protected mixed $source = []
    ) {
    }

    /**
     * Create new lazy collection.
     */
    public static function make(mixed $source = []): static
    {
        return new static($source);
    }

    /**
     * Create from range.
     */
    public static function range(int $start, int $end, int $step = 1): static
    {
        return new static(function () use ($start, $end, $step) {
            for ($i = $start; $step > 0 ? $i <= $end : $i >= $end; $i += $step) {
                yield $i;
            }
        });
    }

    /**
     * Create from times.
     */
    public static function times(int $count, callable $callback): static
    {
        return new static(function () use ($count, $callback) {
            for ($i = 1; $i <= $count; $i++) {
                yield $callback($i);
            }
        });
    }

    /**
     * Create infinite sequence.
     */
    public static function infinite(callable $callback = null): static
    {
        return new static(function () use ($callback) {
            $i = 0;
            while (true) {
                yield $callback ? $callback($i++) : $i++;
            }
        });
    }

    /**
     * Get iterator.
     */
    public function getIterator(): \Traversable
    {
        return $this->getGenerator();
    }

    /**
     * Get generator.
     */
    protected function getGenerator(): \Generator
    {
        $source = $this->source;

        if (is_callable($source)) {
            $result = $source();
            if ($result instanceof \Generator) {
                yield from $result;
            } else {
                yield from $this->makeGenerator($result);
            }
        } elseif ($source instanceof \Generator) {
            yield from $source;
        } elseif ($source instanceof \Traversable) {
            yield from $source;
        } elseif (is_array($source)) {
            yield from $source;
        }
    }

    /**
     * Make generator from value.
     */
    protected function makeGenerator(mixed $value): \Generator
    {
        if ($value instanceof \Generator || $value instanceof \Traversable) {
            yield from $value;
        } elseif (is_array($value)) {
            yield from $value;
        } else {
            yield $value;
        }
    }

    /**
     * Map over items lazily.
     */
    public function map(callable $callback): static
    {
        return new static(function () use ($callback) {
            foreach ($this->getGenerator() as $key => $value) {
                yield $key => $callback($value, $key);
            }
        });
    }

    /**
     * Filter items lazily.
     */
    public function filter(callable $callback = null): static
    {
        return new static(function () use ($callback) {
            foreach ($this->getGenerator() as $key => $value) {
                if ($callback === null) {
                    if ($value) {
                        yield $key => $value;
                    }
                } elseif ($callback($value, $key)) {
                    yield $key => $value;
                }
            }
        });
    }

    /**
     * Reject items lazily.
     */
    public function reject(callable $callback): static
    {
        return $this->filter(fn($value, $key) => !$callback($value, $key));
    }

    /**
     * Take first N items.
     */
    public function take(int $limit): static
    {
        return new static(function () use ($limit) {
            $count = 0;
            foreach ($this->getGenerator() as $key => $value) {
                if ($count++ >= $limit) {
                    break;
                }
                yield $key => $value;
            }
        });
    }

    /**
     * Take while condition is true.
     */
    public function takeWhile(callable $callback): static
    {
        return new static(function () use ($callback) {
            foreach ($this->getGenerator() as $key => $value) {
                if (!$callback($value, $key)) {
                    break;
                }
                yield $key => $value;
            }
        });
    }

    /**
     * Take until condition is true.
     */
    public function takeUntil(callable $callback): static
    {
        return $this->takeWhile(fn($value, $key) => !$callback($value, $key));
    }

    /**
     * Skip first N items.
     */
    public function skip(int $offset): static
    {
        return new static(function () use ($offset) {
            $count = 0;
            foreach ($this->getGenerator() as $key => $value) {
                if ($count++ < $offset) {
                    continue;
                }
                yield $key => $value;
            }
        });
    }

    /**
     * Skip while condition is true.
     */
    public function skipWhile(callable $callback): static
    {
        return new static(function () use ($callback) {
            $shouldTake = false;
            foreach ($this->getGenerator() as $key => $value) {
                if (!$shouldTake && !$callback($value, $key)) {
                    $shouldTake = true;
                }

                if ($shouldTake) {
                    yield $key => $value;
                }
            }
        });
    }

    /**
     * Skip until condition is true.
     */
    public function skipUntil(callable $callback): static
    {
        return $this->skipWhile(fn($value, $key) => !$callback($value, $key));
    }

    /**
     * Unique items.
     */
    public function unique(string|callable|null $key = null): static
    {
        return new static(function () use ($key) {
            $callback = is_callable($key) ? $key : fn($item) => $key === null ? $item : ($item[$key] ?? null);
            $seen = [];

            foreach ($this->getGenerator() as $k => $item) {
                $id = $callback($item, $k);

                if (!in_array($id, $seen, true)) {
                    $seen[] = $id;
                    yield $k => $item;
                }
            }
        });
    }

    /**
     * Chunk into collections.
     */
    public function chunk(int $size): static
    {
        return new static(function () use ($size) {
            $chunk = [];

            foreach ($this->getGenerator() as $key => $value) {
                $chunk[$key] = $value;

                if (count($chunk) >= $size) {
                    yield new Collection($chunk);
                    $chunk = [];
                }
            }

            if (!empty($chunk)) {
                yield new Collection($chunk);
            }
        });
    }

    /**
     * Flatten lazily.
     */
    public function flatten(int $depth = INF): static
    {
        return new static(function () use ($depth) {
            foreach ($this->getGenerator() as $item) {
                if (!is_array($item) && !$item instanceof Collection) {
                    yield $item;
                } elseif ($depth === 1) {
                    $values = $item instanceof Collection ? $item->all() : $item;
                    yield from array_values($values);
                } else {
                    $values = $item instanceof Collection ? $item->all() : $item;
                    yield from (new static($values))->flatten($depth - 1)->getGenerator();
                }
            }
        });
    }

    /**
     * Tap into collection.
     */
    public function tap(callable $callback): static
    {
        return new static(function () use ($callback) {
            foreach ($this->getGenerator() as $key => $value) {
                $callback($value, $key);
                yield $key => $value;
            }
        });
    }

    /**
     * Execute callback on each item.
     */
    public function each(callable $callback): static
    {
        foreach ($this->getGenerator() as $key => $value) {
            if ($callback($value, $key) === false) {
                break;
            }
        }

        return $this;
    }

    /**
     * Reduce to single value.
     */
    public function reduce(callable $callback, mixed $initial = null): mixed
    {
        $accumulator = $initial;

        foreach ($this->getGenerator() as $key => $value) {
            $accumulator = $callback($accumulator, $value, $key);
        }

        return $accumulator;
    }

    /**
     * Check if any item passes test.
     */
    public function some(callable $callback): bool
    {
        foreach ($this->getGenerator() as $key => $value) {
            if ($callback($value, $key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if all items pass test.
     */
    public function every(callable $callback): bool
    {
        foreach ($this->getGenerator() as $key => $value) {
            if (!$callback($value, $key)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if collection contains item.
     */
    public function contains(mixed $key, mixed $operator = null, mixed $value = null): bool
    {
        if (func_num_args() === 1) {
            if (is_callable($key)) {
                return $this->some($key);
            }

            foreach ($this->getGenerator() as $item) {
                if ($item === $key) {
                    return true;
                }
            }

            return false;
        }

        return $this->some(fn($item) => $this->compareValues($item[$key] ?? null, $operator, $value));
    }

    /**
     * Get first item.
     */
    public function first(callable $callback = null, mixed $default = null): mixed
    {
        foreach ($this->getGenerator() as $key => $value) {
            if ($callback === null || $callback($value, $key)) {
                return $value;
            }
        }

        return $default;
    }

    /**
     * Get all items as array (materializes the collection).
     */
    public function all(): array
    {
        return iterator_to_array($this->getGenerator());
    }

    /**
     * Get values only.
     */
    public function values(): static
    {
        return new static(function () {
            foreach ($this->getGenerator() as $value) {
                yield $value;
            }
        });
    }

    /**
     * Get keys only.
     */
    public function keys(): static
    {
        return new static(function () {
            foreach ($this->getGenerator() as $key => $value) {
                yield $key;
            }
        });
    }

    /**
     * Collect into eager Collection.
     */
    public function collect(): Collection
    {
        return new Collection($this->all());
    }

    /**
     * Count items (materializes the collection).
     */
    public function count(): int
    {
        $count = 0;

        foreach ($this->getGenerator() as $item) {
            $count++;
        }

        return $count;
    }

    /**
     * Check if empty.
     */
    public function isEmpty(): bool
    {
        foreach ($this->getGenerator() as $item) {
            return false;
        }

        return true;
    }

    /**
     * Check if not empty.
     */
    public function isNotEmpty(): bool
    {
        return !$this->isEmpty();
    }

    /**
     * Zip with other iterables.
     */
    public function zip(mixed ...$arrays): static
    {
        return new static(function () use ($arrays) {
            $iterators = array_map(function ($items) {
                if ($items instanceof \Traversable) {
                    return $items;
                }
                return new \ArrayIterator(is_array($items) ? $items : [$items]);
            }, array_merge([$this->getGenerator()], $arrays));

            while (true) {
                $values = [];
                $continue = false;

                foreach ($iterators as $iterator) {
                    if ($iterator->valid()) {
                        $values[] = $iterator->current();
                        $iterator->next();
                        $continue = true;
                    } else {
                        break 2;
                    }
                }

                if ($continue) {
                    yield new Collection($values);
                }
            }
        });
    }

    /**
     * Remember items (cache in memory).
     */
    public function remember(): static
    {
        $cache = [];
        $iterator = $this->getGenerator();

        return new static(function () use (&$cache, $iterator) {
            foreach ($cache as $key => $value) {
                yield $key => $value;
            }

            foreach ($iterator as $key => $value) {
                $cache[$key] = $value;
                yield $key => $value;
            }
        });
    }

    /**
     * Convert to JSON.
     */
    public function toJson(int $options = 0): string
    {
        return json_encode($this->all(), $options);
    }

    /**
     * Compare values with operator.
     */
    protected function compareValues(mixed $a, mixed $operator, mixed $b): bool
    {
        return match ($operator) {
            '=', '==' => $a == $b,
            '===' => $a === $b,
            '!=', '<>' => $a != $b,
            '!==' => $a !== $b,
            '<' => $a < $b,
            '>' => $a > $b,
            '<=' => $a <= $b,
            '>=' => $a >= $b,
            default => false,
        };
    }
}
