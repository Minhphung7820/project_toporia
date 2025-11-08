<?php

declare(strict_types=1);

namespace Toporia\Framework\Console;

/**
 * Abstract Console Command
 *
 * Base class for all console commands.
 * Follows Single Responsibility Principle - each command does one thing.
 * Uses Dependency Injection for Input/Output (testable).
 */
abstract class Command
{
    /**
     * Command signature (name and arguments)
     *
     * Format: "command:name {arg1} {arg2?} {--option} {--option2=}"
     *
     * @var string
     */
    protected string $signature = '';

    /**
     * Command description
     *
     * @var string
     */
    protected string $description = '';

    /**
     * Input interface
     *
     * @var InputInterface|null
     */
    protected ?InputInterface $input = null;

    /**
     * Output interface
     *
     * @var OutputInterface|null
     */
    protected ?OutputInterface $output = null;

    /**
     * Execute the command
     *
     * This is the main method that child classes must implement.
     *
     * @return int Exit code (0 = success, non-zero = error)
     */
    abstract public function handle(): int;

    /**
     * Get command signature
     *
     * @return string
     */
    public function getSignature(): string
    {
        return $this->signature;
    }

    /**
     * Get command name from signature
     *
     * @return string
     */
    public function getName(): string
    {
        return explode(' ', $this->signature)[0];
    }

    /**
     * Get command description
     *
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Set input interface
     *
     * @param InputInterface $input
     * @return void
     */
    public function setInput(InputInterface $input): void
    {
        $this->input = $input;
    }

    /**
     * Set output interface
     *
     * @param OutputInterface $output
     * @return void
     */
    public function setOutput(OutputInterface $output): void
    {
        $this->output = $output;
    }

    /**
     * Get argument value
     *
     * @param string|int $key
     * @param mixed $default
     * @return mixed
     */
    protected function argument(string|int $key, mixed $default = null): mixed
    {
        return $this->input?->getArgument($key, $default) ?? $default;
    }

    /**
     * Get option value
     *
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    protected function option(string $name, mixed $default = null): mixed
    {
        return $this->input?->getOption($name, $default) ?? $default;
    }

    /**
     * Check if option exists
     *
     * @param string $name
     * @return bool
     */
    protected function hasOption(string $name): bool
    {
        return $this->input?->hasOption($name) ?? false;
    }

    /**
     * Write output to console
     *
     * @param string $message
     * @return void
     */
    protected function write(string $message): void
    {
        $this->output?->write($message);
    }

    /**
     * Write line to console
     *
     * @param string $message
     * @return void
     */
    protected function writeln(string $message): void
    {
        $this->output?->writeln($message);
    }

    /**
     * Write info message
     *
     * @param string $message
     * @return void
     */
    protected function info(string $message): void
    {
        $this->output?->info($message);
    }

    /**
     * Write error message
     *
     * @param string $message
     * @return void
     */
    protected function error(string $message): void
    {
        $this->output?->error($message);
    }

    /**
     * Write success message
     *
     * @param string $message
     * @return void
     */
    protected function success(string $message): void
    {
        $this->output?->success($message);
    }

    /**
     * Write warning message
     *
     * @param string $message
     * @return void
     */
    protected function warn(string $message): void
    {
        $this->output?->warning($message);
    }

    /**
     * Write line separator
     *
     * @param string $char
     * @param int $length
     * @return void
     */
    protected function line(string $char = '-', int $length = 80): void
    {
        $this->output?->line($char, $length);
    }

    /**
     * Write blank line(s)
     *
     * @param int $count
     * @return void
     */
    protected function newLine(int $count = 1): void
    {
        $this->output?->newLine($count);
    }

    /**
     * Write a table
     *
     * @param array<string> $headers
     * @param array<array<string>> $rows
     * @return void
     */
    protected function table(array $headers, array $rows): void
    {
        $this->output?->table($headers, $rows);
    }

    /**
     * Ask user for confirmation (yes/no)
     *
     * @param string $question
     * @param bool $default
     * @return bool
     */
    protected function confirm(string $question, bool $default = false): bool
    {
        if (!$this->input?->isInteractive()) {
            return $default;
        }

        $suffix = $default ? '[Y/n]' : '[y/N]';
        $this->write("{$question} {$suffix}: ");

        $answer = strtolower(trim(fgets(STDIN) ?: ''));

        if ($answer === '') {
            return $default;
        }

        return in_array($answer, ['y', 'yes']);
    }

    /**
     * Ask user for input
     *
     * @param string $question
     * @param string|null $default
     * @return string
     */
    protected function ask(string $question, ?string $default = null): string
    {
        if (!$this->input?->isInteractive()) {
            return $default ?? '';
        }

        $suffix = $default ? " [{$default}]" : '';
        $this->write("{$question}{$suffix}: ");

        $answer = trim(fgets(STDIN) ?: '');

        return $answer === '' ? ($default ?? '') : $answer;
    }

    /**
     * Ask user to choose from options
     *
     * @param string $question
     * @param array<string> $choices
     * @param string|null $default
     * @return string
     */
    protected function choice(string $question, array $choices, ?string $default = null): string
    {
        if (!$this->input?->isInteractive()) {
            return $default ?? $choices[0];
        }

        $this->writeln($question);
        foreach ($choices as $i => $choice) {
            $this->writeln("  [{$i}] {$choice}");
        }

        $suffix = $default !== null ? " [{$default}]" : '';
        $this->write("Choose{$suffix}: ");

        $answer = trim(fgets(STDIN) ?: '');

        if ($answer === '') {
            return $default ?? $choices[0];
        }

        if (is_numeric($answer) && isset($choices[(int) $answer])) {
            return $choices[(int) $answer];
        }

        return in_array($answer, $choices) ? $answer : ($default ?? $choices[0]);
    }
}
