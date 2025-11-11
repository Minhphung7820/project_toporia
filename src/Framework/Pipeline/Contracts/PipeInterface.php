<?php

declare(strict_types=1);

namespace Toporia\Framework\Pipeline\Contracts;

use Closure;

/**
 * Pipe interface for pipeline stages.
 *
 * Defines the contract for pipe implementations that process
 * data within a pipeline.
 */
interface PipeInterface
{
    /**
     * Handle the data through the pipe.
     *
     * @param mixed $passable The data being passed through the pipeline
     * @param Closure $next The next pipe in the pipeline
     * @return mixed The processed data
     */
    public function handle(mixed $passable, Closure $next): mixed;
}
