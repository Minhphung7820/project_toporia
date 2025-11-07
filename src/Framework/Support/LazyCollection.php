<?php

declare(strict_types=1);

namespace Framework\Support;

use ArrayAccess;
use ArrayIterator;
use Countable;
use Generator;
use InvalidArgumentException;
use IteratorAggregate;
use Traversable;

/**
 * LazyCollection - Memory-efficient collection using generators.
 *
 * Multi-pass safe: mỗi lần iterate sẽ tạo Generator mới.
 * Vẫn giữ style hiện tại: chunk()/zip() yield Collection eager.
 */
class LazyCollection implements IteratorAggregate, Countable
{
    /** @var callable():Generator */
    protected $producer;

    /**
     * @param callable|Generator|Traversable|array $source
     */
    public function __construct(protected mixed $source = [])
    {
        // Chuẩn hoá thành producer closure để đảm bảo multi-pass safe.
        $this->producer = $this->normalizeToProducer($source);
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
        if ($step === 0) {
            throw new InvalidArgumentException('Step cannot be 0.');
        }

        return new static(function () use ($start, $end, $step) {
            if ($step > 0) {
                for ($i = $start; $i <= $end; $i += $step) {
                    yield $i;
                }
            } else {
                for ($i = $start; $i >= $end; $i += $step) {
                    yield $i;
                }
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
     * IteratorAggregate
     */
    public function getIterator(): Traversable
    {
        $p = $this->producer;
        return $p();
    }

    /**
     * Internal: chuẩn hoá $source thành producer closure tạo Generator mới mỗi lần.
     * - Nếu $source là Generator (one-shot), ta wrap thành closure "remember on first pass":
     *   + Lần đầu: consume generator -> cache -> yield
     *   + Lần sau: yield từ cache (multi-pass safe mà vẫn lazy ở pass đầu)
     */
    protected function normalizeToProducer(mixed $source): callable
    {
        // callable: gọi để lấy iterable/Generator mới mỗi lần
        if (is_callable($source) && !($source instanceof Traversable)) {
            return static function () use ($source): Generator {
                $it = $source();
                yield from LazyCollection::iterToGenerator($it);
            };
        }

        // Generator: one-shot -> wrap với cache để pass sau vẫn chạy được
        if ($source instanceof Generator) {
            $cache = [];
            $consumed = false;

            return static function () use (&$cache, &$consumed, $source): Generator {
                if (!$consumed) {
                    foreach ($source as $k => $v) {
                        $cache[$k] = $v;
                        yield $k => $v;
                    }
                    $consumed = true;
                    return;
                }
                // Pass 2+: dùng cache
                foreach ($cache as $k => $v) {
                    yield $k => $v;
                }
            };
        }

        // Traversable (Iterator, ArrayIterator...): tạo mới nếu có thể, nếu không thì wrap với remember nhẹ
        if ($source instanceof Traversable) {
            return static function () use ($source): Generator {
                // Nếu Traversable không rewindable, foreach sẽ fail ở pass 2 — ta "best effort" convert sang ArrayIterator
                if (method_exists($source, 'rewind')) {
                    foreach ($source as $k => $v) {
                        yield $k => $v;
                    }
                    return;
                }
                // Fallback: copy sang array một lần (đánh đổi bộ nhớ) để multi-pass
                $arr = iterator_to_array($source, true);
                foreach ($arr as $k => $v) {
                    yield $k => $v;
                }
            };
        }

        if (is_array($source)) {
            return static function () use ($source): Generator {
                foreach ($source as $k => $v) {
                    yield $k => $v;
                }
            };
        }

        throw new InvalidArgumentException('Source must be iterable/generator/callable producing iterable.');
    }

    /**
     * Utility: convert iterable|mixed -> Generator
     */
    protected static function iterToGenerator(mixed $iter): Generator
    {
        if ($iter instanceof Generator) {
            yield from $iter;
            return;
        }
        if ($iter instanceof Traversable) {
            foreach ($iter as $k => $v) {
                yield $k => $v;
            }
            return;
        }
        if (is_array($iter)) {
            foreach ($iter as $k => $v) {
                yield $k => $v;
            }
            return;
        }
        // single value
        yield $iter;
    }

    /**
     * Map over items lazily.
     */
    public function map(callable $callback): static
    {
        return new static(function () use ($callback) {
            foreach ($this->getIterator() as $key => $value) {
                yield $key => $callback($value, $key);
            }
        });
    }

    /**
     * Flat map (map rồi mở phẳng 1 tầng).
     */
    public function flatMap(callable $callback): static
    {
        return new static(function () use ($callback) {
            foreach ($this->getIterator() as $key => $value) {
                $out = $callback($value, $key);
                if ($out instanceof Traversable) {
                    foreach ($out as $k => $v) yield $k => $v;
                } elseif (is_array($out)) {
                    foreach ($out as $k => $v) yield $k => $v;
                } elseif ($out !== null) {
                    yield $key => $out;
                }
            }
        });
    }

    /**
     * Concat các iterable khác vào sau.
     */
    public function concat(mixed ...$iters): static
    {
        return new static(function () use ($iters) {
            foreach ($this->getIterator() as $k => $v) yield $k => $v;
            foreach ($iters as $it) {
                foreach (self::iterToGenerator($it) as $k => $v) {
                    yield $k => $v;
                }
            }
        });
    }

    /**
     * Filter items lazily.
     */
    public function filter(callable $callback = null): static
    {
        return new static(function () use ($callback) {
            foreach ($this->getIterator() as $key => $value) {
                if ($callback === null) {
                    if ($value) yield $key => $value;
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
        if ($limit < 0) throw new InvalidArgumentException('take() requires limit >= 0');
        return new static(function () use ($limit) {
            if ($limit === 0) return;
            $count = 0;
            foreach ($this->getIterator() as $key => $value) {
                yield $key => $value;
                if (++$count >= $limit) break;
            }
        });
    }

    /**
     * Take while condition is true.
     */
    public function takeWhile(callable $callback): static
    {
        return new static(function () use ($callback) {
            foreach ($this->getIterator() as $key => $value) {
                if (!$callback($value, $key)) break;
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
        if ($offset < 0) throw new InvalidArgumentException('skip() requires offset >= 0');
        return new static(function () use ($offset) {
            $count = 0;
            foreach ($this->getIterator() as $key => $value) {
                if ($count++ < $offset) continue;
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
            $taking = false;
            foreach ($this->getIterator() as $key => $value) {
                if (!$taking && !$callback($value, $key)) {
                    $taking = true;
                }
                if ($taking) yield $key => $value;
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
     * Unique items (O(1) lookup).
     * - $key: null => item chính nó; string => lấy theo key/prop; callable($item,$k) => id bất kỳ
     */
    public function unique(string|callable|null $key = null): static
    {
        return new static(function () use ($key) {
            $callback = is_callable($key)
                ? $key
                : function ($item) use ($key) {
                    if ($key === null) return $item;
                    // Lấy theo key hoặc property
                    if (is_array($item) && array_key_exists($key, $item)) return $item[$key];
                    if (is_object($item)) {
                        if (isset($item->{$key})) return $item->{$key};
                        $getter = 'get' . str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $key)));
                        if (method_exists($item, $getter)) return $item->{$getter}();
                    }
                    return null;
                };

            $seen = [];
            foreach ($this->getIterator() as $k => $item) {
                $id = self::normalizeKey($callback($item, $k));
                if (!array_key_exists($id, $seen)) {
                    $seen[$id] = true;
                    yield $k => $item;
                }
            }
        });
    }

    /**
     * Chunk into collections (eager chunk).
     */
    public function chunk(int $size): static
    {
        if ($size <= 0) throw new InvalidArgumentException('chunk() requires size > 0');
        return new static(function () use ($size) {
            $chunk = [];
            foreach ($this->getIterator() as $key => $value) {
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
            foreach ($this->getIterator() as $item) {
                if (!is_array($item) && !$item instanceof Collection) {
                    yield $item;
                } elseif ($depth === 1) {
                    $values = $item instanceof Collection ? $item->all() : $item;
                    yield from array_values($values);
                } else {
                    $values = $item instanceof Collection ? $item->all() : $item;
                    yield from (new static($values))->flatten($depth - 1)->getIterator();
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
            foreach ($this->getIterator() as $key => $value) {
                $callback($value, $key);
                yield $key => $value;
            }
        });
    }

    /**
     * Execute callback on each item (terminal).
     */
    public function each(callable $callback): static
    {
        foreach ($this->getIterator() as $key => $value) {
            if ($callback($value, $key) === false) break;
        }
        return $this;
    }

    /**
     * Reduce to single value (terminal).
     */
    public function reduce(callable $callback, mixed $initial = null): mixed
    {
        $acc = $initial;
        foreach ($this->getIterator() as $key => $value) {
            $acc = $callback($acc, $value, $key);
        }
        return $acc;
    }

    /**
     * Check if any item passes test (terminal).
     */
    public function some(callable $callback): bool
    {
        foreach ($this->getIterator() as $key => $value) {
            if ($callback($value, $key)) return true;
        }
        return false;
    }

    /**
     * Check if all items pass test (terminal).
     */
    public function every(callable $callback): bool
    {
        foreach ($this->getIterator() as $key => $value) {
            if (!$callback($value, $key)) return false;
        }
        return true;
    }

    /**
     * Check if collection contains item (terminal).
     * - contains(fn($item)=>...)
     * - contains($needle)
     * - contains($key, $op, $value) | supports array/object
     */
    public function contains(mixed $key, mixed $operator = null, mixed $value = null): bool
    {
        if (func_num_args() === 1) {
            if (is_callable($key)) {
                return $this->some($key);
            }
            foreach ($this->getIterator() as $item) {
                if ($item === $key) return true;
            }
            return false;
        }

        return $this->some(function ($item) use ($key, $operator, $value) {
            $left = self::getValueByKey($item, $key);
            return $this->compareValues($left, $operator, $value);
        });
    }

    /**
     * Get first item (terminal).
     */
    public function first(callable $callback = null, mixed $default = null): mixed
    {
        foreach ($this->getIterator() as $key => $value) {
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
        // dùng iterator_to_array sẽ giữ key; true = preserve keys
        return iterator_to_array($this->getIterator(), true);
    }

    /**
     * Values only (reindex).
     */
    public function values(): static
    {
        return new static(function () {
            foreach ($this->getIterator() as $value) yield $value;
        });
    }

    /**
     * Keys only.
     */
    public function keys(): static
    {
        return new static(function () {
            foreach ($this->getIterator() as $key => $value) yield $key;
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
     * Count items (terminal; cẩn trọng với stream vô hạn).
     */
    public function count(): int
    {
        $c = 0;
        foreach ($this->getIterator() as $_) $c++;
        return $c;
    }

    public function isEmpty(): bool
    {
        foreach ($this->getIterator() as $_) return false;
        return true;
    }

    public function isNotEmpty(): bool
    {
        return !$this->isEmpty();
    }

    /**
     * Zip with other iterables — dừng khi 1 bên hết (yield Collection)
     */
    public function zip(mixed ...$arrays): static
    {
        return new static(function () use ($arrays) {
            // Chuẩn hoá thành Iterator (ArrayIterator/Generator/Traversable)
            $iterators = array_map(function ($items) {
                if ($items instanceof Traversable) return $items;
                if (is_array($items)) return new ArrayIterator($items);
                // single value
                return new ArrayIterator([$items]);
            }, array_merge([$this->getIterator()], $arrays));

            // Prime tất cả
            foreach ($iterators as $it) {
                if (method_exists($it, 'rewind')) $it->rewind();
            }

            while (true) {
                $row = [];
                foreach ($iterators as $it) {
                    if (!$it->valid()) {
                        return; // stop khi 1 iterator hết
                    }
                    $row[] = $it->current();
                }
                // advance tất cả sau khi đọc current
                foreach ($iterators as $it) {
                    $it->next();
                }
                yield new Collection($row);
            }
        });
    }

    /**
     * Remember items (cache in memory for multi-pass).
     */
    public function remember(): static
    {
        $cache = [];
        $sourceProducer = $this->producer;

        return new static(function () use (&$cache, $sourceProducer) {
            // yield cache trước
            foreach ($cache as $k => $v) yield $k => $v;

            // sau đó consume source và fill cache
            foreach ($sourceProducer() as $k => $v) {
                $cache[$k] = $v;
                yield $k => $v;
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

    /**
     * Lấy giá trị theo key từ array/ArrayAccess/object (support getter).
     */
    protected static function getValueByKey(mixed $item, string|int $key): mixed
    {
        if (is_array($item)) {
            return $item[$key] ?? null;
        }

        if ($item instanceof ArrayAccess) {
            return $item[$key] ?? null;
        }

        if (is_object($item)) {
            if (isset($item->{$key}) || property_exists($item, (string)$key)) {
                return $item->{$key};
            }
            $getter = 'get' . str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', (string)$key)));
            if (method_exists($item, $getter)) {
                return $item->{$getter}();
            }
        }

        return null;
    }

    /**
     * Chuẩn hoá key cho unique(): scalar => cast; array/object => json_encode stable.
     */
    protected static function normalizeKey(mixed $id): string
    {
        if (is_null($id)) return 'null';
        if (is_bool($id)) return $id ? 'true' : 'false';
        if (is_int($id) || is_float($id) || is_string($id)) return (string)$id;
        // array/object: stable json
        return json_encode($id, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
    }
}
