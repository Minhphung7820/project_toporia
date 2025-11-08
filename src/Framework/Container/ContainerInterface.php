<?php

declare(strict_types=1);

namespace Toporia\Framework\Container;

use Toporia\Framework\Container\Exception\ContainerException;
use Toporia\Framework\Container\Exception\NotFoundException;

/**
 * Dependency Injection Container interface.
 *
 * Provides service location and dependency injection capabilities
 * following PSR-11 Container Interface pattern.
 */
interface ContainerInterface
{
    /**
     * Finds an entry of the container by its identifier and returns it.
     *
     * @param string $id Identifier of the entry to look for.
     * @return mixed Entry.
     * @throws NotFoundException  No entry was found for this identifier.
     * @throws ContainerException Error while retrieving the entry.
     */
    public function get(string $id): mixed;

    /**
     * Returns true if the container can return an entry for the given identifier.
     *
     * @param string $id Identifier of the entry to look for.
     * @return bool
     */
    public function has(string $id): bool;

    /**
     * Bind a service factory to the container.
     *
     * @param string $id Service identifier.
     * @param callable $factory Factory function that creates the service.
     * @return void
     */
    public function bind(string $id, callable $factory): void;

    /**
     * Bind a singleton service to the container.
     * The service will be created once and reused on subsequent calls.
     *
     * @param string $id Service identifier.
     * @param callable $factory Factory function that creates the service.
     * @return void
     */
    public function singleton(string $id, callable $factory): void;

    /**
     * Register an existing instance as a singleton.
     *
     * @param string $id Service identifier.
     * @param mixed $instance The service instance.
     * @return void
     */
    public function instance(string $id, mixed $instance): void;

    /**
     * Resolve and call a callable with dependency injection.
     *
     * @param callable|array|string $callable The callable to invoke.
     * @param array $parameters Additional parameters to pass.
     * @return mixed The result of the callable.
     * @throws ContainerException
     */
    public function call(callable|array|string $callable, array $parameters = []): mixed;
}
