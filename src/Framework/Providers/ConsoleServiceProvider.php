<?php

declare(strict_types=1);

namespace Toporia\Framework\Providers;

use Toporia\Framework\Container\ContainerInterface;
use Toporia\Framework\Foundation\ServiceProvider;
use Toporia\Framework\Console\Application;
use App\Presentation\Console\Kernel;

/**
 * Console Service Provider
 *
 * Binds the console application and registers CLI commands via the kernel.
 */
final class ConsoleServiceProvider extends ServiceProvider
{
  public function register(ContainerInterface $container): void
  {
    $container->singleton(Application::class, fn() => new Application($container));
  }

  public function boot(ContainerInterface $container): void
  {
    $kernel = new Kernel();
    $kernel->bootstrap($container->get(Application::class));
  }
}
