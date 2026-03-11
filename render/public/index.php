<?php
/**
 * SimpleMappr Render Service
 *
 * A microservice that accepts JSON map descriptions and returns rendered images.
 * Uses MapServer's shp2img command-line tool.
 */

declare(strict_types=1);

require_once __DIR__ . '/../src/MapRenderer.php';
require_once __DIR__ . '/../src/MapfileGenerator.php';
require_once __DIR__ . '/../src/Projections.php';
require_once __DIR__ . '/../src/Layers.php';
require_once __DIR__ . '/../src/Symbols.php';

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Handle CORS preflight
if ($method === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Max-Age: 86400');
    http_response_code(204);
    exit;
}

// Set CORS headers for all responses
header('Access-Control-Allow-Origin: *');

// Route handling
try {
    switch (true) {
        case $method === 'GET' && $path === '/health':
            handleHealth();
            break;

        case $method === 'POST' && $path === '/render':
            handleRender();
            break;

        case $method === 'GET' && $path === '/projections':
            handleProjections();
            break;

        case $method === 'GET' && $path === '/layers':
            handleLayers();
            break;

        case $method === 'GET' && $path === '/shapes':
            handleShapes();
            break;

        default:
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Not found', 'path' => $path]);
    }
} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => $e->getMessage(),
        'trace' => getenv('ENVIRONMENT') === 'development' ? $e->getTraceAsString() : null
    ]);
}

/**
 * Health check endpoint
 */
function handleHealth(): void
{
    header('Content-Type: application/json');

    $mapserverVersion = trim(shell_exec('shp2img -v 2>&1') ?? 'unknown');
    $gdalVersion = trim(shell_exec('gdalinfo --version 2>&1') ?? 'unknown');

    echo json_encode([
        'status' => 'ok',
        'service' => 'simplemappr-render',
        'mapserver' => $mapserverVersion,
        'gdal' => $gdalVersion,
        'php' => PHP_VERSION,
        'timestamp' => date('c')
    ], JSON_PRETTY_PRINT);
}

/**
 * Main render endpoint
 */
function handleRender(): void
{
    $input = file_get_contents('php://input');
    $request = json_decode($input, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Invalid JSON: ' . json_last_error_msg()]);
        return;
    }

    $renderer = new MapRenderer();
    $result = $renderer->render($request);

    if ($result['success']) {
        $contentTypes = [
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'svg' => 'image/svg+xml',
            'tif' => 'image/tiff',
            'tiff' => 'image/tiff'
        ];

        $output = $request['output'] ?? 'png';
        $contentType = $contentTypes[$output] ?? 'image/png';

        header('Content-Type: ' . $contentType);
        header('Content-Length: ' . strlen($result['image']));
        header('X-Render-Time: ' . $result['render_time'] . 'ms');
        echo $result['image'];
    } else {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'error' => $result['error'],
            'mapfile' => getenv('ENVIRONMENT') === 'development' ? $result['mapfile'] ?? null : null
        ]);
    }
}

/**
 * List available projections
 */
function handleProjections(): void
{
    header('Content-Type: application/json');
    echo json_encode(Projections::all(), JSON_PRETTY_PRINT);
}

/**
 * List available layers
 */
function handleLayers(): void
{
    header('Content-Type: application/json');
    echo json_encode(Layers::all(), JSON_PRETTY_PRINT);
}

/**
 * List available marker shapes
 */
function handleShapes(): void
{
    header('Content-Type: application/json');
    echo json_encode(Symbols::shapes(), JSON_PRETTY_PRINT);
}
