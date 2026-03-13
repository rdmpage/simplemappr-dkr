<?php
/**
 * MapfileGenerator - Generates MapServer mapfiles from JSON configuration
 */

declare(strict_types=1);

class MapfileGenerator
{
    private string $shapesDir;
    private string $fontsDir;

    public function __construct(string $shapesDir, string $fontsDir)
    {
        $this->shapesDir = $shapesDir;
        $this->fontsDir = $fontsDir;
    }

    /**
     * Generate a complete mapfile from request parameters
     */
    public function generate(array $request): string
    {
        $width = (int)($request['width'] ?? 900);
        $height = (int)($request['height'] ?? 450);
        $projection = $request['projection'] ?? 'epsg:4326';
        $output = $request['output'] ?? 'png';

        // Get projection-specific default extent if not provided or if using degrees with projected CRS
        $projDef = Projections::get($projection);
        $defaultBbox = $projDef['extent'] ?? [-180, -90, 180, 90];
        $bbox = $request['bbox'] ?? $defaultBbox;

        // If bbox looks like degrees but projection uses meters, use projection default
        if ($projection !== 'epsg:4326' &&
            abs($bbox[0]) <= 180 && abs($bbox[1]) <= 90 &&
            abs($bbox[2]) <= 180 && abs($bbox[3]) <= 90) {
            $bbox = $defaultBbox;
        }

        $map = "MAP\n";
        $map .= "  NAME \"simplemappr\"\n";
        $map .= "  STATUS ON\n";
        $map .= "  SIZE {$width} {$height}\n";
        $map .= "  EXTENT {$bbox[0]} {$bbox[1]} {$bbox[2]} {$bbox[3]}\n";
        $map .= "  UNITS DD\n";
        $map .= "  IMAGECOLOR 255 255 255\n";
        $map .= "  FONTSET \"{$this->fontsDir}/fonts.list\"\n";
        $map .= "  SHAPEPATH \"{$this->shapesDir}\"\n";
        $map .= "\n";

        // Config block
        $map .= "  CONFIG \"PROJ_LIB\" \"/usr/share/proj\"\n";
        $map .= "  CONFIG \"MS_ERRORFILE\" \"stderr\"\n";
        $map .= "\n";

        // Web block
        $map .= "  WEB\n";
        $map .= "    IMAGEPATH \"/tmp/mapserver\"\n";
        $map .= "    IMAGEURL \"/tmp\"\n";
        $map .= "  END\n";
        $map .= "\n";

        // Map projection
        $map .= $this->generateProjection($projection);

        // Output format
        $map .= $this->generateOutputFormat($output);

        // Symbol definitions
        $map .= $this->generateSymbols();

        // Line thickness multiplier
        $lineThickness = (float)($request['line_thickness'] ?? 1.0);

        // Base layers
        $map .= $this->generateBaseLayers($request['layers'] ?? ['countries'], $projection, $lineThickness);

        // Point layers
        foreach ($request['points'] ?? [] as $i => $pointSet) {
            $map .= $this->generatePointLayer($pointSet, $i, $projection);
        }

        // Region layers
        foreach ($request['regions'] ?? [] as $i => $region) {
            $map .= $this->generateRegionLayer($region, $i, $projection);
        }

        // WKT layers
        foreach ($request['wkt'] ?? [] as $i => $wkt) {
            $map .= $this->generateWktLayer($wkt, $i, $projection);
        }

        // Graticules
        if (!empty($request['graticules']['enabled'])) {
            $map .= $this->generateGraticules($request['graticules'], $projection);
        }

        // Legend
        if (!empty($request['options']['legend'])) {
            $map .= $this->generateLegend();
        }

        // Scalebar
        if (!empty($request['options']['scalebar'])) {
            $map .= $this->generateScalebar($width);
        }

        $map .= "END\n";

        return $map;
    }

    private function generateProjection(string $projection): string
    {
        $projDef = Projections::get($projection);

        $block = "  PROJECTION\n";
        if ($projDef && isset($projDef['proj'])) {
            // Parse PROJ.4 string and output each parameter on its own line
            $params = $this->parseProjString($projDef['proj']);
            foreach ($params as $param) {
                $block .= "    \"{$param}\"\n";
            }
        } else {
            // Fallback to WGS84
            $block .= "    \"proj=longlat\"\n";
            $block .= "    \"ellps=WGS84\"\n";
            $block .= "    \"datum=WGS84\"\n";
            $block .= "    \"no_defs\"\n";
        }
        $block .= "  END\n\n";

        return $block;
    }

    private function parseProjString(string $proj): array
    {
        // Remove leading + and split by space
        $parts = preg_split('/\s+/', trim($proj));
        $params = [];
        foreach ($parts as $part) {
            $part = ltrim($part, '+');
            if (!empty($part)) {
                $params[] = $part;
            }
        }
        return $params;
    }

