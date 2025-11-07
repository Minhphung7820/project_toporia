<?php
namespace Framework\Application\UseCase;

abstract class AbstractHandler
{
    /** Execute a use case. */
    abstract public function __invoke(object $message): mixed;
}
