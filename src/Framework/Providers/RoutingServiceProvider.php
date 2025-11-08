<?php

declare(strict_types=1);

namespace Toporia\Framework\Providers;

use Toporia\Framework\Container\ContainerInterface;
use Toporia\Framework\Foundation\ServiceProvider;
use Toporia\Framework\Http\Request;
use Toporia\Framework\Http\Response;
use Toporia\Framework\Routing\Router;
use Toporia\Framework\Routing\RouterInterface;

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
