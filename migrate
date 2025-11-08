#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Database Migration Runner
 *
 * This script runs all database migrations.
 * Usage: php migrate.php
 */

require __DIR__ . '/vendor/autoload.php';

// Bootstrap the application
$app = require __DIR__ . '/bootstrap/app.php';

// Get the console application
$console = $app->getContainer()->get(\Toporia\Framework\Console\Application::class);

// Run migrate command
exit($console->run(['migrate']));
