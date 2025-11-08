<?php

declare(strict_types=1);

namespace Toporia\Framework\Auth;

/**
 * Authorization Gate
 *
 * Manages authorization gates for application-wide permission checks.
 * Gates provide a simple closure-based approach to authorization.
 */
final class Gate implements GateInterface
{
    private array $abilities = [];
    private mixed $user = null;

    public function __construct(
        private ?AuthManagerInterface $auth = null
    ) {}

    public function define(string $ability, callable $callback): self
    {
        $this->abilities[$ability] = $callback;
        return $this;
    }

    public function allows(string $ability, mixed ...$arguments): bool
    {
        if (!$this->has($ability)) {
            return false;
        }

        $user = $this->resolveUser();

        if ($user === null) {
            return false;
        }

        $callback = $this->abilities[$ability];
        $result = $callback($user, ...$arguments);

        return $result === true;
    }

    public function denies(string $ability, mixed ...$arguments): bool
    {
        return !$this->allows($ability, ...$arguments);
    }

    public function authorize(string $ability, mixed ...$arguments): void
    {
        if ($this->denies($ability, ...$arguments)) {
            throw AuthorizationException::forAbility($ability);
        }
    }

    public function any(array $abilities, mixed ...$arguments): bool
    {
        foreach ($abilities as $ability) {
            if ($this->allows($ability, ...$arguments)) {
                return true;
            }
        }

        return false;
    }

    public function all(array $abilities, mixed ...$arguments): bool
    {
        foreach ($abilities as $ability) {
            if ($this->denies($ability, ...$arguments)) {
                return false;
            }
        }

        return true;
    }

    public function forUser(mixed $user): self
    {
        $clone = clone $this;
        $clone->user = $user;
        return $clone;
    }

    public function has(string $ability): bool
    {
        return isset($this->abilities[$ability]);
    }

    /**
     * Resolve the user for authorization
     *
     * @return mixed
     */
    private function resolveUser(): mixed
    {
        if ($this->user !== null) {
            return $this->user;
        }

        if ($this->auth !== null) {
            $guard = $this->auth->guard();
            if ($guard->check()) {
                return $guard->user();
            }
        }

        return null;
    }

    /**
     * Define multiple abilities at once
     *
     * @param array $abilities ['ability' => callback, ...]
     * @return self
     */
    public function defineMany(array $abilities): self
    {
        foreach ($abilities as $ability => $callback) {
            $this->define($ability, $callback);
        }

        return $this;
    }

    /**
     * Define a policy class for a resource
     *
     * @param string $class Resource class name
     * @param string $policyClass Policy class name
     * @return self
     */
    public function policy(string $class, string $policyClass): self
    {
        // Register all public methods of the policy as gates
        $policy = new $policyClass();
        $reflection = new \ReflectionClass($policyClass);

        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isStatic() || $method->isConstructor()) {
                continue;
            }

            $ability = $class . '@' . $method->getName();
            $this->define($ability, [$policy, $method->getName()]);
        }

        return $this;
    }

    /**
     * Check ability using resource-based authorization
     *
     * @param string $action
     * @param mixed $resource
     * @param mixed ...$arguments
     * @return bool
     */
    public function check(string $action, mixed $resource, mixed ...$arguments): bool
    {
        $class = is_object($resource) ? get_class($resource) : $resource;
        $ability = $class . '@' . $action;

        return $this->allows($ability, $resource, ...$arguments);
    }
}
