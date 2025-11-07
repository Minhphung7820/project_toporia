<?php
namespace Framework\Domain;

abstract class Entity
{
    /** @var array<string,mixed> */
    protected array $attributes = [];

    public function __get(string $key) { return $this->attributes[$key] ?? null; }
    public function toArray(): array { return $this->attributes; }
}
