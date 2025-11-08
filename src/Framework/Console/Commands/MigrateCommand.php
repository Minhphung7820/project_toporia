<?php

declare(strict_types=1);

namespace Toporia\Framework\Console\Commands;

use Toporia\Framework\Console\Command;
use Toporia\Framework\Database\DatabaseManager;
use Toporia\Framework\Database\Schema\SchemaBuilder;

/**
 * Run database migrations.
 */
final class MigrateCommand extends Command
{
    protected string $signature = 'migrate';
    protected string $description = 'Run database migrations';

    public function __construct(
        private DatabaseManager $db
    ) {
    }

    /**
     * Execute the command.
     */
    public function handle(): int
    {
        $this->info('Running migrations...');
        $this->newLine();

        try {
            $connection = $this->db->connection();
            $schema = new SchemaBuilder($connection);

            // Get all migration files
            $migrationsPath = dirname(__DIR__, 4) . '/database/migrations';

            if (!is_dir($migrationsPath)) {
                $this->error('Migrations directory not found!');
                return 1;
            }

            $files = glob($migrationsPath . '/*.php');

            if (empty($files)) {
                $this->warn('No migrations found.');
                return 0;
            }

            sort($files);

            foreach ($files as $file) {
                $migrationName = basename($file, '.php');

                $this->write("Migrating: <comment>{$migrationName}</comment>");

                require_once $file;

                $migration = new $migrationName();
                $migration->setSchema($schema);
                $migration->up();

                $this->writeln(' <info>✓ DONE</info>');
            }

            $this->newLine();
            $this->success('Migrations completed successfully!');

            return 0;
        } catch (\Exception $e) {
            $this->newLine();
            $this->error('Migration failed!');
            $this->error($e->getMessage());

            return 1;
        }
    }
}
