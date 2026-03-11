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

// Application URL (no trailing slash)
defined('APP_URL') || define('APP_URL', getenv('APP_URL') ?: 'http://localhost:8080');

// Render service URL
defined('RENDER_SERVICE_URL') || define('RENDER_SERVICE_URL',
    getenv('RENDER_SERVICE_URL') ?: 'http://localhost:8081');

// Database path (SQLite)
defined('DATABASE_PATH') || define('DATABASE_PATH',
    getenv('DATABASE_PATH') ?: ROOT . '/data/simplemappr.db');

// OAuth: ORCID
defined('ORCID_CLIENT_ID') || define('ORCID_CLIENT_ID', getenv('ORCID_CLIENT_ID') ?: '');
defined('ORCID_CLIENT_SECRET') || define('ORCID_CLIENT_SECRET', getenv('ORCID_CLIENT_SECRET') ?: '');

// OAuth: Google
defined('GOOGLE_CLIENT_ID') || define('GOOGLE_CLIENT_ID', getenv('GOOGLE_CLIENT_ID') ?: '');
defined('GOOGLE_CLIENT_SECRET') || define('GOOGLE_CLIENT_SECRET', getenv('GOOGLE_CLIENT_SECRET') ?: '');

// Session/security
defined('APP_SECRET') || define('APP_SECRET', getenv('APP_SECRET') ?: 'change-me-in-production');

// Cookie settings
defined('COOKIE_TIMEOUT') || define('COOKIE_TIMEOUT', 60 * 60 * 24 * 14); // 2 weeks

// Upload directory
defined('UPLOAD_DIRECTORY') || define('UPLOAD_DIRECTORY', ROOT . '/tmp');

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
