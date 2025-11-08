<?php

declare(strict_types=1);

namespace Framework\Providers;

use Framework\Container\ContainerInterface;
use Framework\Foundation\ServiceProvider;
use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Routing\Router;
use Framework\Routing\RouterInterface;

/**
 * Routing Service Provider
 *
 * Registers the router as a singleton with its dependencies.
 */
class RoutingServiceProvider extends ServiceProvider
{
    /**
     * {@inheritdoc}
     */
    public function register(ContainerInterface $container): void
    {
        // Router - Singleton with injected dependencies
        $container->singleton(RouterInterface::class, fn(ContainerInterface $c) => new Router(
            $c->get(Request::class),
            $c->get(Response::class),
            $c
        ));

        $container->singleton(Router::class, fn(ContainerInterface $c) => new Router(
            $c->get(Request::class),
            $c->get(Response::class),
            $c
        ));

        $container->singleton('router', fn(ContainerInterface $c) => $c->get(Router::class));
    }

    /**
     * {@inheritdoc}
     */
    public function boot(ContainerInterface $container): void
    {
        /** @var Router $router */
        $router = $container->get(Router::class);

        // Load middleware aliases from config
        $middlewareAliases = $this->getMiddlewareAliases($container);
        $router->setMiddlewareAliases($middlewareAliases);
    }

    /**
     * Get middleware aliases from configuration.
     *
     * @param ContainerInterface $container
     * @return array<string, string>
     */
    protected function getMiddlewareAliases(ContainerInterface $container): array
    {
        try {
            if (!$container->has('config')) {
                return [];
            }

            $config = $container->get('config');
            return $config->get('middleware.aliases', []);
        } catch (\Throwable $e) {
            return [];
        }
    }
}
