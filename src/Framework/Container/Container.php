<?php
namespace Framework\Container;

final class Container
{
    private array $bindings = [];
    private array $instances = [];

    public function bind(string $id, callable $factory): void
    {
        $this->bindings[$id] = $factory;
    }

    public function singleton(string $id, callable $factory): void
    {
        $this->bindings[$id] = $factory;
        $this->instances[$id] = null; // mark as singleton
    }

    public function make(string $id)
    {
        if (array_key_exists($id, $this->instances)) {
            if ($this->instances[$id] === null) {
                $this->instances[$id] = $this->bindings[$id]($this);
            }
            return $this->instances[$id];
        }
        if (!isset($this->bindings[$id])) {
            if (class_exists($id)) { return new $id(); }
            throw new \RuntimeException("Service '$id' not bound");
        }
        return ($this->bindings[$id])($this);
    }
}
