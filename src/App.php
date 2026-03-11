<?php
/**
 * SimpleMappr Application
 *
 * Main application class that handles routing and request processing.
 */

declare(strict_types=1);

namespace SimpleMappr;

class App
{
    /**
     * Run the application
     */
    public function run(): void
    {
        // Get request info
        $method = $_SERVER['REQUEST_METHOD'];
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        // Simple routing for now
        switch (true) {
            case $path === '/' || $path === '':
                $this->handleHome();
                break;

            case $path === '/health':
                $this->handleHealth();
                break;

            case strpos($path, '/api') === 0:
                $this->handleApi($method, $path);
                break;

            case strpos($path, '/render') === 0:
                $this->handleRender();
                break;

            default:
                $this->handleNotFound();
        }
    }

    /**
     * Home page
     */
    private function handleHome(): void
    {
        header('Content-Type: text/html; charset=utf-8');
        echo $this->renderTemplate('home');
    }

    /**
     * Health check endpoint
     */
    private function handleHealth(): void
    {
        header('Content-Type: application/json');

        $status = [
            'status' => 'ok',
            'service' => 'simplemappr-app',
            'environment' => ENVIRONMENT,
            'php' => PHP_VERSION,
            'timestamp' => date('c')
        ];

        // Check database
        try {
            $db = Database::getInstance();
            $status['database'] = 'connected';
        } catch (\Exception $e) {
            $status['database'] = 'error: ' . $e->getMessage();
            $status['status'] = 'degraded';
        }

        // Check render service
        $renderUrl = RENDER_SERVICE_URL . '/health';
        $ch = curl_init($renderUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $status['render_service'] = 'connected';
        } else {
            $status['render_service'] = 'error: HTTP ' . $httpCode;
            $status['status'] = 'degraded';
        }

        echo json_encode($status, JSON_PRETTY_PRINT);
    }

    /**
     * API endpoint
     */
    private function handleApi(string $method, string $path): void
    {
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');

        if ($method === 'OPTIONS') {
            header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type');
            http_response_code(204);
            return;
        }

        // For now, just show API info
        echo json_encode([
            'service' => 'SimpleMappr API',
            'version' => '2.0.0',
            'endpoints' => [
                'GET /api' => 'API information',
                'POST /api/render' => 'Render a map',
                'GET /api/projections' => 'List available projections',
                'GET /api/layers' => 'List available layers',
                'GET /api/shapes' => 'List available marker shapes'
            ]
        ], JSON_PRETTY_PRINT);
    }

    /**
     * Proxy render requests to render service
     */
    private function handleRender(): void
    {
        $input = file_get_contents('php://input');

        $ch = curl_init(RENDER_SERVICE_URL . '/render');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $input,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: image/png'
            ],
            CURLOPT_RETURNTRANSFER => true
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        http_response_code($httpCode);
        header('Content-Type: ' . ($contentType ?: 'image/png'));
        echo $response;
    }

    /**
     * 404 handler
     */
    private function handleNotFound(): void
    {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Not found']);
    }

    /**
     * Simple template renderer
     */
    private function renderTemplate(string $name): string
    {
        $templateFile = ROOT . '/views/' . $name . '.html';

        if (file_exists($templateFile)) {
            return file_get_contents($templateFile);
        }

        // Fallback HTML
        return $this->getDefaultHtml();
    }

    /**
     * Default HTML for home page
     */
    private function getDefaultHtml(): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SimpleMappr</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem;
        }
        h1 { color: #2c3e50; margin-bottom: 1rem; }
        .status { padding: 1rem; background: #e8f5e9; border-radius: 4px; margin: 1rem 0; }
        .status.loading { background: #fff3e0; }
        .status.error { background: #ffebee; }
        code { background: #f5f5f5; padding: 0.2em 0.4em; border-radius: 3px; }
        pre { background: #f5f5f5; padding: 1rem; border-radius: 4px; overflow-x: auto; }
        a { color: #3498db; }
    </style>
</head>
<body>
    <h1>SimpleMappr</h1>
    <p>A point map web application for quality publications and presentations.</p>

    <div id="status" class="status loading">
        Checking service status...
    </div>

    <h2>Quick Links</h2>
    <ul>
        <li><a href="/api">API Documentation</a></li>
        <li><a href="/health">Health Check</a></li>
    </ul>

    <h2>Test Render</h2>
    <p>Send a POST request to <code>/render</code> with JSON body:</p>
    <pre>{
  "output": "png",
  "width": 400,
  "height": 200,
  "layers": ["countries"],
  "points": [{
    "legend": "Test",
    "shape": "circle",
    "size": 10,
    "color": [255, 0, 0],
    "coordinates": [[45.5, -75.5]]
  }]
}</pre>

    <script>
        fetch('/health')
            .then(r => r.json())
            .then(data => {
                const el = document.getElementById('status');
                el.className = 'status ' + (data.status === 'ok' ? '' : 'error');
                el.innerHTML = '<strong>Status:</strong> ' + data.status +
                    '<br><strong>Database:</strong> ' + data.database +
                    '<br><strong>Render Service:</strong> ' + data.render_service;
            })
            .catch(err => {
                const el = document.getElementById('status');
                el.className = 'status error';
                el.textContent = 'Error checking status: ' + err.message;
            });
    </script>
</body>
</html>
HTML;
    }
}
