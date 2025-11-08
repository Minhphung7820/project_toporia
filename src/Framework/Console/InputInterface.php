<?php

declare(strict_types=1);

namespace Toporia\Framework\Console;

/**
 * Input Interface
 *
 * Abstraction for command input (arguments, options, interactive input).
 * Follows Interface Segregation Principle.
 */
interface InputInterface
{
    /**
     * Get argument by name or index
     *
     * @param string|int $key
     * @param mixed $default
     * @return mixed
     */
    public function getArgument(string|int $key, mixed $default = null): mixed;

    /**
     * Get all arguments
     *
     * @return array<string|int, mixed>
     */
    public function getArguments(): array;

    /**
     * Check if argument exists
     *
     * @param string|int $key
     * @return bool
     */
    public function hasArgument(string|int $key): bool;

    /**
     * Get option by name
     *
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    public function getOption(string $name, mixed $default = null): mixed;

    /**
     * Get all options
     *
     * @return array<string, mixed>
     */
    public function getOptions(): array;

    /**
     * Check if option exists
     *
     * @param string $name
     * @return bool
     */
    public function hasOption(string $name): bool;

    /**
     * Check if interactive mode is enabled
     *
     * @return bool
     */
    public function isInteractive(): bool;
}
