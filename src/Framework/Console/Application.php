<?php

declare(strict_types=1);

namespace Toporia\Framework\Console;

use Toporia\Framework\Container\ContainerInterface;

/**
 * Console Application
 *
 * Minimal command dispatcher that:
 * - Registers console commands by signature
 * - Resolves command instances via the container
 * - Routes argv to the appropriate command
 * - Injects Input/Output into commands
 */
final class Application
{
  /** @var array<string, class-string<Command>> */
  private array $registry = [];

  private InputInterface $input;
  private OutputInterface $output;

  public function __construct(
    private readonly ContainerInterface $container
  ) {
    $this->output = new Output();
  }

  /**
   * Register a command class by its signature.
   *
   * @param class-string<Command> $commandClass
   */
  public function register(string $commandClass): void
  {
    /** @var Command $instance */
    $instance = $this->container->get($commandClass);
    $name = $instance->getName();
    $this->registry[$name] = $commandClass;
  }

  /**
   * Run the console application.
   *
   * @param array<int, string> $argv
   */
  public function run(array $argv): int
  {
    // Parse input
    $this->input = Input::fromArgv($argv);

    // Get command name
    $commandName = $argv[1] ?? 'list';

    // Handle built-in commands
    if ($commandName === 'list') {
      return $this->listCommands();
    }

    // Find and execute command
    if (!isset($this->registry[$commandName])) {
      $this->output->error("Command not found: {$commandName}");
      $this->output->writeln("Run 'list' to see available commands.");
      return 1;
    }

    return $this->executeCommand($commandName);
  }

  /**
   * Execute a registered command
   *
   * @param string $commandName
   * @return int
   */
  private function executeCommand(string $commandName): int
  {
    try {
      /** @var Command $command */
      $command = $this->container->get($this->registry[$commandName]);

      // Inject Input/Output
      $command->setInput($this->input);
      $command->setOutput($this->output);

      // Execute command
      return $command->handle();
    } catch (\Throwable $e) {
      $this->output->error("Command failed: {$e->getMessage()}");

      if ($this->input->hasOption('verbose') || $this->input->hasOption('v')) {
        $this->output->error($e->getTraceAsString());
      }

      return 1;
    }
  }

  /**
   * List all registered commands
   *
   * @return int
   */
  private function listCommands(): int
  {
    $this->output->writeln("Available commands:");
    $this->output->newLine();

    if (empty($this->registry)) {
      $this->output->warning("No commands registered.");
      return 0;
    }

    // Prepare table data
    $headers = ['Command', 'Description'];
    $rows = [];

    foreach ($this->registry as $name => $class) {
      /** @var Command $command */
      $command = $this->container->get($class);
      $rows[] = [$name, $command->getDescription()];
    }

    $this->output->table($headers, $rows);

    $this->output->newLine();
    $this->output->info("Run 'php console [command] --help' for more information.");

    return 0;
  }

  /**
   * Set custom output (for testing)
   *
   * @param OutputInterface $output
   * @return void
   */
  public function setOutput(OutputInterface $output): void
  {
    $this->output = $output;
  }
}
