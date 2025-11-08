<?php

declare(strict_types=1);

namespace Toporia\Framework\Console;

/**
 * Output Interface
 *
 * Abstraction for command output.
 * Follows Interface Segregation Principle.
 */
interface OutputInterface
{
    /**
     * Write a message to the output
     *
     * @param string $message
     * @return void
     */
    public function write(string $message): void;

    /**
     * Write a message to the output with newline
     *
     * @param string $message
     * @return void
     */
    public function writeln(string $message): void;

    /**
     * Write an info message
     *
     * @param string $message
     * @return void
     */
    public function info(string $message): void;

    /**
     * Write an error message
     *
     * @param string $message
     * @return void
     */
    public function error(string $message): void;

    /**
     * Write a success message
     *
     * @param string $message
     * @return void
     */
    public function success(string $message): void;

    /**
     * Write a warning message
     *
     * @param string $message
     * @return void
     */
    public function warning(string $message): void;

    /**
     * Write a line separator
     *
     * @param string $char
     * @param int $length
     * @return void
     */
    public function line(string $char = '-', int $length = 80): void;

    /**
     * Write a blank line
     *
     * @return void
     */
    public function newLine(int $count = 1): void;

    /**
     * Write a table
     *
     * @param array<string> $headers
     * @param array<array<string>> $rows
     * @return void
     */
    public function table(array $headers, array $rows): void;
}
