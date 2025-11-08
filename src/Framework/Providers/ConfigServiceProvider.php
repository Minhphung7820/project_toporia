<?php

declare(strict_types=1);

namespace Toporia\Framework\Providers;

use Toporia\Framework\Config\Repository;
use Toporia\Framework\Container\ContainerInterface;
use Toporia\Framework\Foundation\Application;
use Toporia\Framework\Foundation\ServiceProvider;

/**
 * Configuration Service Provider
 *
 * Loads and registers configuration files.
 */
class ConfigServiceProvider extends ServiceProvider
{
    /**
     * {@inheritdoc}
     */
    public function register(ContainerInterface $container): void
    {
        $container->singleton(Repository::class, function (ContainerInterface $c) {
            /** @var Application $app */
            $app = $c->get(Application::class);

            $config = new Repository();

            // Load all config files from config directory
            $config->loadDirectory($app->path('config'));

            return $config;
        });

        $container->singleton('config', fn(ContainerInterface $c) => $c->get(Repository::class));
    }
}
