<?php

declare(strict_types=1);

namespace App\Providers;

use Toporia\Framework\Container\ContainerInterface;
use Toporia\Framework\Foundation\ServiceProvider;
use App\Domain\Product\ProductRepository;
use App\Infrastructure\Persistence\InMemoryProductRepository;

/**
 * Repository Service Provider
 *
 * Bind repository interfaces to their implementations.
 * This is where you configure which repository implementation to use.
 */
class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * {@inheritdoc}
     */
    public function register(ContainerInterface $container): void
    {
        // Bind ProductRepository to InMemory implementation
        // Change this to use PDO or other implementations as needed
        $container->bind(ProductRepository::class, fn() => new InMemoryProductRepository());

        // Example: To use PDO repository instead:
        // $container->bind(ProductRepository::class, function(ContainerInterface $c) {
        //     return new PdoProductRepository($c->get('db'));
        // });
    }
}