    private function getWgs84Projection(int $indent = 4): string
    {
        $pad = str_repeat(' ', $indent);
        return "{$pad}\"proj=longlat\"\n" .
               "{$pad}\"ellps=WGS84\"\n" .
               "{$pad}\"datum=WGS84\"\n" .
               "{$pad}\"no_defs\"\n";
    }

    private function generateOutputFormat(string $output): string
    {
        $formats = [
            'png' => [
                'driver' => 'AGG/PNG',
                'mimetype' => 'image/png',
                'imagemode' => 'RGB',
                'extension' => 'png',
                'formatoptions' => ['INTERLACE=OFF', 'COMPRESSION=9']
            ],
            'jpg' => [
                'driver' => 'AGG/JPEG',
                'mimetype' => 'image/jpeg',
                'imagemode' => 'RGB',
                'extension' => 'jpg',
                'formatoptions' => ['QUALITY=95']
            ],
            'svg' => [
                'driver' => 'CAIRO/SVG',
                'mimetype' => 'image/svg+xml',
                'imagemode' => 'RGB',
                'extension' => 'svg',
                'formatoptions' => ['COMPRESSED_OUTPUT=FALSE', 'FULL_RESOLUTION=TRUE']
            ],
            'tif' => [
                'driver' => 'GDAL/GTiff',
                'mimetype' => 'image/tiff',
                'imagemode' => 'RGB',
                'extension' => 'tif',
                'formatoptions' => ['COMPRESS=JPEG', 'JPEG_QUALITY=100', 'PHOTOMETRIC=YCBCR']
            ]
        ];

        $fmt = $formats[$output] ?? $formats['png'];

        $block = "  OUTPUTFORMAT\n";
        $block .= "    NAME \"{$output}\"\n";
        $block .= "    DRIVER \"{$fmt['driver']}\"\n";
        $block .= "    MIMETYPE \"{$fmt['mimetype']}\"\n";
        $block .= "    IMAGEMODE {$fmt['imagemode']}\n";
        $block .= "    EXTENSION \"{$fmt['extension']}\"\n";
        foreach ($fmt['formatoptions'] as $opt) {
            $block .= "    FORMATOPTION \"{$opt}\"\n";
        }
        $block .= "  END\n\n";

        return $block;
    }

    private function generateSymbols(): string
    {
        $block = "";

        foreach (Symbols::definitions() as $name => $def) {
            $block .= "  SYMBOL\n";
            $block .= "    NAME \"{$name}\"\n";
            $block .= "    TYPE {$def['type']}\n";

            if (isset($def['points'])) {
                $block .= "    POINTS\n";
                $block .= "      {$def['points']}\n";
                $block .= "    END\n";
            }

            if (isset($def['filled'])) {
                $block .= "    FILLED " . ($def['filled'] ? 'TRUE' : 'FALSE') . "\n";
            }

            $block .= "  END\n\n";
        }

        return $block;
    }

    private function generateBaseLayers(array $layers, string $projection, float $lineThickness = 1.0): string
    {
        $block = "";

        foreach ($layers as $layerName) {
            $layer = Layers::get($layerName);
            if ($layer === null) {
                continue;
            }

            $block .= $this->generateLayerBlock($layerName, $layer, $projection, $lineThickness);
        }

        return $block;
    }

    private function generateLayerBlock(string $name, array $layer, string $projection, float $lineThickness = 1.0): string
    {
        $block = "  LAYER\n";
        $block .= "    NAME \"{$name}\"\n";
        $block .= "    STATUS ON\n";
        $block .= "    TYPE {$layer['type']}\n";

        if (isset($layer['data'])) {
            $block .= "    DATA \"{$layer['data']}\"\n";
        }

        // Layer projection (source data is WGS84)
        $block .= "    PROJECTION\n";
        $block .= "      \"proj=longlat\"\n";
        $block .= "      \"ellps=WGS84\"\n";
        $block .= "      \"datum=WGS84\"\n";
        $block .= "      \"no_defs\"\n";
        $block .= "    END\n";

        // Raster layers need no CLASS block
        if ($layer['type'] !== 'RASTER') {
            $block .= "    CLASS\n";
            if (isset($layer['name'])) {
                $block .= "      NAME \"{$layer['name']}\"\n";
            }
            $block .= "      STYLE\n";

            if (isset($layer['color'])) {
                $block .= "        COLOR {$layer['color']}\n";
            }
            if (isset($layer['outlinecolor'])) {
                $block .= "        OUTLINECOLOR {$layer['outlinecolor']}\n";
            }
            if (isset($layer['width'])) {
                $width = $layer['width'] * $lineThickness;
                $block .= "        WIDTH {$width}\n";
            }

            $block .= "      END\n";
            $block .= "    END\n";
        }

        $block .= "  END\n\n";

        return $block;
    }

