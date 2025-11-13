<?php

declare(strict_types=1);

namespace Toporia\Framework\Providers;

use Toporia\Framework\Container\ContainerInterface;
use Toporia\Framework\Foundation\ServiceProvider;
use Toporia\Framework\Console\Application;
use Toporia\Framework\Console\CommandRegistry;
use App\Presentation\Console\Kernel;

/**
 * Console Service Provider
 *
 * Binds the console application and registers CLI commands.
 * Framework commands are auto-registered here (framework layer).
 * Application commands are registered in Application Kernel (application layer).
 */
final class ConsoleServiceProvider extends ServiceProvider
{
  public function register(ContainerInterface $container): void
  {
    // Register command registry
    $container->singleton(CommandRegistry::class, fn() => new CommandRegistry());

    // Register console application
    $container->singleton(Application::class, fn($c) => new Application($c));
  }

  public function boot(ContainerInterface $container): void
  {
    $registry = $container->get(CommandRegistry::class);

    // Register FRAMEWORK commands here (framework layer)
    $this->registerFrameworkCommands($registry);

    // Bootstrap APPLICATION kernel (application layer)
    $kernel = new Kernel();
    $kernel->bootstrap($container->get(Application::class), $registry);
  }

  /**
   * Register framework-level commands.
   *
   * @param CommandRegistry $registry
   * @return void
   */
  private function registerFrameworkCommands(CommandRegistry $registry): void
  {
    $registry->registerMany([
      // Database commands
      \Toporia\Framework\Console\Commands\MigrateCommand::class,
      \Toporia\Framework\Console\Commands\MigrateRollbackCommand::class,
      \Toporia\Framework\Console\Commands\MigrateStatusCommand::class,

      // Route commands
      \Toporia\Framework\Console\Commands\RouteCacheCommand::class,
      \Toporia\Framework\Console\Commands\RouteClearCommand::class,
      \Toporia\Framework\Console\Commands\RouteListCommand::class,

      // Cache commands
      \Toporia\Framework\Console\Commands\CacheClearCommand::class,

      // Queue commands
      \Toporia\Framework\Console\Commands\QueueWorkCommand::class,

      // Schedule commands
      \Toporia\Framework\Console\Commands\ScheduleRunCommand::class,
      \Toporia\Framework\Console\Commands\ScheduleWorkCommand::class,
      \Toporia\Framework\Console\Commands\ScheduleListCommand::class,

      // Realtime commands
      \Toporia\Framework\Console\Commands\RealtimeServeCommand::class,
      \Toporia\Framework\Console\Commands\RealtimeKafkaConsumerCommand::class,
      \Toporia\Framework\Console\Commands\RealtimeRedisConsumerCommand::class,

      // Development server
      \Toporia\Framework\Console\Commands\ServeCommand::class,
    ]);
  }
}
