<?php

declare(strict_types=1);

namespace Toporia\Framework\Auth;

/**
 * Auth Manager Interface - Multi-guard authentication manager.
 *
 * Manages multiple authentication guards (session, token, etc.)
 * and allows switching between them.
 *
 * Following Open/Closed Principle - can add new guards without modifying existing code.
 */
interface AuthManagerInterface
{
    /**
     * Get a guard instance by name.
     *
     * @param string|null $name Guard name or null for default.
     * @return GuardInterface Guard instance.
     * @throws \InvalidArgumentException If guard not found.
     */
    public function guard(?string $name = null): GuardInterface;

    /**
     * Set the default guard name.
     *
     * @param string $name Guard name.
     * @return void
     */
    public function setDefaultGuard(string $name): void;

    /**
     * Get the default guard name.
     *
     * @return string Default guard name.
     */
    public function getDefaultGuard(): string;

    /**
     * Check if a guard exists.
     *
     * @param string $name Guard name.
     * @return bool True if guard exists.
     */
    public function hasGuard(string $name): bool;

    /**
     * Extend the manager with a custom guard.
     *
     * @param string $name Guard name.
     * @param callable $callback Guard factory callback.
     * @return void
     */
    public function extend(string $name, callable $callback): void;
}
