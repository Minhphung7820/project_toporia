<?php

declare(strict_types=1);

namespace Framework\Support;

/**
 * Collection - Advanced immutable collection with functional operations.
 *
 * Enhanced collection implementation with features beyond Laravel:
 * - Truly immutable by default (all operations return new instances)
 * - Advanced functional methods (window, sliding, transpose)
 * - Better performance with lazy evaluation
 * - Type-safe operations with generics support
 * - Pipe and composition patterns
 * - Statistical functions
 * - Set operations
 *
 * @template TKey of array-key
 * @template TValue
 */
class Collection implements CollectionInterface
{
    /**
     * @param array<TKey, TValue> $items
     */
    protected function __construct(
        protected array $items = []
    ) {
    }

    /**
     * Create new collection from items.
     */
    public static function make(mixed $items = []): static
    {
        if ($items instanceof self) {
            return new static($items->all());
        }

        if ($items instanceof \Traversable) {
            return new static(iterator_to_array($items));
        }

        return new static((array) $items);
    }

    /**
     * Create collection from range.
     */
    public static function range(int $start, int $end, int $step = 1): static
    {
        return new static(range($start, $end, $step));
    }

    /**
     * Create collection by repeating value.
     */
    public static function times(int $count, callable $callback): static
    {
        $items = [];
        for ($i = 1; $i <= $count; $i++) {
            $items[] = $callback($i);
        }
        return new static($items);
    }

    /**
     * Wrap value in collection if not already.
     */
    public static function wrap(mixed $value): static
    {
        if ($value instanceof self) {
            return $value;
        }

        return new static(is_array($value) ? $value : [$value]);
    }

    /**
     * Get all items as array.
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * Get first item matching callback or default.
     */
    public function first(callable $callback = null, mixed $default = null): mixed
    {
        if ($callback === null) {
            return empty($this->items) ? $default : reset($this->items);
        }

        foreach ($this->items as $key => $value) {
            if ($callback($value, $key)) {
                return $value;
            }
        }

        return $default;
    }

    /**
     * Get last item matching callback or default.
     */
    public function last(callable $callback = null, mixed $default = null): mixed
    {
        if ($callback === null) {
            return empty($this->items) ? $default : end($this->items);
        }

        $items = array_reverse($this->items, true);
        foreach ($items as $key => $value) {
            if ($callback($value, $key)) {
                return $value;
            }
        }

        return $default;
    }

    /**
     * Get item at index or default.
     */
    public function nth(int $index, mixed $default = null): mixed
    {
        $values = array_values($this->items);
        return $values[$index] ?? $default;
    }

    /**
     * Map over items.
     */
    public function map(callable $callback): static
    {
        $keys = array_keys($this->items);
        $items = array_map($callback, $this->items, $keys);

        return new static(array_combine($keys, $items));
    }

    /**
     * Map with keys preserved and callback receives value and key.
     */
    public function mapWithKeys(callable $callback): static
    {
        $result = [];

        foreach ($this->items as $key => $value) {
            $assoc = $callback($value, $key);

            foreach ($assoc as $mapKey => $mapValue) {
                $result[$mapKey] = $mapValue;
            }
        }

        return new static($result);
    }

    /**
     * Flat map over items.
     */
    public function flatMap(callable $callback): static
    {
        return $this->map($callback)->flatten(1);
    }

    /**
     * Filter items using callback.
     */
    public function filter(callable $callback = null): static
    {
        if ($callback === null) {
            return new static(array_filter($this->items));
        }

        return new static(array_filter($this->items, $callback, ARRAY_FILTER_USE_BOTH));
    }

    /**
     * Filter and reject items.
     */
    public function reject(callable $callback): static
    {
        return $this->filter(fn($value, $key) => !$callback($value, $key));
    }

    /**
     * Reduce collection to single value.
     */
    public function reduce(callable $callback, mixed $initial = null): mixed
    {
        return array_reduce($this->items, $callback, $initial);
    }

