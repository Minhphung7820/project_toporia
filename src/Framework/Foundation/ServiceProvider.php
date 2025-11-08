<?php

declare(strict_types=1);

namespace Framework\Foundation;

use Framework\Container\ContainerInterface;

/**
 * Abstract Service Provider
 *
 * Base class for service providers. Provides default implementations
 * for the ServiceProviderInterface methods.
 */
abstract class ServiceProvider implements ServiceProviderInterface
{
    /**
     * {@inheritdoc}
     */
    public function register(ContainerInterface $container): void
    {
        // Override in subclass if needed
    }

    /**
     * {@inheritdoc}
     */
    public function boot(ContainerInterface $container): void
    {
        // Override in subclass if needed
    }
}
