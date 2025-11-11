<?php

declare(strict_types=1);

namespace Toporia\Framework\Providers;

use Toporia\Framework\Container\ContainerInterface;
use Toporia\Framework\Foundation\ServiceProvider;
use Toporia\Framework\Process\ProcessManager;
use Toporia\Framework\Process\ProcessPool;

/**
 * Process Service Provider
 *
 * Registers multi-process services.
 */
final class ProcessServiceProvider extends ServiceProvider
{
    public function register(ContainerInterface $container): void
    {
        // Register ProcessManager
        $container->bind(ProcessManager::class, fn() => new ProcessManager());

        // Register ProcessPool with default worker count (CPU cores)
        $container->bind(ProcessPool::class, function () {
            $cores = $this->getCpuCoreCount();
            return new ProcessPool(workerCount: $cores);
        });

        // Convenience bindings
        $container->bind('process.manager', fn($c) => $c->get(ProcessManager::class));
        $container->bind('process.pool', fn($c) => $c->get(ProcessPool::class));
    }

    /**
     * Get CPU core count.
     *
     * @return int
     */
    private function getCpuCoreCount(): int
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return (int) ($_ENV['NUMBER_OF_PROCESSORS'] ?? 4);
        }

        // Linux/macOS
        $output = shell_exec('nproc 2>/dev/null || sysctl -n hw.ncpu 2>/dev/null || echo 4');
        return max(1, (int) trim($output));
    }
}
