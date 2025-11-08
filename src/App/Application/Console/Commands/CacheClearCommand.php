<?php

declare(strict_types=1);

namespace App\Application\Console\Commands;

use Toporia\Framework\Console\Command;
use Toporia\Framework\Cache\CacheManagerInterface;

/**
 * Cache Clear Command
 *
 * Clear application cache.
 *
 * Usage:
 *   php console cache:clear
 *   php console cache:clear --store=redis
 */
final class CacheClearCommand extends Command
{
    protected string $signature = 'cache:clear';
    protected string $description = 'Clear application cache';

    public function __construct(
        private readonly CacheManagerInterface $cacheManager
    ) {}

    public function handle(): int
    {
        $store = $this->option('store');

        try {
            if ($store) {
                $this->info("Clearing '{$store}' cache...");
                $this->cacheManager->driver($store)->clear();
            } else {
                $this->info('Clearing default cache...');
                $this->cacheManager->driver()->clear();
            }

            $this->success('Cache cleared successfully!');

            return 0;
        } catch (\Exception $e) {
            $this->error("Failed to clear cache: {$e->getMessage()}");
            return 1;
        }
    }
}
