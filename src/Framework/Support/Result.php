<?php

declare(strict_types=1);

namespace Framework\Support;

/**
 * Result type for functional error handling.
 *
 * Represents either a successful value (Ok) or an error (Err).
 * Provides a type-safe alternative to exceptions for expected errors.
 *
 * Benefits:
 * - Forces explicit error handling
 * - Makes error paths visible in code
 * - Enables functional composition
 * - No hidden control flow like exceptions
 *
 * Usage:
 * ```php
 * function divide(float $a, float $b): Result
 * {
 *     if ($b === 0.0) {
 *         return Result::err(new \DivisionByZeroError());
 *     }
 *     return Result::ok($a / $b);
 * }
 *
 * $result = divide(10, 2);
 * $value = $result->unwrapOr(0); // 5
 *
 * // With mapping
 * $result = divide(10, 2)
 *     ->map(fn($v) => $v * 2)
 *     ->map(fn($v) => "Result: {$v}");
 *
 * // With pattern matching
 * $message = $result->match(
 *     onOk: fn($v) => "Success: {$v}",
 *     onErr: fn($e) => "Error: {$e->getMessage()}"
 * );
 *
 * // Combining multiple results
 * $results = Result::all([
 *     divide(10, 2),
 *     divide(20, 4),
 *     divide(30, 6)
 * ]);
 * // Returns Ok([5, 5, 5]) or Err(first error)
 * ```
 */
final class Result
{
    /**
     * @param mixed $ok Success value.
     * @param \Throwable|null $err Error value.
     */
    private function __construct(
        private mixed $ok,
        private ?\Throwable $err
    ) {
    }

    /**
     * Create a successful Result.
     *
     * @param mixed $value Success value.
     * @return self Result containing success value.
     */
    public static function ok(mixed $value): self
    {
        return new self($value, null);
    }

    /**
     * Create an error Result.
     *
     * @param \Throwable $error Error value.
     * @return self Result containing error.
     */
    public static function err(\Throwable $error): self
    {
        return new self(null, $error);
    }

    /**
     * Wrap a callable that might throw exceptions.
     *
     * @param callable $callback Callback to execute.
     * @return self Result containing callback result or exception.
     */
    public static function from(callable $callback): self
    {
        try {
            return self::ok($callback());
        } catch (\Throwable $e) {
            return self::err($e);
        }
    }

    /**
     * Combine multiple Results into one.
     *
     * Returns Ok with array of all values if all are Ok.
     * Returns Err with first error if any are Err.
     *
     * @param array<self> $results Array of Results.
     * @return self Result containing array of values or first error.
     */
    public static function all(array $results): self
    {
        $values = [];

        foreach ($results as $result) {
            if ($result->isErr()) {
                return $result;
            }
            $values[] = $result->unwrap();
        }

        return self::ok($values);
    }

    /**
     * Check if Result is Ok.
     *
     * @return bool True if Ok, false if Err.
     */
    public function isOk(): bool
    {
        return $this->err === null;
    }

    /**
     * Check if Result is Err.
     *
     * @return bool True if Err, false if Ok.
     */
    public function isErr(): bool
    {
        return $this->err !== null;
    }

    /**
     * Unwrap the Ok value.
     *
     * @return mixed The Ok value.
     * @throws \Throwable If Result is Err.
     */
    public function unwrap(): mixed
    {
        if ($this->isErr()) {
            throw $this->err;
        }

        return $this->ok;
    }

    /**
     * Unwrap the Err value.
     *
     * @return \Throwable The error.
     * @throws \LogicException If Result is Ok.
     */
    public function unwrapErr(): \Throwable
    {
        if ($this->isOk()) {
            throw new \LogicException('Cannot unwrapErr on Ok Result');
        }

        return $this->err;
    }

    /**
     * Unwrap the value or return a default.
     *
     * @param mixed $default Default value if Err.
     * @return mixed The Ok value or default.
     */
    public function unwrapOr(mixed $default): mixed
    {
        return $this->isOk() ? $this->ok : $default;
    }

    /**
     * Unwrap the value or compute a default.
     *
     * @param callable $callback Callback to compute default value.
     * @return mixed The Ok value or computed default.
     */
    public function unwrapOrElse(callable $callback): mixed
    {
        return $this->isOk() ? $this->ok : $callback($this->err);
    }

    /**
     * Transform the Ok value.
     *
     * @param callable $callback Transformation function.
     * @return self New Result with transformed value or original error.
     */
    public function map(callable $callback): self
    {
        if ($this->isErr()) {
            return $this;
        }

        return self::ok($callback($this->ok));
    }

    /**
     * Transform the Err value.
     *
     * @param callable $callback Transformation function.
     * @return self New Result with original value or transformed error.
     */
    public function mapErr(callable $callback): self
    {
        if ($this->isOk()) {
            return $this;
        }

        return self::err($callback($this->err));
    }

    /**
     * Chain operations that return Results.
     *
     * Also known as flatMap or bind.
     *
     * @param callable $callback Function that returns a Result.
     * @return self Result from callback or original error.
     */
    public function andThen(callable $callback): self
    {
        if ($this->isErr()) {
            return $this;
        }

        return $callback($this->ok);
    }

    /**
     * Execute side effect if Ok.
     *
     * @param callable $callback Side effect function.
     * @return self Original Result for chaining.
     */
    public function tap(callable $callback): self
    {
        if ($this->isOk()) {
            $callback($this->ok);
        }

        return $this;
    }

    /**
     * Execute side effect if Err.
     *
     * @param callable $callback Side effect function.
     * @return self Original Result for chaining.
     */
    public function tapErr(callable $callback): self
    {
        if ($this->isErr()) {
            $callback($this->err);
        }

        return $this;
    }

    /**
     * Pattern match on Result.
     *
     * @param callable $onOk Handler for Ok case.
     * @param callable $onErr Handler for Err case.
     * @return mixed Result of the appropriate handler.
     */
    public function match(callable $onOk, callable $onErr): mixed
    {
        return $this->isOk() ? $onOk($this->ok) : $onErr($this->err);
    }

    /**
     * Convert Result to array representation.
     *
     * @return array{ok?: mixed, err?: \Throwable} Array with ok or err key.
     */
    public function toArray(): array
    {
        return $this->isOk()
            ? ['ok' => $this->ok]
            : ['err' => $this->err];
    }
}
