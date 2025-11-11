<?php

declare(strict_types=1);

namespace Toporia\Framework\Providers;

use Toporia\Framework\Container\ContainerInterface;
use Toporia\Framework\DateTime\Chronos;
use Toporia\Framework\DateTime\Contracts\ChronosInterface;
use Toporia\Framework\Foundation\ServiceProvider;

/**
 * DateTime Service Provider
 *
 * Registers date/time services into the container.
 */
final class DateTimeServiceProvider extends ServiceProvider
{
    public function register(ContainerInterface $container): void
    {
        // Register Chronos as the default date/time implementation
        $container->bind(ChronosInterface::class, fn() => Chronos::now());

        // Convenience binding
        $container->bind('chronos', fn() => Chronos::now());
    }
}
