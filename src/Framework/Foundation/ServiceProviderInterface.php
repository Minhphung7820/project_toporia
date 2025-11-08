<?php

declare(strict_types=1);

namespace Toporia\Framework\Foundation;

use Toporia\Framework\Container\ContainerInterface;

/**
 * Service Provider Interface
 *
 * Service providers are the central place for configuring services.
 * They allow you to organize service registration logic in separate classes.
 */
interface ServiceProviderInterface
{
    /**
     * Register services into the container.
     *
     * This method is called during the bootstrap process to bind services.
     * You should only bind services here, not resolve them.
     *
     * @param ContainerInterface $container
     * @return void
     */
    public function register(ContainerInterface $container): void;

    /**
     * Bootstrap services after all providers are registered.
     *
     * This method is called after all providers have registered their services.
     * You can safely resolve services from the container here.
     *
     * @param ContainerInterface $container
     * @return void
     */
    public function boot(ContainerInterface $container): void;
}
