<?php

declare(strict_types=1);

namespace Framework\Providers;

use Framework\Container\ContainerInterface;
use Framework\Database\Connection;
use Framework\Database\ConnectionInterface;
use Framework\Database\DatabaseManager;
use Framework\Database\ORM\Model;
use Framework\Foundation\ServiceProvider;

/**
 * Database Service Provider
 *
 * Registers database services including connections and ORM.
 */
class DatabaseServiceProvider extends ServiceProvider
{
    /**
     * {@inheritdoc}
     */
    public function register(ContainerInterface $container): void
    {
        // Database Manager - Singleton for managing multiple connections
        $container->singleton(DatabaseManager::class, function () {
            $config = $this->getDatabaseConfig();
            return new DatabaseManager($config);
        });

        // Default connection alias
        $container->bind('db', fn(ContainerInterface $c) => $c->get(DatabaseManager::class)->connection());
        $container->bind(ConnectionInterface::class, fn(ContainerInterface $c) => $c->get('db'));
        $container->bind(Connection::class, fn(ContainerInterface $c) => $c->get('db'));
    }

    /**
     * {@inheritdoc}
     */
    public function boot(ContainerInterface $container): void
    {
        // Set default connection for ORM models
        $db = $container->get(DatabaseManager::class);
        Model::setConnection($db->connection());
    }

    /**
     * Get database configuration.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function getDatabaseConfig(): array
    {
        // Try to get from config service if available
        try {
            $config = container('config');
            $defaultConnection = $config->get('database.default', 'mysql');
            $connections = $config->get('database.connections', []);

            if (!empty($connections)) {
                return [$defaultConnection => $connections[$defaultConnection]];
            }
        } catch (\Throwable $e) {
            // Config not available, use fallback
        }

        // Fallback to environment variables
        return [
            'default' => [
                'driver' => env('DB_DRIVER', 'mysql'),
                'host' => env('DB_HOST', 'localhost'),
                'port' => (int) env('DB_PORT', 3306),
                'database' => env('DB_NAME', 'project_topo'),
                'username' => env('DB_USER', 'root'),
                'password' => env('DB_PASS', ''),
                'charset' => 'utf8mb4',
            ],
        ];
    }
}
