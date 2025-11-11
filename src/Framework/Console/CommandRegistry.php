<?php

declare(strict_types=1);

namespace Toporia\Framework\Console;

/**
 * Command Registry for managing and discovering console commands.
 *
 * Provides centralized command registration and auto-discovery.
 * Follows Open/Closed Principle - extend by adding commands, not modifying code.
 *
 * Performance: O(1) registration, O(1) lookup
 */
final class CommandRegistry
{
    /**
     * @var array<class-string<Command>> Registered command classes
     */
    private array $commands = [];

    /**
     * Register a command class.
     *
     * @param class-string<Command> $commandClass
     * @return self
     */
    public function register(string $commandClass): self
    {
        $this->commands[] = $commandClass;
        return $this;
    }

    /**
     * Register multiple command classes.
     *
     * @param array<class-string<Command>> $commandClasses
     * @return self
     */
    public function registerMany(array $commandClasses): self
    {
        foreach ($commandClasses as $commandClass) {
            $this->register($commandClass);
        }
        return $this;
    }

    /**
     * Get all registered commands.
     *
     * @return array<class-string<Command>>
     */
    public function all(): array
    {
        return $this->commands;
    }

    /**
     * Check if a command is registered.
     *
     * @param class-string<Command> $commandClass
     * @return bool
     */
    public function has(string $commandClass): bool
    {
        return in_array($commandClass, $this->commands, true);
    }

    /**
     * Clear all registered commands.
     *
     * @return void
     */
    public function clear(): void
    {
        $this->commands = [];
    }
}
