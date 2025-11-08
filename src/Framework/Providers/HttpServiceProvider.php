<?php

declare(strict_types=1);

namespace Toporia\Framework\Providers;

use Toporia\Framework\Container\ContainerInterface;
use Toporia\Framework\Foundation\ServiceProvider;
use Toporia\Framework\Http\Request;
use Toporia\Framework\Http\RequestInterface;
use Toporia\Framework\Http\Response;
use Toporia\Framework\Http\ResponseInterface;

/**
 * HTTP Service Provider
 *
 * Registers Request and Response services into the container.
 */
class HttpServiceProvider extends ServiceProvider
{
    /**
     * {@inheritdoc}
     */
    public function register(ContainerInterface $container): void
    {
        // Request - Created per request
        $container->bind(RequestInterface::class, fn() => Request::capture());
        $container->bind(Request::class, fn() => Request::capture());
        $container->bind('request', fn() => Request::capture());

        // Response - Created per request
        $container->bind(ResponseInterface::class, fn() => new Response());
        $container->bind(Response::class, fn() => new Response());
        $container->bind('response', fn() => new Response());
    }
}
