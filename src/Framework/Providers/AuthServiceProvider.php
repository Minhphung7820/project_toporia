<?php

declare(strict_types=1);

namespace Toporia\Framework\Providers;

use App\Domain\User\UserRepository;
use Toporia\Framework\Auth\AuthManager;
use Toporia\Framework\Auth\AuthManagerInterface;
use Toporia\Framework\Auth\Guards\SessionGuard;
use Toporia\Framework\Auth\Guards\TokenGuard;
use Toporia\Framework\Auth\UserProvider\RepositoryUserProvider;
use Toporia\Framework\Container\ContainerInterface;
use Toporia\Framework\Foundation\ServiceProvider;
use Toporia\Framework\Http\Request;

/**
 * Authentication Service Provider
 *
 * Registers authentication services including:
 * - Auth Manager with multiple guards
 * - User providers
 * - Guard configurations
 *
 * Following Single Responsibility Principle - only handles auth services.
 */
class AuthServiceProvider extends ServiceProvider
{
    /**
     * {@inheritdoc}
     */
    public function register(ContainerInterface $container): void
    {
        $this->registerAuthManager($container);
        $this->registerAuthAlias($container);
    }

    /**
     * Register Auth Manager with guard factories.
     *
     * @param ContainerInterface $container
     * @return void
     */
    protected function registerAuthManager(ContainerInterface $container): void
    {
        $container->singleton(AuthManagerInterface::class, function (ContainerInterface $c) {
            $guardFactories = [
                'web' => fn() => $this->createSessionGuard($c, 'web'),
                'api' => fn() => $this->createTokenGuard($c, 'api'),
                // Add more guards here as needed:
                // 'admin' => fn() => $this->createSessionGuard($c, 'admin'),
            ];

            $defaultGuard = $this->getDefaultGuard($c);

            return new AuthManager($guardFactories, $defaultGuard);
        });
    }

    /**
     * Register 'auth' alias for helper function.
     *
     * @param ContainerInterface $container
     * @return void
     */
    protected function registerAuthAlias(ContainerInterface $container): void
    {
        $container->singleton('auth', fn(ContainerInterface $c) => $c->get(AuthManagerInterface::class));
    }

    /**
     * Create a session guard instance.
     *
     * @param ContainerInterface $container
     * @param string $name Guard name.
     * @return SessionGuard
     */
    protected function createSessionGuard(ContainerInterface $container, string $name): SessionGuard
    {
        $userProvider = new RepositoryUserProvider(
            $container->get(UserRepository::class)
        );

        return new SessionGuard($userProvider, $name);
    }

    /**
     * Get default guard name from config.
     *
     * @param ContainerInterface $container
     * @return string
     */
    protected function getDefaultGuard(ContainerInterface $container): string
    {
        try {
            if ($container->has('config')) {
                $config = $container->get('config');
                return $config->get('auth.defaults.guard', 'web');
            }
        } catch (\Throwable $e) {
            // Fall back to 'web'
        }

        return 'web';
    }

    /**
     * Create a token guard instance for API authentication.
     *
     * @param ContainerInterface $container
     * @param string $name Guard name.
     * @return TokenGuard
     */
    protected function createTokenGuard(ContainerInterface $container, string $name): TokenGuard
    {
        $userProvider = new RepositoryUserProvider(
            $container->get(UserRepository::class)
        );

        $request = $container->get(Request::class);

        return new TokenGuard($userProvider, $request, $name);
    }
}
