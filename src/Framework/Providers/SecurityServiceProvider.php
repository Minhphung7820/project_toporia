<?php

declare(strict_types=1);

namespace Toporia\Framework\Providers;

use Toporia\Framework\Container\ContainerInterface;
use Toporia\Framework\Foundation\ServiceProvider;
use Toporia\Framework\Security\CsrfTokenManagerInterface;
use Toporia\Framework\Security\SessionCsrfTokenManager;
use Toporia\Framework\Security\XssService;
use Toporia\Framework\Auth\GateInterface;
use Toporia\Framework\Auth\Gate;
use Toporia\Framework\Auth\AuthInterface;
use Toporia\Framework\Http\CookieJar;

/**
 * Security Service Provider
 *
 * Registers security-related services:
 * - CSRF protection
 * - Authorization (Gates)
 * - Cookie management
 */
final class SecurityServiceProvider extends ServiceProvider
{
    public function register(ContainerInterface $container): void
    {
        // CSRF Token Manager
        $container->singleton(CsrfTokenManagerInterface::class, function () {
            return new SessionCsrfTokenManager();
        });

        $container->bind('csrf', fn($c) => $c->get(CsrfTokenManagerInterface::class));

        // Authorization Gate
        $container->singleton(GateInterface::class, function ($c) {
            $auth = $c->has('auth') ? $c->get('auth') : null;
            return new Gate($auth);
        });

        $container->bind('gate', fn($c) => $c->get(GateInterface::class));

        // Cookie Jar
        $container->singleton(CookieJar::class, function () {
            $key = env('APP_KEY');
            return new CookieJar($key);
        });

        $container->bind('cookie', fn($c) => $c->get(CookieJar::class));

        // XSS Protection Service
        $container->singleton(XssService::class, function () {
            return new XssService();
        });

        $container->bind('xss', fn($c) => $c->get(XssService::class));
    }

    public function boot(ContainerInterface $container): void
    {
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
}
