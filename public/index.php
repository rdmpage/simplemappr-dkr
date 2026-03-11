<?php
/**
 * SimpleMappr - Front Controller
 *
 * This is the entry point for all HTTP requests.
 */

declare(strict_types=1);

// Load configuration
require_once __DIR__ . '/../config/conf.php';

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Initialize application
use SimpleMappr\App;

// Create and run application
$app = new App();
$app->run();
