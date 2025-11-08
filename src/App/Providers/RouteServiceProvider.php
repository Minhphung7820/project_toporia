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
 * This provider is responsible for loading application routes.
 * Route definitions are separated from the bootstrap process.
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

        // Load web routes
        $this->loadRoutes($app->path('routes/web.php'), $router);
    }

    /**
     * Load routes from a file.
     *
     * @param string $path Route file path
     * @param Router $router Router instance
     * @return void
     */
    protected function loadRoutes(string $path, Router $router): void
    {
        if (file_exists($path)) {
            require $path;
        }
    }
}
