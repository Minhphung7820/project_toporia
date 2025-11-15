<?php

declare(strict_types=1);

namespace App\Providers;

use Toporia\Framework\Container\ContainerInterface;
use Toporia\Framework\Foundation\ServiceProvider;
use App\Domain\Product\ProductRepository;
use App\Infrastructure\Persistence\EloquentProductRepository;
use App\Infrastructure\Persistence\InMemoryProductRepository;

/**
 * Repository Service Provider
 *
 * Bind repository interfaces to their implementations.
 * This is where you configure which repository implementation to use.
 *
 * Clean Architecture:
 * - Binds Domain interfaces (ProductRepository) to Infrastructure implementations
 * - This is the Dependency Inversion in action
 * - Domain layer doesn't know about Infrastructure
 */
class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * {@inheritdoc}
     */
    public function register(ContainerInterface $container): void
    {
        // Bind ProductRepository to Eloquent implementation (database-backed)
        // This uses ProductModel for actual database operations
        $container->bind(ProductRepository::class, fn() => new EloquentProductRepository());

        // Alternative: Use in-memory implementation (for testing)
        // $container->bind(ProductRepository::class, fn() => new InMemoryProductRepository());

        // Alternative: Use PDO repository (custom SQL)
        // $container->bind(ProductRepository::class, function(ContainerInterface $c) {
        //     return new PdoProductRepository($c->get('db'));
        // });
    }
}
