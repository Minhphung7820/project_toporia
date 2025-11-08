<?php

declare(strict_types=1);

namespace Framework\Providers;

use Framework\Container\ContainerInterface;
use Framework\Foundation\ServiceProvider;
use Framework\Http\Request;
use Framework\Http\RequestInterface;
use Framework\Http\Response;
use Framework\Http\ResponseInterface;

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
