<?php
/**
 * Projections - Available map projections
 */

declare(strict_types=1);

class Projections
{
    private static array $projections = [
        'epsg:4326' => [
            'name' => 'Geographic (WGS84)',
            'proj' => 'proj=longlat,ellps=WGS84,datum=WGS84,no_defs'
        ],
        'esri:102009' => [
            'name' => 'North America Lambert',
            'proj' => 'proj=lcc,lat_1=20,lat_2=60,lat_0=40,lon_0=-96,x_0=0,y_0=0,ellps=GRS80,datum=NAD83,units=m,over,no_defs'
        ],
        'esri:102015' => [
            'name' => 'South America Lambert',
            'proj' => 'proj=lcc,lat_1=-5,lat_2=-42,lat_0=-32,lon_0=-60,x_0=0,y_0=0,ellps=aust_SA,units=m,over,no_defs'
        ],
        'esri:102014' => [
            'name' => 'Europe Lambert',
            'proj' => 'proj=lcc,lat_1=43,lat_2=62,lat_0=30,lon_0=10,x_0=0,y_0=0,ellps=intl,units=m,over,no_defs'
        ],
        'esri:102012' => [
            'name' => 'Asia Lambert',
            'proj' => 'proj=lcc,lat_1=30,lat_2=62,lat_0=0,lon_0=105,x_0=0,y_0=0,ellps=WGS84,datum=WGS84,units=m,over,no_defs'
        ],
        'esri:102024' => [
            'name' => 'Africa Lambert',
            'proj' => 'proj=lcc,lat_1=20,lat_2=-23,lat_0=0,lon_0=25,x_0=0,y_0=0,ellps=WGS84,datum=WGS84,units=m,over,no_defs'
        ],
        'epsg:3112' => [
            'name' => 'Australia Lambert',
            'proj' => 'proj=lcc,lat_1=-18,lat_2=-36,lat_0=0,lon_0=134,x_0=0,y_0=0,ellps=GRS80,towgs84=0,0,0,0,0,0,0,units=m,over,no_defs'
        ],
        'epsg:102017' => [
            'name' => 'North Pole Azimuthal',
            'proj' => 'proj=laea,lat_0=90,lon_0=0,x_0=0,y_0=0,ellps=WGS84,datum=WGS84,units=m,over,no_defs'
        ],
        'epsg:102019' => [
            'name' => 'South Pole Azimuthal',
            'proj' => 'proj=laea,lat_0=-90,lon_0=0,x_0=0,y_0=0,ellps=WGS84,datum=WGS84,units=m,over,no_defs'
        ],
        'epsg:54030' => [
            'name' => 'World Robinson',
            'proj' => 'proj=robin,lon_0=0,x_0=0,y_0=0,ellps=WGS84,datum=WGS84,units=m,over,no_defs'
        ],
        'epsg:3395' => [
            'name' => 'World Mercator',
            'proj' => 'proj=merc,lon_0=0,k=1,x_0=0,y_0=0,ellps=WGS84,datum=WGS84,units=m,over,no_defs'
        ]
    ];

    public static function all(): array
    {
        return self::$projections;
    }

    public static function get(string $code): ?array
    {
        return self::$projections[strtolower($code)] ?? null;
    }

    public static function exists(string $code): bool
    {
        return isset(self::$projections[strtolower($code)]);
    }
}
