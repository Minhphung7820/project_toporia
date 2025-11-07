<?php
namespace Framework\Support;

final class Result
{
    private function __construct(private mixed $ok, private ?\Throwable $err) {}

    public static function ok(mixed $v): self { return new self($v, null); }
    public static function err(\Throwable $e): self { return new self(null, $e); }

    public function isOk(): bool { return $this->err === null; }
    public function isErr(): bool { return $this->err !== null; }
    public function unwrap(): mixed { if ($this->isErr()) throw $this->err; return $this->ok; }

    public function match(callable $onOk, callable $onErr): mixed
    {
        return $this->isOk() ? $onOk($this->ok) : $onErr($this->err);
    }
}
