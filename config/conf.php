<?php
/**
 * SimpleMappr Configuration
 *
 * This file loads configuration from environment variables.
 * In Docker, these are set via docker-compose.yml or .env file.
 * For local development without Docker, copy .env.example to .env
 */

declare(strict_types=1);

// Environment: development, production, testing
defined('ENVIRONMENT') || define('ENVIRONMENT', getenv('ENVIRONMENT') ?: 'development');

// Application root directory
defined('ROOT') || define('ROOT', dirname(__DIR__));

// Render service URL
defined('RENDER_SERVICE_URL') || define('RENDER_SERVICE_URL',
    getenv('RENDER_SERVICE_URL') ?: 'http://localhost:8081');

// Database path (SQLite)
defined('DATABASE_PATH') || define('DATABASE_PATH',
    getenv('DATABASE_PATH') ?: ROOT . '/data/simplemappr.db');

// Google Analytics 4 Measurement ID (e.g. G-XXXXXXXXXX). Optional:
// leave unset/empty to disable analytics entirely.
defined('GA_MEASUREMENT_ID') || define('GA_MEASUREMENT_ID',
    preg_match('/^G-[A-Z0-9]+$/', (string) getenv('GA_MEASUREMENT_ID')) ? getenv('GA_MEASUREMENT_ID') : '');

// Google Tag Manager container ID (e.g. GTM-XXXXXXX). Optional: leave
// unset/empty to disable. Can be used instead of, or as well as, GA above.
defined('GTM_CONTAINER_ID') || define('GTM_CONTAINER_ID',
    preg_match('/^GTM-[A-Z0-9]+$/', (string) getenv('GTM_CONTAINER_ID')) ? getenv('GTM_CONTAINER_ID') : '');

// Map defaults
defined('DEFAULT_WIDTH') || define('DEFAULT_WIDTH', 900);
defined('DEFAULT_HEIGHT') || define('DEFAULT_HEIGHT', 450);
defined('DEFAULT_PROJECTION') || define('DEFAULT_PROJECTION', 'epsg:4326');

// Debug mode
defined('DEBUG') || define('DEBUG', ENVIRONMENT === 'development');

// Error reporting
if (DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

// Timezone
date_default_timezone_set('UTC');
