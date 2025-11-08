<?php

declare(strict_types=1);

namespace Framework\Foundation;

use Framework\Container\Container;
use Framework\Container\ContainerInterface;

/**
 * Application Bootstrap
 *
 * This class bootstraps the framework by:
 * 1. Creating the container
 * 2. Registering service providers
 * 3. Booting service providers
 */
class Application
{
    /**
     * @var ContainerInterface Dependency injection container
     */
    private ContainerInterface $container;

    /**
     * @var array<ServiceProviderInterface> Registered service providers
     */
    private array $providers = [];

    /**
     * @var bool Whether service providers have been booted
     */
    private bool $booted = false;

    /**
     * @param string $basePath Application base path
     * @param ContainerInterface|null $container Optional custom container
     */
    public function __construct(
        private string $basePath,
        ?ContainerInterface $container = null
    ) {
        $this->container = $container ?? new Container();

        // Register the container itself
        $this->container->instance(ContainerInterface::class, $this->container);
        $this->container->instance(Container::class, $this->container);
        $this->container->instance('app', $this);
        $this->container->instance(Application::class, $this);
    }

    /**
     * Register a service provider.
     *
     * @param string|ServiceProviderInterface $provider Provider class name or instance
     * @return self
     */
    public function register(string|ServiceProviderInterface $provider): self
    {
        if (is_string($provider)) {
            $provider = new $provider();
        }

        $provider->register($this->container);
        $this->providers[] = $provider;

        // If already booted, boot this provider immediately
        if ($this->booted) {
            $provider->boot($this->container);
        }

        return $this;
    }

    /**
     * Register multiple service providers.
     *
     * @param array<string|ServiceProviderInterface> $providers
     * @return self
     */
    public function registerProviders(array $providers): self
    {
        foreach ($providers as $provider) {
            $this->register($provider);
        }

        return $this;
    }

    /**
     * Boot all registered service providers.
     *
     * @return self
     */
    public function boot(): self
    {
        if ($this->booted) {
            return $this;
        }

        foreach ($this->providers as $provider) {
            $provider->boot($this->container);
        }

        $this->booted = true;

        return $this;
    }

    /**
     * Get the container instance.
     *
     * @return ContainerInterface
     */
    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }

    /**
     * Get the base path of the application.
     *
     * @return string
     */
    public function getBasePath(): string
    {
        return $this->basePath;
    }

    /**
     * Get a path relative to the base path.
     *
     * @param string $path
     * @return string
     */
    public function path(string $path = ''): string
    {
        return $this->basePath . ($path ? DIRECTORY_SEPARATOR . $path : '');
    }

    /**
     * Resolve a service from the container.
     *
     * @param string $id
     * @return mixed
     */
    public function make(string $id): mixed
    {
        return $this->container->get($id);
    }

    /**
     * Check if a service exists in the container.
     *
     * @param string $id
     * @return bool
     */
    public function has(string $id): bool
    {
        return $this->container->has($id);
    }
}
