<?php
namespace Framework\Domain;

abstract class ValueObject
{
    public function equals(self $other): bool
    {
        return $this->__toString() === (string)$other;
    }
    abstract public function __toString(): string;
}
