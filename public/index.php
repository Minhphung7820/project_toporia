<?php

declare(strict_types=1);

/**
 * Application Entry Point
 *
 * This file is the entry point for all HTTP requests.
 * It bootstraps the application and handles the incoming request.
 */

// Error reporting for development
ini_set('display_errors', '1');
error_reporting(E_ALL);

// Start session
session_start();

/*
|--------------------------------------------------------------------------
| Register The Autoloader
|--------------------------------------------------------------------------
*/

require __DIR__ . '/../vendor/autoload.php';

/*
|--------------------------------------------------------------------------
| Bootstrap The Application
|--------------------------------------------------------------------------
|
| Load the application with all service providers registered.
|
*/

/** @var \Toporia\Framework\Foundation\Application $app */
$app = require __DIR__ . '/../bootstrap/app.php';

/*
|--------------------------------------------------------------------------
| Load Helper Functions
|--------------------------------------------------------------------------
*/

require __DIR__ . '/../bootstrap/helpers.php';

/*
|--------------------------------------------------------------------------
| Run The Application
|--------------------------------------------------------------------------
|
| Dispatch the HTTP request through the router.
|
*/

$app->make(\Toporia\Framework\Routing\Router::class)->dispatch();
