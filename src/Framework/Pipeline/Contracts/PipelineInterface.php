<?php

declare(strict_types=1);

namespace Toporia\Framework\Pipeline\Contracts;

use Closure;

/**
 * Pipeline interface for processing data through multiple stages.
 *
 * Defines the contract for pipeline implementations that allow
 * data to flow through a series of processing stages (pipes).
 */
interface PipelineInterface
{
    /**
     * Set the object being sent through the pipeline.
     *
     * @param mixed $passable The object to process
     * @return self
     */
    public function send(mixed $passable): self;

    /**
     * Set the array of pipes.
     *
     * @param array<int, mixed> $pipes Array of pipes (callables, classes, or objects)
     * @return self
     */
    public function through(array $pipes): self;

    /**
     * Add a pipe to the pipeline.
     *
     * @param mixed $pipe Pipe (callable, class name, or object)
     * @return self
     */
    public function pipe(mixed $pipe): self;

    /**
     * Set the method to call on pipe objects.
     *
     * @param string $method Method name (default: 'handle')
     * @return self
     */
    public function via(string $method): self;

    /**
     * Run the pipeline with a final destination callback.
     *
     * @param Closure $destination Final callback to execute after all pipes
     * @return mixed Result from destination callback
     */
    public function then(Closure $destination): mixed;

    /**
     * Run the pipeline and return the result.
     *
     * Equivalent to ->then(fn($passable) => $passable)
     *
     * @return mixed The processed passable
     */
    public function thenReturn(): mixed;
}
