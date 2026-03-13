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
    private Translator $translator;

    public function __construct()
    {
        // Initialize translator with detected or requested locale
        $locale = Translator::detectLocale();
        $this->translator = Translator::getInstance($locale);
    }

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
            case $path === '/' || $path === '' || $path === '/editor':
                $this->handleEditor();
                break;

            case $path === '/health':
                $this->handleHealth();
                break;

            case $path === '/status':
                $this->handleStatus();
                break;

            case $path === '/lang':
                $this->handleLanguageSwitch();
                break;

            case $path === '/api/i18n':
                $this->handleI18nApi();
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
     * Handle language switch
     */
    private function handleLanguageSwitch(): void
    {
        $lang = $_GET['lang'] ?? $_POST['lang'] ?? 'en';

        if (isset(Translator::SUPPORTED_LOCALES[$lang])) {
            $this->translator->setLocaleCookie($lang);
            $this->translator->setLocale($lang);
        }

        // Redirect back to referrer or home
        $redirect = $_SERVER['HTTP_REFERER'] ?? '/';
        header('Location: ' . $redirect);
        exit;
    }

    /**
     * API endpoint for i18n translations
     */
    private function handleI18nApi(): void
    {
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        header('Cache-Control: public, max-age=3600');

        $section = $_GET['section'] ?? null;

        $response = [
            'locale' => $this->translator->getLocale(),
            'supported' => $this->translator->getSupportedLocales()
        ];

        if ($section) {
            $response['translations'] = $this->translator->section($section);
        } else {
            $response['translations'] = $this->translator->all();
        }

        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Home page
     */
    private function handleHome(): void
    {
        header('Content-Type: text/html; charset=utf-8');
        echo $this->getHomeHtml();
    }

    /**
     * Map editor page
     */
    private function handleEditor(): void
    {
        header('Content-Type: text/html; charset=utf-8');
        $editorFile = ROOT . '/public/editor.html';
        if (file_exists($editorFile)) {
            $html = file_get_contents($editorFile);

            // Inject i18n data into the page
            $i18nScript = sprintf(
                '<script>window.SimpleMappr = window.SimpleMappr || {}; ' .
                'window.SimpleMappr.locale = %s; ' .
                'window.SimpleMappr.i18n = %s; ' .
                'window.SimpleMappr.supportedLocales = %s;</script>',
                json_encode($this->translator->getLocale()),
                json_encode($this->translator->all(), JSON_UNESCAPED_UNICODE),
                json_encode($this->translator->getSupportedLocales(), JSON_UNESCAPED_UNICODE)
            );

            // Insert before </head>
            $html = str_replace('</head>', $i18nScript . "\n</head>", $html);

            echo $html;
        } else {
            http_response_code(500);
            echo 'Editor not found';
        }
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
     * Status page with map data availability
     */
    private function handleStatus(): void
    {
        header('Content-Type: text/html; charset=utf-8');

        $t = $this->translator;
        $locale = $t->getLocale();

        // Get service health
        $health = $this->getHealthStatus();

        // Define map data sources with file paths and attribution
        $mapSources = [
            'Natural Earth - Physical' => [
                'url' => 'https://www.naturalearthdata.com/',
                'logo' => '/images/logos/natural-earth.png',
                'license_key' => 'health.license_public_domain',
                'files' => [
                    'ne_10m_land' => '/mapserver/maps/10m_physical/ne_10m_land.shp',
                    'ne_10m_ocean' => '/mapserver/maps/10m_physical/ne_10m_ocean.shp',
                    'ne_10m_lakes' => '/mapserver/maps/10m_physical/ne_10m_lakes.shp',
                    'ne_10m_rivers' => '/mapserver/maps/10m_physical/ne_10m_rivers_lake_centerlines.shp',
                ]
            ],
            'Natural Earth - Cultural' => [
                'url' => 'https://www.naturalearthdata.com/',
                'logo' => '/images/logos/natural-earth.png',
                'license_key' => 'health.license_public_domain',
                'files' => [
                    'ne_10m_admin_0 (countries)' => '/mapserver/maps/10m_cultural/10m_cultural/ne_10m_admin_0_map_units.shp',
                    'ne_10m_admin_1 (states/provinces)' => '/mapserver/maps/10m_cultural/10m_cultural/ne_10m_admin_1_states_provinces.shp',
                    'ne_10m_roads' => '/mapserver/maps/10m_cultural/10m_cultural/ne_10m_roads.shp',
                    'ne_10m_railroads' => '/mapserver/maps/10m_cultural/10m_cultural/ne_10m_railroads.shp',
                ]
            ],
            'Natural Earth - Rasters' => [
                'url' => 'https://www.naturalearthdata.com/downloads/10m-raster-data/',
                'logo' => '/images/logos/natural-earth.png',
                'license_key' => 'health.license_public_domain',
                'files' => [
                    'Cross-blend hypsometry (HYP_HR_SR_OB_DR)' => '/mapserver/maps/HYP_HR_SR_OB_DR/HYP_HR_SR_OB_DR.tif',
                    'Greyscale relief (GRAY_HR_SR_OB_DR)' => '/mapserver/maps/GRAY_HR_SR_OB_DR/GRAY_HR_SR_OB_DR.tif',
                    'Blue Marble' => '/mapserver/maps/blue_marble/land_shallow_topo_21600.tif',
                ]
            ],
            'Conservation International' => [
                'url' => 'https://www.conservation.org/priorities/biodiversity-hotspots',
                'logo' => '/images/logos/conservation-international.svg',
                'license_key' => 'health.license_cc_by_sa',
                'license_url' => 'https://creativecommons.org/licenses/by-sa/4.0/',
                'doi' => '10.5281/zenodo.3261807',
                'files' => [
                    'Biodiversity Hotspots' => '/mapserver/maps/conservation_international/hotspots_2016_1.shp',
                ]
            ],
            'WWF' => [
                'url' => 'https://www.worldwildlife.org/',
                'logo' => '/images/logos/wwf.svg',
                'license_key' => 'health.license_contact_wwf',
                'files' => [
                    'Terrestrial Ecoregions' => '/mapserver/maps/wwf_terr_ecos/wwf_terr_ecos.shp',
                    'Marine Ecoregions (MEOW)' => '/mapserver/maps/wwf_meow/meow_ecos.shp',
                ]
            ]
        ];

        // Check file availability
        $renderMapsPath = RENDER_SERVICE_URL ? $this->getRenderMapsPath() : ROOT . '/mapserver/maps';

        if (isset($_GET['partial'])) {
            echo $this->buildStatusBodyHtml($t, $health, $mapSources);
        } else {
            echo $this->getStatusHtml($t, $locale, $health, $mapSources, $renderMapsPath);
        }
    }

    /**
     * Get health status as array
     */
    private function getHealthStatus(): array
    {
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
            $status['database'] = 'error';
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
            $status['render_service'] = 'error';
            $status['status'] = 'degraded';
        }

        return $status;
    }

    /**
     * Check if a map file exists via render service
     */
    private function getRenderMapsPath(): string
    {
        // Query the render service for its maps path
        $ch = curl_init(RENDER_SERVICE_URL . '/health');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $response = curl_exec($ch);
        curl_close($ch);

        // Default path used in render service
        return '/app/mapserver/maps';
    }

    /**
     * Check if map file exists via render service API
     */
    private function checkMapFileViaRender(string $relativePath): bool
    {
        $url = RENDER_SERVICE_URL . '/check-file?path=' . urlencode($relativePath);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $data = json_decode($response, true);
            return $data['exists'] ?? false;
        }
        return false;
    }

    /**
     * Generate status page HTML
     */
    private function buildStatusBodyHtml(Translator $t, array $health, array $mapSources): string
    {
        $statusText = $health['status'] === 'ok' ? $t->t('health.connected') : $t->t('health.degraded');

        $servicesHtml = '';
        $dbStatus = $health['database'] === 'connected' ? $t->t('health.connected') : $t->t('health.error');
        $dbClass = $health['database'] === 'connected' ? 'status-ok' : 'status-error';
        $servicesHtml .= '<tr><td>' . $t->t('health.database') . '</td><td class="' . $dbClass . '">' . $dbStatus . '</td></tr>';

        $renderStatus = $health['render_service'] === 'connected' ? $t->t('health.connected') : $t->t('health.error');
        $renderClass = $health['render_service'] === 'connected' ? 'status-ok' : 'status-error';
        $servicesHtml .= '<tr><td>' . $t->t('health.render_service') . '</td><td class="' . $renderClass . '">' . $renderStatus . '</td></tr>';

        $mapsHtml = '';
        foreach ($mapSources as $sourceName => $source) {
            $logoHtml = '';
            if (isset($source['logo'])) {
                $logoHtml = '<img src="' . htmlspecialchars($source['logo']) . '" alt="" class="provider-logo">';
            }
            $mapsHtml .= '<h3 class="provider-heading">' . $logoHtml . '<a href="' . htmlspecialchars($source['url']) . '" target="_blank">' . htmlspecialchars($sourceName) . '</a></h3>';
            $licenseText = $t->t($source['license_key']);
            if (isset($source['license_url'])) {
                $licenseText = '<a href="' . htmlspecialchars($source['license_url']) . '" target="_blank">' . htmlspecialchars($licenseText) . '</a>';
            } else {
                $licenseText = htmlspecialchars($licenseText);
            }
            $mapsHtml .= '<p class="license">' . $t->t('health.license') . ': ' . $licenseText;
            if (isset($source['doi'])) {
                $doiUrl = 'https://doi.org/' . $source['doi'];
                $mapsHtml .= ' | DOI: <a href="' . htmlspecialchars($doiUrl) . '" target="_blank">' . htmlspecialchars($source['doi']) . '</a>';
            }
            $mapsHtml .= '</p>';
            $mapsHtml .= '<table class="files-table">';

            foreach ($source['files'] as $name => $path) {
                $exists = $this->checkMapFileViaRender($path);
                $fileStatusClass = $exists ? 'status-ok' : 'status-missing';
                $statusIcon = $exists ? '✓' : '✗';
                $mapsHtml .= '<tr>';
                $mapsHtml .= '<td>' . htmlspecialchars($name) . '</td>';
                $mapsHtml .= '<td class="' . $fileStatusClass . '">' . $statusIcon . '</td>';
                $mapsHtml .= '</tr>';
            }

            $mapsHtml .= '</table>';
        }
        $mapsHtml .= '<p class="logo-disclaimer">' . htmlspecialchars($t->t('health.logo_disclaimer')) . '</p>';

        return <<<HTML
    <h1>{$t->t('health.status')}</h1>

    <div class="overall-status {$health['status']}">{$statusText}</div>

    <div class="section">
        <h2>{$t->t('health.services')}</h2>
        <table>
            <tr><th>{$t->t('health.service')}</th><th>{$t->t('health.status')}</th></tr>
            {$servicesHtml}
            <tr><td>PHP</td><td>{$health['php']}</td></tr>
            <tr><td>{$t->t('health.environment')}</td><td>{$health['environment']}</td></tr>
        </table>
    </div>

    <div class="section">
        <h2>{$t->t('health.map_data')}</h2>
        <p style="margin-bottom: 1rem; color: #666;">{$t->t('health.map_data_description')}</p>
        {$mapsHtml}
    </div>
HTML;
    }

    private function getStatusHtml(Translator $t, string $locale, array $health, array $mapSources, string $mapsPath): string
    {
        $bodyHtml = $this->buildStatusBodyHtml($t, $health, $mapSources);
        $backLink = htmlspecialchars($t->t('health.back_to_editor'));

        return <<<HTML
<!DOCTYPE html>
<html lang="{$locale}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$t->t('general.app_name')} - {$t->t('health.status')}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 900px;
            margin: 0 auto;
            padding: 2rem;
            background: #f5f5f5;
        }
        header {
            background: #2c3e50;
            color: white;
            padding: 1rem 2rem;
            margin: -2rem -2rem 2rem -2rem;
        }
        header a { color: white; text-decoration: none; }
        header h1 { font-size: 1.5rem; }
        h1 { margin-bottom: 1rem; }
        h2 { color: #2c3e50; margin: 2rem 0 1rem 0; border-bottom: 2px solid #3498db; padding-bottom: 0.5rem; }
        h3 { color: #555; margin: 1.5rem 0 0.5rem 0; }
        h3 a { color: #3498db; }
        .provider-heading { display: flex; align-items: center; gap: 0.5rem; }
        .provider-logo { width: 48px; height: 48px; object-fit: contain; flex-shrink: 0; }
        .logo-disclaimer { font-size: 0.8rem; color: #999; margin-top: 1rem; font-style: italic; }
        .license { font-size: 0.85rem; color: #777; margin-bottom: 0.5rem; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 1rem; background: white; }
        th, td { padding: 0.75rem; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f8f9fa; font-weight: 600; }
        .files-table td:last-child { width: 50px; text-align: center; font-weight: bold; }
        .status-ok { color: #27ae60; }
        .status-error { color: #e74c3c; }
        .status-missing { color: #e74c3c; }
        .overall-status {
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
            font-weight: 600;
        }
        .overall-status.ok { background: #d4edda; color: #155724; }
        .overall-status.error { background: #f8d7da; color: #721c24; }
        .back-link { margin-top: 2rem; }
        .back-link a { color: #3498db; }
        .section { background: white; padding: 1.5rem; border-radius: 4px; margin-bottom: 1rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
    <header>
        <h1><a href="/">{$t->t('general.app_name')}</a></h1>
    </header>

    {$bodyHtml}

    <div class="back-link">
        <a href="/">{$backLink}</a>
    </div>
</body>
</html>
HTML;
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
     * HTML for home page with i18n support
     */
    private function getHomeHtml(): string
    {
        $t = $this->translator;
        $locale = $t->getLocale();
        $locales = $t->getSupportedLocales();

        // Build language switcher
        $langLinks = [];
        foreach ($locales as $code => $info) {
            if ($code === $locale) {
                $langLinks[] = '<strong>' . $info['native'] . '</strong>';
            } else {
                $langLinks[] = '<a href="/lang?lang=' . $code . '">' . $info['native'] . '</a>';
            }
        }
        $langSwitcher = implode(' | ', $langLinks);

        return <<<HTML
<!DOCTYPE html>
<html lang="{$locale}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$t->t('general.app_name')}</title>
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
        .lang-switcher { text-align: right; margin-bottom: 1rem; font-size: 0.9rem; }
        .status { padding: 1rem; background: #e8f5e9; border-radius: 4px; margin: 1rem 0; }
        .status.loading { background: #fff3e0; }
        .status.error { background: #ffebee; }
        code { background: #f5f5f5; padding: 0.2em 0.4em; border-radius: 3px; }
        pre { background: #f5f5f5; padding: 1rem; border-radius: 4px; overflow-x: auto; }
        a { color: #3498db; }
    </style>
</head>
<body>
    <div class="lang-switcher">{$langSwitcher}</div>

    <h1>{$t->t('general.app_name')}</h1>
    <p>{$t->t('general.tagline')}</p>

    <p style="margin: 1.5rem 0;">
        <a href="/editor" style="display: inline-block; padding: 0.75rem 1.5rem; background: #3498db; color: white; text-decoration: none; border-radius: 4px; font-weight: bold;">{$t->t('editor.title')}</a>
    </p>

    <div id="status" class="status loading">
        {$t->t('general.loading')}
    </div>

    <h2>Quick Links</h2>
    <ul>
        <li><a href="/editor">{$t->t('editor.title')}</a></li>
        <li><a href="/api">{$t->t('nav.api')}</a></li>
        <li><a href="/health">{$t->t('health.status')}</a></li>
    </ul>

    <script>
        fetch('/health')
            .then(r => r.json())
            .then(data => {
                const el = document.getElementById('status');
                el.className = 'status ' + (data.status === 'ok' ? '' : 'error');
                el.innerHTML = '<strong>{$t->t('health.status')}:</strong> ' + data.status +
                    '<br><strong>{$t->t('health.database')}:</strong> ' + data.database +
                    '<br><strong>{$t->t('health.render_service')}:</strong> ' + data.render_service;
            })
            .catch(err => {
                const el = document.getElementById('status');
                el.className = 'status error';
                el.textContent = '{$t->t('general.error')}: ' + err.message;
            });
    </script>
</body>
</html>
HTML;
    }
}