    private function generatePointLayer(array $pointSet, int $index, string $projection): string
    {
        $legend = $pointSet['legend'] ?? "Points {$index}";
        $shape = $pointSet['shape'] ?? 'circle';
        $size = (int)($pointSet['size'] ?? 10);
        $color = $pointSet['color'] ?? [0, 0, 0];
        $shadow = $pointSet['shadow'] ?? false;

        if (is_array($color)) {
            $colorStr = implode(' ', $color);
        } else {
            $colorStr = str_replace(',', ' ', $color);
        }

        $block = "  LAYER\n";
        $block .= "    NAME \"points_{$index}\"\n";
        $block .= "    STATUS ON\n";
        $block .= "    TYPE POINT\n";
        $block .= "    PROJECTION\n";
        $block .= $this->getWgs84Projection(6);
        $block .= "    END\n";

        // Class
        $block .= "    CLASS\n";
        $block .= "      NAME \"" . addslashes($legend) . "\"\n";

        // Shadow style (if enabled)
        if ($shadow) {
            $block .= "      STYLE\n";
            $block .= "        SYMBOL \"{$shape}\"\n";
            $block .= "        SIZE {$size}\n";
            $block .= "        COLOR 180 180 180\n";
            $block .= "        OFFSET 2 2\n";
            $block .= "      END\n";
        }

        // Main style
        $block .= "      STYLE\n";
        $block .= "        SYMBOL \"{$shape}\"\n";
        $block .= "        SIZE {$size}\n";
        $block .= "        COLOR {$colorStr}\n";

        // Add outline for filled shapes
        if (!in_array($shape, ['plus', 'cross', 'asterisk']) &&
            strpos($shape, 'open') !== 0) {
            $block .= "        OUTLINECOLOR 0 0 0\n";
        }

        $block .= "      END\n";
        $block .= "    END\n";

        // Add features (inline)
        $block .= "    FEATURE\n";
        foreach ($pointSet['coordinates'] ?? [] as $coord) {
            // Coordinates come as [lat, lon] - MapServer wants lon, lat
            $lat = is_array($coord) ? ($coord['lat'] ?? $coord[0] ?? 0) : 0;
            $lon = is_array($coord) ? ($coord['lon'] ?? $coord[1] ?? 0) : 0;
            $block .= "      POINTS {$lon} {$lat} END\n";
        }
        $block .= "    END\n";

        $block .= "  END\n\n";

        return $block;
    }

    private function generateRegionLayer(array $region, int $index, string $projection): string
    {
        $legend = $region['legend'] ?? "Region {$index}";
        $color = $region['color'] ?? [120, 120, 120];
        $border = $region['border'] ?? true;
        $places = $region['places'] ?? [];

        if (is_array($color)) {
            $colorStr = implode(' ', $color);
        } else {
            $colorStr = str_replace(',', ' ', $color);
        }

        // Build filter expression
        $filters = [];
        foreach ($places as $place) {
            if (preg_match('/^([A-Z]{3})\[([A-Z|]+)\]$/i', $place, $m)) {
                // Country code with state codes: CAN[ON|QC]
                $country = strtoupper($m[1]);
                $states = explode('|', strtoupper($m[2]));
                foreach ($states as $state) {
                    $filters[] = "(\"[adm0_a3]\" = \"{$country}\" AND \"[code_hasc]\" ~* \".{$state}\$\")";
                }
            } else {
                // Plain place name
                $place = addslashes(trim($place));
                $filters[] = "(\"[name]\" ~* \"{$place}\$\" OR \"[admin]\" ~* \"{$place}\$\")";
            }
        }

        $filter = '(' . implode(' OR ', $filters) . ')';

        $block = "  LAYER\n";
        $block .= "    NAME \"region_{$index}\"\n";
        $block .= "    STATUS ON\n";
        $block .= "    TYPE POLYGON\n";
        $block .= "    DATA \"10m_cultural/10m_cultural/ne_10m_admin_1_states_provinces\"\n";
        $block .= "    PROJECTION\n";
        $block .= $this->getWgs84Projection(6);
        $block .= "    END\n";
        $block .= "    FILTER {$filter}\n";
        $block .= "    CLASS\n";
        $block .= "      NAME \"" . addslashes($legend) . "\"\n";
        $block .= "      STYLE\n";
        $block .= "        COLOR {$colorStr}\n";
        if ($border) {
            $block .= "        OUTLINECOLOR 30 30 30\n";
        }
        $block .= "      END\n";
        $block .= "    END\n";
        $block .= "  END\n\n";

        return $block;
    }

