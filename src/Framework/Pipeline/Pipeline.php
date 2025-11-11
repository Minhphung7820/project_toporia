<?php

declare(strict_types=1);

namespace Toporia\Framework\Pipeline;

use Closure;
use Toporia\Framework\Container\ContainerInterface;
use Toporia\Framework\Pipeline\Contracts\PipelineInterface;

/**
 * General-purpose Pipeline for passing data through multiple stages.
 *
 * Laravel-compatible pipeline implementation for chainable operations.
 * Perfect for filtering, transforming, validating data through multiple steps.
 *
 * Features:
 * - Pipe through callbacks (closures)
 * - Pipe through invokable objects
 * - Pipe through class methods (Class@method)
 * - Container-based dependency injection
 * - Fluent chainable API
 * - Then/thenReturn for flexible output
 *
 * Performance:
 * - O(N) where N = number of pipes
 * - Lazy evaluation (pipeline built once, executed once)
 * - Zero overhead for unused features
 *
 * Example:
 * ```php
 * $result = Pipeline::make($container)
 *     ->send($user)
 *     ->through([
 *         ValidateUser::class,
 *         NormalizeData::class,
 *         function($user, $next) {
 *             $user->verified = true;
 *             return $next($user);
 *         }
 *     ])
 *     ->thenReturn();
 * ```
 */
final class Pipeline implements PipelineInterface
{
    /**
     * @var mixed The object being passed through the pipeline
     */
    private mixed $passable;

    /**
     * @var array<int, mixed> Array of pipes (callables, class names, or objects)
     */
    private array $pipes = [];

    /**
     * @var string Method name to call on pipe objects
     */
    private string $method = 'handle';

    /**
     * @param ContainerInterface|null $container DI container for resolving pipes
     */
    public function __construct(
        private ?ContainerInterface $container = null
    ) {}

    /**
     * Create a new pipeline instance.
     *
     * @param ContainerInterface|null $container DI container
     * @return self
     */
    public static function make(?ContainerInterface $container = null): self
    {
        return new self($container);
    }

    /**
     * Set the object being sent through the pipeline.
     *
     * @param mixed $passable The object to process
     * @return self
     */
    public function send(mixed $passable): self
    {
        $this->passable = $passable;
        return $this;
    }

    /**
     * Set the array of pipes.
     *
     * @param array<int, mixed> $pipes Array of pipes (callables, classes, or objects)
     * @return self
     */
    public function through(array $pipes): self
    {
        $this->pipes = $pipes;
        return $this;
    }

    /**
     * Add a pipe to the pipeline.
     *
     * @param mixed $pipe Pipe (callable, class name, or object)
     * @return self
     */
    public function pipe(mixed $pipe): self
    {
        $this->pipes[] = $pipe;
        return $this;
    }

    /**
     * Set the method to call on pipe objects.
     *
     * @param string $method Method name (default: 'handle')
     * @return self
     */
    public function via(string $method): self
    {
        $this->method = $method;
        return $this;
    }

    /**
     * Run the pipeline with a final destination callback.
     *
     * @param Closure $destination Final callback to execute after all pipes
     * @return mixed Result from destination callback
     */
    public function then(Closure $destination): mixed
    {
        $pipeline = $this->buildPipeline($destination);
        return $pipeline($this->passable);
    }

    /**
     * Run the pipeline and return the result.
     *
     * Equivalent to ->then(fn($passable) => $passable)
     *
     * @return mixed The processed passable
     */
    public function thenReturn(): mixed
    {
        return $this->then(fn($passable) => $passable);
    }

    /**
     * Build the pipeline with all pipes.
     *
     * Builds pipeline in reverse order (onion pattern) so pipes execute
     * in declaration order.
     *
     * @param Closure $destination Final destination callback
     * @return Closure Pipeline function
     */
    private function buildPipeline(Closure $destination): Closure
    {
        // Start with destination as innermost layer
        $pipeline = $destination;

        // Wrap each pipe around the pipeline (reverse order for correct execution)
        foreach (array_reverse($this->pipes) as $pipe) {
            $pipeline = $this->wrapPipe($pipe, $pipeline);
        }

        return $pipeline;
    }

    /**
     * Wrap a single pipe around the next layer.
     *
     * @param mixed $pipe Pipe (callable, class name, or object)
     * @param Closure $next Next layer in the pipeline
     * @return Closure Wrapped function
     */
    private function wrapPipe(mixed $pipe, Closure $next): Closure
    {
        return function ($passable) use ($pipe, $next) {
            // If pipe is already a closure, execute directly
            if ($pipe instanceof Closure) {
                return $pipe($passable, $next);
            }

            // If pipe is an object with the specified method
            if (is_object($pipe) && method_exists($pipe, $this->method)) {
                return $pipe->{$this->method}($passable, $next);
            }

            // If pipe is a class name string
            if (is_string($pipe)) {
                return $this->executePipeClass($pipe, $passable, $next);
            }

            // If pipe is invokable object
            if (is_callable($pipe)) {
                return $pipe($passable, $next);
            }

            throw new \InvalidArgumentException(
                'Pipeline pipe must be a callable, invokable object, or class name. Got: ' . gettype($pipe)
            );
        };
    }

    /**
     * Execute a pipe class from string name.
     *
     * Supports both 'Class@method' syntax and plain class names.
     *
     * @param string $pipe Class name or 'Class@method'
     * @param mixed $passable Object being passed through
     * @param Closure $next Next layer
     * @return mixed Result from pipe
     */
    private function executePipeClass(string $pipe, mixed $passable, Closure $next): mixed
    {
        // Parse Class@method syntax
        [$class, $method] = $this->parsePipeString($pipe);

        // Resolve instance from container or instantiate directly
        $instance = $this->container !== null
            ? $this->container->get($class)
            : new $class();

        // Call the method
        return $instance->{$method}($passable, $next);
    }

    /**
     * Parse pipe string into class and method.
     *
     * @param string $pipe Class name or 'Class@method'
     * @return array{0: string, 1: string} [className, methodName]
     */
    private function parsePipeString(string $pipe): array
    {
        if (str_contains($pipe, '@')) {
            return explode('@', $pipe, 2);
        }

        return [$pipe, $this->method];
    }

    /**
     * Get the container instance.
     *
     * @return ContainerInterface|null
     */
    public function getContainer(): ?ContainerInterface
    {
        return $this->container;
    }

    /**
     * Set the container instance.
     *
     * @param ContainerInterface $container
     * @return self
     */
    public function setContainer(ContainerInterface $container): self
    {
        $this->container = $container;
        return $this;
    }
}