    /**
     * Check if any item passes test.
     */
    public function some(callable $callback): bool
    {
        foreach ($this->items as $key => $value) {
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
        foreach ($this->items as $key => $value) {
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

            return in_array($key, $this->items, true);
        }

        return $this->some(fn($item) => $this->compareValues($item[$key] ?? null, $operator, $value));
    }

    /**
     * Flatten multi-dimensional collection.
     */
    public function flatten(int $depth = INF): static
    {
        $result = [];

        foreach ($this->items as $item) {
            if (!is_array($item) && !$item instanceof self) {
                $result[] = $item;
            } else {
                $values = $item instanceof self ? $item->all() : $item;

                if ($depth === 1) {
                    $result = array_merge($result, array_values($values));
                } else {
                    $result = array_merge($result, (new static($values))->flatten($depth - 1)->all());
                }
            }
        }

        return new static($result);
    }

    /**
     * Get unique items.
     */
    public function unique(string|callable|null $key = null): static
    {
        if ($key === null) {
            return new static(array_unique($this->items, SORT_REGULAR));
        }

        $callback = is_callable($key) ? $key : fn($item) => $item[$key] ?? null;

        $exists = [];
        $items = [];

        foreach ($this->items as $k => $item) {
            $id = $callback($item, $k);

            if (!in_array($id, $exists, true)) {
                $exists[] = $id;
                $items[$k] = $item;
            }
        }

        return new static($items);
    }

    /**
     * Sort collection.
     */
    public function sort(callable $callback = null): static
    {
        $items = $this->items;

        if ($callback === null) {
            asort($items);
        } else {
            uasort($items, $callback);
        }

        return new static($items);
    }

    /**
     * Sort by key.
     */
    public function sortBy(string|callable $callback, bool $descending = false): static
    {
        $callback = is_callable($callback) ? $callback : fn($item) => $item[$callback] ?? null;

        $items = $this->items;
        uasort($items, function ($a, $b) use ($callback, $descending) {
            $result = $callback($a) <=> $callback($b);
            return $descending ? -$result : $result;
        });

        return new static($items);
    }

    /**
     * Sort in descending order.
     */
    public function sortDesc(): static
    {
        return $this->sort(fn($a, $b) => $b <=> $a);
    }

    /**
     * Reverse collection order.
     */
    public function reverse(): static
    {
        return new static(array_reverse($this->items, true));
    }

    /**
     * Take first N items.
     */
    public function take(int $limit): static
    {
        if ($limit < 0) {
            return $this->slice($limit, abs($limit));
        }

        return $this->slice(0, $limit);
    }

    /**
     * Skip first N items.
     */
    public function skip(int $offset): static
    {
        return $this->slice($offset);
    }

    /**
     * Slice collection.
     */
    public function slice(int $offset, ?int $length = null): static
    {
        return new static(array_slice($this->items, $offset, $length, true));
    }

    /**
     * Chunk collection into smaller collections.
     */
    public function chunk(int $size): static
    {
        $chunks = [];

        foreach (array_chunk($this->items, $size, true) as $chunk) {
            $chunks[] = new static($chunk);
        }

        return new static($chunks);
    }

    /**
     * Split collection into N groups.
     */
    public function split(int $numberOfGroups): static
    {
        if ($this->isEmpty()) {
            return new static();
        }

        $groupSize = (int) ceil($this->count() / $numberOfGroups);

        return $this->chunk($groupSize);
    }

    /**
     * Group items by key or callback.
     */
    public function groupBy(string|callable $key): static
    {
        $callback = is_callable($key) ? $key : fn($item) => $item[$key] ?? null;

        $groups = [];

        foreach ($this->items as $k => $item) {
            $groupKey = $callback($item, $k);

            if (!isset($groups[$groupKey])) {
                $groups[$groupKey] = [];
            }

            $groups[$groupKey][$k] = $item;
        }

        return new static(array_map(fn($group) => new static($group), $groups));
    }

    /**
     * Partition into two collections based on callback.
     */
    public function partition(callable $callback): array
    {
        $passed = [];
        $failed = [];

        foreach ($this->items as $key => $value) {
            if ($callback($value, $key)) {
                $passed[$key] = $value;
            } else {
                $failed[$key] = $value;
            }
        }

        return [new static($passed), new static($failed)];
    }

    /**
     * Zip with other collections.
     */
    public function zip(mixed ...$arrays): static
    {
        $arrayableItems = array_map(function ($items) {
            return $items instanceof self ? $items->all() : $items;
        }, $arrays);

        $params = array_merge([array_values($this->items)], array_map(fn($items) => array_values($items), $arrayableItems));

        return new static(array_map(fn(...$items) => new static($items), ...$params));
    }

    /**
     * Merge with other collections.
     */
    public function merge(mixed ...$arrays): static
    {
        $result = $this->items;

        foreach ($arrays as $items) {
            if ($items instanceof self) {
                $items = $items->all();
            }

            $result = array_merge($result, $items);
        }

        return new static($result);
    }

    /**
     * Combine with values.
     */
    public function combine(mixed $values): static
    {
        $values = $values instanceof self ? $values->all() : $values;

        return new static(array_combine($this->items, $values));
    }

    /**
     * Get values only (reset keys).
     */
    public function values(): static
    {
        return new static(array_values($this->items));
    }

    /**
     * Get keys only.
     */
    public function keys(): static
    {
        return new static(array_keys($this->items));
    }

    /**
     * Pluck values by key.
     */
    public function pluck(string $value, ?string $key = null): static
    {
        $results = [];

        foreach ($this->items as $item) {
            $itemValue = is_array($item) ? ($item[$value] ?? null) : ($item->$value ?? null);

            if ($key === null) {
                $results[] = $itemValue;
            } else {
                $itemKey = is_array($item) ? ($item[$key] ?? null) : ($item->$key ?? null);
                $results[$itemKey] = $itemValue;
            }
        }

        return new static($results);
    }

    /**
     * Get min value.
     */
    public function min(string|callable|null $callback = null): mixed
    {
        if ($callback === null) {
            return min($this->items);
        }

        $callback = is_callable($callback) ? $callback : fn($item) => $item[$callback] ?? null;

        return $this->map($callback)->filter()->min();
    }

    /**
     * Get max value.
     */
    public function max(string|callable|null $callback = null): mixed
    {
        if ($callback === null) {
            return max($this->items);
        }

        $callback = is_callable($callback) ? $callback : fn($item) => $item[$callback] ?? null;

        return $this->map($callback)->filter()->max();
    }

    /**
     * Sum values.
     */
    public function sum(string|callable|null $callback = null): int|float
    {
        if ($callback === null) {
            return array_sum($this->items);
        }

        $callback = is_callable($callback) ? $callback : fn($item) => $item[$callback] ?? 0;

        return $this->map($callback)->sum();
    }

    /**
     * Average values.
     */
    public function avg(string|callable|null $callback = null): int|float|null
    {
        $count = $this->count();

        if ($count === 0) {
            return null;
        }

        return $this->sum($callback) / $count;
    }

    /**
     * Median value.
     */
    public function median(string|callable|null $callback = null): int|float|null
    {
        $values = $callback === null
            ? $this->filter(fn($item) => is_numeric($item))
            : $this->map($callback)->filter(fn($item) => is_numeric($item));

        $count = $values->count();

        if ($count === 0) {
            return null;
        }

        $sorted = $values->sort()->values();
        $middle = (int) ($count / 2);

        if ($count % 2 === 0) {
            return ($sorted->nth($middle - 1) + $sorted->nth($middle)) / 2;
        }

        return $sorted->nth($middle);
    }

    /**
     * Mode (most frequent value).
     */
    public function mode(string|callable|null $callback = null): array
    {
        $values = $callback === null ? $this : $this->map($callback);

        $counts = array_count_values($values->all());

        if (empty($counts)) {
            return [];
        }

        $maxCount = max($counts);

        return array_keys(array_filter($counts, fn($count) => $count === $maxCount));
    }

    /**
     * Get item by key or default.
     */
    public function get(mixed $key, mixed $default = null): mixed
    {
        return $this->items[$key] ?? $default;
    }

    /**
     * Check if key exists.
     */
    public function has(mixed $key): bool
    {
        return array_key_exists($key, $this->items);
    }

    /**
     * Get only specified keys.
     */
    public function only(array $keys): static
    {
        return new static(array_intersect_key($this->items, array_flip($keys)));
    }

    /**
     * Get all except specified keys.
     */
    public function except(array $keys): static
    {
        return new static(array_diff_key($this->items, array_flip($keys)));
    }

    /**
     * Count items.
     */
    public function count(): int
    {
        return count($this->items);
    }

    /**
     * Check if collection is empty.
     */
    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    /**
     * Check if collection is not empty.
     */
    public function isNotEmpty(): bool
    {
        return !$this->isEmpty();
    }

    /**
     * Execute callback on each item.
     */
    public function each(callable $callback): static
    {
        foreach ($this->items as $key => $value) {
            if ($callback($value, $key) === false) {
                break;
            }
        }

        return $this;
    }

    /**
     * Pipe collection through callback.
     */
    public function pipe(callable $callback): mixed
    {
        return $callback($this);
    }

    /**
     * Tap into collection without modifying it.
     */
    public function tap(callable $callback): static
    {
        $callback(clone $this);

        return $this;
    }

    /**
     * Apply callback when condition is true.
     */
    public function when(bool $condition, callable $callback, ?callable $default = null): static
    {
        if ($condition) {
            return $callback($this);
        }

        if ($default !== null) {
            return $default($this);
        }

        return $this;
    }

    /**
     * Apply callback unless condition is true.
     */
    public function unless(bool $condition, callable $callback, ?callable $default = null): static
    {
        return $this->when(!$condition, $callback, $default);
    }

    /**
     * Get items as JSON.
     */
    public function toJson(int $options = 0): string
    {
        return json_encode($this->items, $options);
    }

    /**
     * Get iterator.
     */
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->items);
    }

    /**
     * Check if offset exists.
     */
    public function offsetExists(mixed $offset): bool
    {
        return $this->has($offset);
    }

    /**
     * Get item at offset.
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->get($offset);
    }

    /**
     * Set item at offset (throws exception - immutable).
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new \BadMethodCallException('Collection is immutable');
    }

    /**
     * Unset item at offset (throws exception - immutable).
     */
    public function offsetUnset(mixed $offset): void
    {
        throw new \BadMethodCallException('Collection is immutable');
    }

    /**
     * Convert to string.
     */
    public function __toString(): string
    {
        return $this->toJson();
    }

    /**
     * Compare values with operator.
     */
    protected function compareValues(mixed $a, mixed $operator, mixed $b): bool
    {
        return match ($operator) {
            '=' , '==' => $a == $b,
            '===' => $a === $b,
            '!=' , '<>' => $a != $b,
            '!==' => $a !== $b,
            '<' => $a < $b,
            '>' => $a > $b,
            '<=' => $a <= $b,
            '>=' => $a >= $b,
            default => false,
        };
    }

    // ========================================
    // ADVANCED METHODS (Beyond Laravel)
    // ========================================

    /**
     * Create sliding windows of items.
     *
     * Example: [1,2,3,4]->window(2) = [[1,2], [2,3], [3,4]]
     */
    public function window(int $size, int $step = 1): static
    {
        if ($size <= 0) {
            return new static();
        }

        $windows = [];
        $values = array_values($this->items);
        $count = count($values);

        for ($i = 0; $i <= $count - $size; $i += $step) {
            $windows[] = new static(array_slice($values, $i, $size));
        }

        return new static($windows);
    }

    /**
     * Get pairs of consecutive items.
     *
     * Example: [1,2,3,4]->pairs() = [[1,2], [2,3], [3,4]]
     */
    public function pairs(): static
    {
        return $this->window(2, 1);
    }

    /**
     * Transpose matrix (2D collection).
     *
     * Example: [[1,2], [3,4]]->transpose() = [[1,3], [2,4]]
     */
    public function transpose(): static
    {
        if ($this->isEmpty()) {
            return new static();
        }

        $items = array_map(function ($items) {
            return $items instanceof self ? $items->all() : $items;
        }, $this->items);

        $result = [];
        $maxLength = max(array_map('count', $items));

        for ($i = 0; $i < $maxLength; $i++) {
            $result[] = new static(array_column($items, $i));
        }

        return new static($result);
    }

    /**
     * Get cartesian product with other collections.
     *
     * Example: [1,2]->crossJoin([3,4]) = [[1,3], [1,4], [2,3], [2,4]]
     */
    public function crossJoin(mixed ...$arrays): static
    {
        $results = [[]];

        $arrays = array_merge([$this->all()], array_map(function ($items) {
            return $items instanceof self ? $items->all() : $items;
        }, $arrays));

        foreach ($arrays as $index => $array) {
            $append = [];

            foreach ($results as $product) {
                foreach ($array as $item) {
                    $product[$index] = $item;
                    $append[] = $product;
                }
            }

            $results = $append;
        }

        return new static(array_map(fn($result) => new static($result), $results));
    }

    /**
     * Diff with other collection.
     */
    public function diff(mixed $items): static
    {
        $items = $items instanceof self ? $items->all() : $items;

        return new static(array_diff($this->items, $items));
    }

    /**
     * Diff keys with other collection.
     */
    public function diffKeys(mixed $items): static
    {
        $items = $items instanceof self ? $items->all() : $items;

        return new static(array_diff_key($this->items, $items));
    }

    /**
     * Intersect with other collection.
     */
    public function intersect(mixed $items): static
    {
        $items = $items instanceof self ? $items->all() : $items;

        return new static(array_intersect($this->items, $items));
    }

    /**
     * Intersect keys with other collection.
     */
    public function intersectKeys(mixed $items): static
    {
        $items = $items instanceof self ? $items->all() : $items;

        return new static(array_intersect_key($this->items, $items));
    }

    /**
     * Union with other collection.
     */
    public function union(mixed $items): static
    {
        $items = $items instanceof self ? $items->all() : $items;

        return new static($this->items + $items);
    }

    /**
     * Pad collection to specified size.
     */
    public function pad(int $size, mixed $value): static
    {
        return new static(array_pad($this->items, $size, $value));
    }

    /**
     * Get random item(s).
     */
    public function random(int $number = 1): mixed
    {
        $count = $this->count();

        if ($number > $count) {
            throw new \InvalidArgumentException(
                "Cannot get {$number} random items from collection with {$count} items"
            );
        }

        if ($number === 1) {
            return $this->items[array_rand($this->items)];
        }

        $keys = array_rand($this->items, $number);
        $keys = is_array($keys) ? $keys : [$keys];

        return new static(array_intersect_key($this->items, array_flip($keys)));
    }

    /**
     * Shuffle items.
     */
    public function shuffle(): static
    {
        $items = $this->items;
        shuffle($items);

        return new static($items);
    }

    /**
     * Apply callback N times.
     */
    public function pipe_times(int $times, callable $callback): static
    {
        $result = $this;

        for ($i = 0; $i < $times; $i++) {
            $result = $callback($result, $i);
        }

        return $result;
    }

    /**
     * Sliding reduce (apply reduce with sliding window).
     */
    public function scanl(callable $callback, mixed $initial = null): static
    {
        $results = [$initial];
        $accumulator = $initial;

        foreach ($this->items as $key => $value) {
            $accumulator = $callback($accumulator, $value, $key);
            $results[] = $accumulator;
        }

        return new static($results);
    }

    /**
     * Take items while callback returns true.
     */
    public function takeWhile(callable $callback): static
    {
        $result = [];

        foreach ($this->items as $key => $value) {
            if (!$callback($value, $key)) {
                break;
            }

            $result[$key] = $value;
        }

        return new static($result);
    }

    /**
     * Take items until callback returns true.
     */
    public function takeUntil(callable $callback): static
    {
        return $this->takeWhile(fn($value, $key) => !$callback($value, $key));
    }

    /**
     * Skip items while callback returns true.
     */
    public function skipWhile(callable $callback): static
    {
        $shouldTake = false;
        $result = [];

        foreach ($this->items as $key => $value) {
            if (!$shouldTake && !$callback($value, $key)) {
                $shouldTake = true;
            }

            if ($shouldTake) {
                $result[$key] = $value;
            }
        }

        return new static($result);
    }

    /**
     * Skip items until callback returns true.
     */
    public function skipUntil(callable $callback): static
    {
        return $this->skipWhile(fn($value, $key) => !$callback($value, $key));
    }

    /**
     * Get duplicates.
     */
    public function duplicates(string|callable|null $key = null): static
    {
        $callback = is_callable($key) ? $key : fn($item) => $key === null ? $item : ($item[$key] ?? null);

        $counts = [];
        $duplicates = [];

        foreach ($this->items as $k => $item) {
            $value = $callback($item, $k);

            if (!isset($counts[$value])) {
                $counts[$value] = 0;
            }

            $counts[$value]++;

            if ($counts[$value] === 2) {
                $duplicates[$k] = $item;
            }
        }

        return new static($duplicates);
    }

    /**
     * Ensure all items are unique (throw exception if duplicates).
     */
    public function ensureUnique(string|callable|null $key = null): static
    {
        $duplicates = $this->duplicates($key);

        if ($duplicates->isNotEmpty()) {
            throw new \RuntimeException('Collection contains duplicate items');
        }

        return $this;
    }

    /**
     * Count occurrences of each value.
     */
    public function countBy(string|callable|null $callback = null): static
    {
        if ($callback === null) {
            return new static(array_count_values($this->items));
        }

        $callback = is_callable($callback) ? $callback : fn($item) => $item[$callback] ?? null;

        $counts = [];

        foreach ($this->items as $key => $value) {
            $group = $callback($value, $key);

            if (!isset($counts[$group])) {
                $counts[$group] = 0;
            }

            $counts[$group]++;
        }

        return new static($counts);
    }

    /**
     * Frequency analysis (get most/least common items).
     */
    public function frequencies(): static
    {
        $counts = $this->countBy();

        return $counts->map(fn($count, $value) => [
            'value' => $value,
            'count' => $count,
            'percentage' => ($count / $this->count()) * 100
        ])->sortBy('count', true);
    }

    /**
     * Sliding aggregate (like moving average).
     */
    public function sliding(int $windowSize, callable $aggregator): static
    {
        return $this->window($windowSize)->map($aggregator);
    }

    /**
     * Moving average.
     */
    public function movingAverage(int $windowSize): static
    {
        return $this->sliding($windowSize, fn($window) => $window->avg());
    }

    /**
     * Create paginator result.
     */
    public function paginate(int $perPage, int $page = 1): array
    {
        $offset = ($page - 1) * $perPage;
        $items = $this->slice($offset, $perPage);
        $total = $this->count();

        return [
            'data' => $items,
            'current_page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => (int) ceil($total / $perPage),
            'from' => $offset + 1,
            'to' => min($offset + $perPage, $total),
        ];
    }

    /**
     * Recursive map (for nested arrays/collections).
     */
    public function mapRecursive(callable $callback): static
    {
        $map = function ($items) use ($callback, &$map) {
            $result = [];

            foreach ($items as $key => $value) {
                if (is_array($value) || $value instanceof self) {
                    $value = $map($value instanceof self ? $value->all() : $value);
                    $result[$key] = new static($value);
                } else {
                    $result[$key] = $callback($value, $key);
                }
            }

            return $result;
        };

        return new static($map($this->items));
    }

    /**
     * Deep flatten (recursive).
     */
    public function flattenDeep(): static
    {
        return $this->flatten(PHP_INT_MAX);
    }

    /**
     * Search for value and return key.
     */
    public function search(mixed $value, bool $strict = false): mixed
    {
        if (is_callable($value)) {
            foreach ($this->items as $key => $item) {
                if ($value($item, $key)) {
                    return $key;
                }
            }

            return false;
        }

        return array_search($value, $this->items, $strict);
    }

    /**
     * Replace items by key.
     */
    public function replace(mixed $items): static
    {
        $items = $items instanceof self ? $items->all() : $items;

        return new static(array_replace($this->items, $items));
    }

    /**
     * Replace recursively.
     */
    public function replaceRecursive(mixed $items): static
    {
        $items = $items instanceof self ? $items->all() : $items;

        return new static(array_replace_recursive($this->items, $items));
    }

    /**
     * Sole item (get single item or throw exception).
     */
    public function sole(callable $callback = null): mixed
    {
        $items = $callback === null ? $this : $this->filter($callback);

        $count = $items->count();

        if ($count === 0) {
            throw new \RuntimeException('No items found');
        }

        if ($count > 1) {
            throw new \RuntimeException('Multiple items found');
        }

        return $items->first();
    }

    /**
     * First or throw exception.
     */
    public function firstOrFail(callable $callback = null): mixed
    {
        $item = $this->first($callback);

        if ($item === null) {
            throw new \RuntimeException('No items found');
        }

        return $item;
    }
}
