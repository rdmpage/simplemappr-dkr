<?php
/**
 * Layers - Available map layers (Natural Earth data)
 */

declare(strict_types=1);

class Layers
{
    private static array $layers = [
        'base' => [
            'name' => 'Land',
            'data' => '10m_physical/ne_10m_land',
            'type' => 'LINE',
            'color' => '10 10 10',
            'width' => 0.5
        ],
        'countries' => [
            'name' => 'Countries',
            'data' => '10m_cultural/10m_cultural/ne_10m_admin_0_map_units',
            'type' => 'LINE',
            'color' => '10 10 10',
            'width' => 0.75
        ],
        'stateprovinces' => [
            'name' => 'State/Provinces',
            'data' => '10m_cultural/10m_cultural/ne_10m_admin_1_states_provinces_lines',
            'type' => 'LINE',
            'color' => '100 100 100',
            'width' => 0.5
        ],
        'lakes' => [
            'name' => 'Lakes',
            'data' => '10m_physical/ne_10m_lakes',
            'type' => 'POLYGON',
            'color' => '200 200 255',
            'outlinecolor' => '80 80 80'
        ],
        'lakesOutline' => [
            'name' => 'Lakes (outline)',
            'data' => '10m_physical/ne_10m_lakes',
            'type' => 'LINE',
            'color' => '80 80 80',
            'width' => 0.5
        ],
        'rivers' => [
            'name' => 'Rivers',
            'data' => '10m_physical/ne_10m_rivers_lake_centerlines',
            'type' => 'LINE',
            'color' => '120 120 200',
            'width' => 0.5
        ],
        'oceans' => [
            'name' => 'Oceans',
            'data' => '10m_physical/ne_10m_ocean',
            'type' => 'POLYGON',
            'color' => '220 220 220'
        ],
        'roads' => [
            'name' => 'Roads',
            'data' => '10m_cultural/10m_cultural/ne_10m_roads',
            'type' => 'LINE',
            'color' => '60 60 60',
            'width' => 0.5
        ],
        'railroads' => [
            'name' => 'Railroads',
            'data' => '10m_cultural/10m_cultural/ne_10m_railroads',
            'type' => 'LINE',
            'color' => '100 100 100',
            'width' => 0.5
        ]
    ];

    // Raster layers (require separate handling - not included by default)
    private static array $rasterLayers = [
        'relief' => [
            'name' => 'Relief',
            'data' => 'HYP_HR_SR_OB_DR/HYP_HR_SR_OB_DR.tif',
            'type' => 'RASTER'
        ],
        'reliefgrey' => [
            'name' => 'Relief (greyscale)',
            'data' => 'GRAY_HR_SR_OB_DR/GRAY_HR_SR_OB_DR.tif',
            'type' => 'RASTER'
        ],
        'blueMarble' => [
            'name' => 'Blue Marble',
            'data' => 'blue_marble/land_shallow_topo_21600.tif',
            'type' => 'RASTER'
        ]
    ];

    public static function all(): array
    {
        return self::$layers;
    }

    public static function get(string $name): ?array
    {
        return self::$layers[$name] ?? self::$rasterLayers[$name] ?? null;
    }

    public static function exists(string $name): bool
    {
        return isset(self::$layers[$name]) || isset(self::$rasterLayers[$name]);
    }

    public static function raster(): array
    {
        return self::$rasterLayers;
    }
}
