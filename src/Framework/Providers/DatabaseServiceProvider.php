<?php

declare(strict_types=1);

namespace Toporia\Framework\Providers;

use Toporia\Framework\Container\ContainerInterface;
use Toporia\Framework\Database\Connection;
use Toporia\Framework\Database\ConnectionInterface;
use Toporia\Framework\Database\DatabaseManager;
use Toporia\Framework\Database\ORM\Model;
use Toporia\Framework\Foundation\ServiceProvider;

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

        // Default connection alias (lazy - only connects when actually used)
        $container->singleton('db', fn(ContainerInterface $c) => $c->get(DatabaseManager::class)->connection());
        $container->bind(ConnectionInterface::class, fn(ContainerInterface $c) => $c->get('db'));
        $container->bind(Connection::class, fn(ContainerInterface $c) => $c->get('db'));
    }

    /**
     * {@inheritdoc}
     */
    public function boot(ContainerInterface $container): void
    {
        // Set default connection for ORM Models
        try {
            $db = $container->get(DatabaseManager::class);
            Model::setConnection($db->connection());
        } catch (\Throwable $e) {
            // Database not configured - Models will fail when used
            // This allows application to boot even without database
        }
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

            if (!empty($connections) && isset($connections[$defaultConnection])) {
                return ['default' => $connections[$defaultConnection]];
            }
        } catch (\Throwable $e) {
            // Config not available, use fallback
        }

        // Fallback to environment variables
        $driver = env('DB_CONNECTION', 'mysql');
        return [
            'default' => [
                'driver' => $driver,
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
