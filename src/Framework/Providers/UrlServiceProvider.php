<?php

declare(strict_types=1);

namespace Toporia\Framework\Providers;

use Toporia\Framework\Container\ContainerInterface;
use Toporia\Framework\Foundation\ServiceProvider;
use Toporia\Framework\Routing\UrlGenerator;
use Toporia\Framework\Routing\UrlGeneratorInterface;

/**
 * URL Service Provider.
 *
 * Registers the URL generator service for generating URLs to routes,
 * assets, and signed URLs.
 */
final class UrlServiceProvider extends ServiceProvider
{
    /**
     * Register URL generator service.
     *
     * @param ContainerInterface $container
     * @return void
     */
    public function register(ContainerInterface $container): void
    {
        $container->singleton(UrlGeneratorInterface::class, function ($c) {
            $routes = $c->get('router')->getRoutes();
            $request = $c->get('request');
            $secretKey = $_ENV['APP_KEY'] ?? 'default-secret-key';

            return new UrlGenerator($routes, $request, $secretKey);
        });

        $container->bind('url', fn($c) => $c->get(UrlGeneratorInterface::class));
    }

    /**
     * Bootstrap URL generator service.
     *
     * @param ContainerInterface $container
     * @return void
     */
    public function boot(ContainerInterface $container): void
    {
        // No bootstrapping needed
    }
}
