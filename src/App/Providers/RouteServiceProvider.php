<?php

declare(strict_types=1);

namespace App\Providers;

use Toporia\Framework\Container\ContainerInterface;
use Toporia\Framework\Foundation\ServiceProvider;
use Toporia\Framework\Foundation\Application;
use Toporia\Framework\Routing\Router;

/**
 * Route Service Provider
 *
 * This provider is responsible for loading application routes with middleware groups.
 * Routes are separated by type (web, api) and each gets appropriate middleware.
 */
class RouteServiceProvider extends ServiceProvider
{
    /**
     * {@inheritdoc}
     */
    public function boot(ContainerInterface $container): void
    {
        /** @var Application $app */
        $app = $container->get(Application::class);

        /** @var Router $router */
        $router = $container->get(Router::class);

        // Load middleware configuration
        $middlewareConfig = $container->get('config')->get('middleware', []);
        $middlewareGroups = $middlewareConfig['groups'] ?? [];

        // Load web routes with 'web' middleware group
        $this->loadWebRoutes($app, $router, $middlewareGroups['web'] ?? []);

        // Load API routes with 'api' middleware group
        $this->loadApiRoutes($app, $router, $middlewareGroups['api'] ?? []);
    }

    /**
     * Load web routes.
     *
     * @param Application $app
     * @param Router $router
     * @param array $middleware
     * @return void
     */
    protected function loadWebRoutes(Application $app, Router $router, array $middleware): void
    {
        $router->group([
            'middleware' => $middleware,
            'namespace' => 'App\\Presentation\\Http\\Controllers',
        ], function (Router $router) use ($app) {
            $path = $app->path('routes/web.php');
            if (file_exists($path)) {
                require $path;
            }
        });
    }

    /**
     * Load API routes.
     *
     * @param Application $app
     * @param Router $router
     * @param array $middleware
     * @return void
     */
    protected function loadApiRoutes(Application $app, Router $router, array $middleware): void
    {
        $router->group([
            'prefix' => 'api',
            'middleware' => $middleware,
            'namespace' => 'App\\Presentation\\Http\\Controllers\\Api',
        ], function (Router $router) use ($app) {
            $path = $app->path('routes/api.php');
            if (file_exists($path)) {
                require $path;
            }
        });
    }
}