    private function generateWktLayer(array $wkt, int $index, string $projection): string
    {
        $legend = $wkt['legend'] ?? "Drawing {$index}";
        $color = $wkt['color'] ?? [120, 120, 120];
        $border = $wkt['border'] ?? false;
        $data = $wkt['data'] ?? '';

        if (is_array($color)) {
            $colorStr = implode(' ', $color);
        } else {
            $colorStr = str_replace(',', ' ', $color);
        }

        // Determine geometry type
        $type = 'POLYGON';
        if (stripos($data, 'POINT') !== false) {
            $type = 'POINT';
        } elseif (stripos($data, 'LINE') !== false) {
            $type = 'LINE';
        }

        $block = "  LAYER\n";
        $block .= "    NAME \"wkt_{$index}\"\n";
        $block .= "    STATUS ON\n";
        $block .= "    TYPE {$type}\n";
        $block .= "    PROJECTION\n";
        $block .= $this->getWgs84Projection(6);
        $block .= "    END\n";
        $block .= "    CLASS\n";
        $block .= "      NAME \"" . addslashes($legend) . "\"\n";
        $block .= "      STYLE\n";
        $block .= "        COLOR {$colorStr}\n";
        if ($border && $type === 'POLYGON') {
            $block .= "        OUTLINECOLOR 0 0 0\n";
        }
        if ($type === 'POINT') {
            $block .= "        SYMBOL \"circle\"\n";
            $block .= "        SIZE 8\n";
        }
        $block .= "        OPACITY 75\n";
        $block .= "      END\n";
        $block .= "    END\n";
        $block .= "    FEATURE\n";
        $block .= "      WKT \"{$data}\"\n";
        $block .= "    END\n";
        $block .= "  END\n\n";

        return $block;
    }

    private function generateGraticules(array $graticules, string $projection): string
    {
        $spacing = $graticules['spacing'] ?? 10;
        $showLabels = $graticules['show_labels'] ?? true;

        $block = "  LAYER\n";
        $block .= "    NAME \"grid\"\n";
        $block .= "    STATUS ON\n";
        $block .= "    TYPE LINE\n";
        $block .= "    PROJECTION\n";
        $block .= $this->getWgs84Projection(6);
        $block .= "    END\n";
        $block .= "    CLASS\n";
        $block .= "      STYLE\n";
        $block .= "        COLOR 200 200 200\n";
        $block .= "      END\n";

        if ($showLabels) {
            $block .= "      LABEL\n";
            $block .= "        FONT \"dejavu-sans\"\n";
            $block .= "        TYPE TRUETYPE\n";
            $block .= "        SIZE 10\n";
            $block .= "        COLOR 30 30 30\n";
            $block .= "        POSITION UC\n";
            $block .= "      END\n";
        }

        $block .= "    END\n";
        $block .= "    GRID\n";
        $block .= "      LABELFORMAT \"DD\"\n";
        $block .= "      MAXINTERVAL {$spacing}\n";
        $block .= "      MAXSUBDIVIDE 2\n";
        $block .= "    END\n";
        $block .= "  END\n\n";

        return $block;
    }

    private function generateLegend(): string
    {
        $block = "  LEGEND\n";
        $block .= "    STATUS EMBED\n";
        $block .= "    POSITION UR\n";
        $block .= "    POSTLABELCACHE TRUE\n";
        $block .= "    LABEL\n";
        $block .= "      FONT \"dejavu-sans\"\n";
        $block .= "      TYPE TRUETYPE\n";
        $block .= "      SIZE 10\n";
        $block .= "      COLOR 0 0 0\n";
        $block .= "    END\n";
        $block .= "  END\n\n";

        return $block;
    }

    private function generateScalebar(int $width): string
    {
        $size = $width <= 500 ? 'small' : 'normal';

        $block = "  SCALEBAR\n";
        $block .= "    STATUS EMBED\n";
        $block .= "    POSITION LR\n";
        $block .= "    STYLE 0\n";
        $block .= "    INTERVALS " . ($size === 'small' ? '2' : '3') . "\n";
        $block .= "    HEIGHT 8\n";
        $block .= "    WIDTH " . ($size === 'small' ? '100' : '200') . "\n";
        $block .= "    UNITS KILOMETERS\n";
        $block .= "    COLOR 30 30 30\n";
        $block .= "    BACKGROUNDCOLOR 255 255 255\n";
        $block .= "    OUTLINECOLOR 0 0 0\n";
        $block .= "    LABEL\n";
        $block .= "      FONT \"dejavu-sans\"\n";
        $block .= "      TYPE TRUETYPE\n";
        $block .= "      SIZE " . ($size === 'small' ? '8' : '10') . "\n";
        $block .= "      COLOR 0 0 0\n";
        $block .= "    END\n";
        $block .= "  END\n\n";

        return $block;
    }
}
