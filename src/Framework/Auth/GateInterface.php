<?php

declare(strict_types=1);

namespace Toporia\Framework\Auth;

/**
 * Gate Interface
 *
 * Defines contract for authorization gates.
 * Gates provide a simple closure-based approach to authorization.
 */
interface GateInterface
{
    /**
     * Define a new authorization gate
     *
     * @param string $ability Ability name (e.g., 'update-post')
     * @param callable $callback Authorization callback (user, ...args) => bool
     * @return self
     */
    public function define(string $ability, callable $callback): self;

    /**
     * Check if a user has a given ability
     *
     * @param string $ability
     * @param mixed ...$arguments Additional arguments passed to the gate callback
     * @return bool
     */
    public function allows(string $ability, mixed ...$arguments): bool;

    /**
     * Check if a user does not have a given ability
     *
     * @param string $ability
     * @param mixed ...$arguments
     * @return bool
     */
    public function denies(string $ability, mixed ...$arguments): bool;

    /**
     * Authorize an ability or throw exception
     *
     * @param string $ability
     * @param mixed ...$arguments
     * @return void
     * @throws AuthorizationException
     */
    public function authorize(string $ability, mixed ...$arguments): void;

    /**
     * Check if any of the given abilities are granted
     *
     * @param array $abilities
     * @param mixed ...$arguments
     * @return bool
     */
    public function any(array $abilities, mixed ...$arguments): bool;

    /**
     * Check if all of the given abilities are granted
     *
     * @param array $abilities
     * @param mixed ...$arguments
     * @return bool
     */
    public function all(array $abilities, mixed ...$arguments): bool;

    /**
     * Set the user for authorization checks
     *
     * @param mixed $user
     * @return self
     */
    public function forUser(mixed $user): self;

    /**
     * Check if a gate has been defined
     *
     * @param string $ability
     * @return bool
     */
    public function has(string $ability): bool;
}
