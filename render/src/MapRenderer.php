<?php
/**
 * MapRenderer - Renders maps using MapServer shp2img
 */

declare(strict_types=1);

class MapRenderer
{
    private string $tempDir = '/tmp/mapserver';
    private string $shapesDir = '/app/mapserver/maps';
    private string $fontsDir = '/app/mapserver/fonts';

    public function __construct()
    {
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0777, true);
        }
    }

    /**
     * Render a map from request parameters
     *
     * @param array $request Map configuration
     * @return array Result with 'success', 'image' or 'error'
     */
    public function render(array $request): array
    {
        $startTime = microtime(true);

        // Generate unique filenames
        $id = uniqid('map_', true);
        $mapPath = $this->tempDir . '/' . $id . '.map';
        $output = $request['output'] ?? 'png';
        $outputPath = $this->tempDir . '/' . $id . '.' . $output;

        try {
            // Generate mapfile
            $generator = new MapfileGenerator($this->shapesDir, $this->fontsDir);
            $mapfile = $generator->generate($request);

            // Write mapfile
            file_put_contents($mapPath, $mapfile);

            // Build command
            $cmd = sprintf(
                'shp2img -m %s -o %s -all_debug 0 2>&1',
                escapeshellarg($mapPath),
                escapeshellarg($outputPath)
            );

            // Execute shp2img
            $cmdOutput = shell_exec($cmd);

            // Check if output was created
            if (!file_exists($outputPath)) {
                return [
                    'success' => false,
                    'error' => 'Render failed: ' . ($cmdOutput ?: 'No output generated'),
                    'mapfile' => $mapfile,
                    'render_time' => round((microtime(true) - $startTime) * 1000, 2)
                ];
            }

            // Read output image
            $image = file_get_contents($outputPath);

            // Cleanup
            @unlink($mapPath);
            @unlink($outputPath);

            return [
                'success' => true,
                'image' => $image,
                'render_time' => round((microtime(true) - $startTime) * 1000, 2)
            ];

        } catch (Exception $e) {
            // Cleanup on error
            @unlink($mapPath);
            @unlink($outputPath);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'render_time' => round((microtime(true) - $startTime) * 1000, 2)
            ];
        }
    }
}
