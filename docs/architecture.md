# SimpleMappr Docker Architecture

This document provides comprehensive documentation for porting SimpleMappr to a modern Docker-based stack. It serves as the sole reference for building the new project from scratch.

---

## 1. Original Codebase Summary

### Source Code Structure

The original SimpleMappr is a PHP 5.6+ application using the Phroute routing framework and Twig templating. The codebase follows a loosely MVC pattern with the following structure:

```
simplemappr/
├── config/           # Configuration files (conf.php, shapefiles.yml, phinx.yml)
├── db/               # Database schema and migrations
├── i18n/             # Internationalization (gettext .po files)
├── mapserver/        # Fonts, mapfile templates, shapefiles
├── public/           # Web-accessible files (CSS, JS, images)
├── src/              # PHP application code
├── views/            # Twig templates
├── index.php         # Entry point
└── composer.json     # Dependencies
```

### Class Descriptions

#### Core Infrastructure Classes

| Class | File | Purpose |
|-------|------|---------|
| `Router` | `src/Router.php` | URL routing using Phroute. Defines all endpoints, applies filters for logging and role checks, instantiates Twig templates. |
| `Database` | `src/Database.php` | PDO database singleton. Reads credentials from `phinx.yml`. Provides `queryInsert()`, `queryUpdate()`, `queryDelete()` methods. |
| `Session` | `src/Session.php` | Handles Janrain/RPX OpenID authentication. Manages cookies, locales (en_US, fr_FR), session creation/destruction. |
| `Request` | `src/Request.php` | Builds request object from `$_REQUEST` parameters. Extracts coords, regions, WKT, projection, bbox, layers, output format. |
| `Utility` | `src/Utility.php` | Static helper methods: coordinate parsing (`makeCoordinates`, `dmsToDeg`), filename cleaning, hex/RGB conversion, parameter loading. |
| `Header` | `src/Header.php` | Sets HTTP headers for various content types (PNG, JPG, TIF, SVG, JSON, KML, PPTX, DOCX). |
| `Logger` | `src/Logger.php` | File-based logging with tail and regex parsing for API/WMS/WFS requests. |
| `Assets` | `src/Assets.php` | CSS/JS minification and combination. CloudFlare cache management. |

#### Constants (Traits)

| Trait | File | Purpose |
|-------|------|---------|
| `AcceptedProjections` | `src/Constants/AcceptedProjections.php` | Defines 11 projections with PROJ strings (epsg:4326, esri:102009, etc.) |
| `AcceptedMarkers` | `src/Constants/AcceptedMarkers.php` | Defines marker shapes (plus, cross, circle, star, square, triangle, hexagon) with vertices, sizes 6-16. |
| `AcceptedOutputs` | `src/Constants/AcceptedOutputs.php` | Defines output formats: PNG, PNG+Alpha, JPG, TIF, SVG with MapServer driver configs. |

#### Mappr Engine Classes

| Class | File | Purpose |
|-------|------|---------|
| `Mappr` | `src/Mappr/Mappr.php` | Abstract base class. Loads shapefiles from YAML config. Uses php-mapscript to build MapScript objects programmatically (layers, classes, styles, symbols). Main `execute()` method orchestrates rendering. |
| `Application` | `src/Mappr/Application/Application.php` | Extends Mappr. Interactive web application rendering. Returns JSON with image URL, bbox, legend URL, scalebar URL, bad points. |
| `Map` | `src/Mappr/Application/Map.php` | Renders saved maps by ID in various formats (PNG, JPG, SVG, KML). |
| `Query` | `src/Mappr/Application/Query.php` | Handles point-and-click queries on map layers. |
| `Api` | `src/Mappr/WebServices/Api.php` | REST API endpoint. Parses URL/file/points parameters. Returns images via GET, JSON with image URL via POST. |
| `Wms` | `src/Mappr/WebServices/Wms.php` | OGC Web Map Service implementation. |
| `Wfs` | `src/Mappr/WebServices/Wfs.php` | OGC Web Feature Service implementation. |
| `Docx` | `src/Mappr/Formats/Docx.php` | Generates Word documents with embedded map images using PHPWord. |
| `Pptx` | `src/Mappr/Formats/Pptx.php` | Generates PowerPoint presentations using PHPPresentation. |

#### Controller Classes

| Class | File | Purpose |
|-------|------|---------|
| `User` | `src/Controller/User.php` | User CRUD. Roles: 1=user, 2=administrator. Static methods: `checkPermission()`, `isAdministrator()`. |
| `Map` | `src/Controller/Map.php` | Map CRUD. Maps stored as JSON in `map` column. Upsert logic (update if title exists). |
| `Share` | `src/Controller/Share.php` | Map sharing with unique tokens. |
| `Citation` | `src/Controller/Citation.php` | Publication citation management. |
| `CitationFeed` | `src/Controller/CitationFeed.php` | RSS feed generation for citations. |
| `Place` | `src/Controller/Place.php` | Geographic place autocomplete from stateprovinces table. |
| `Kml` | `src/Controller/Kml.php` | KML file generation. |
| `OpenAPI` | `src/Controller/OpenAPI.php` | OpenAPI/Swagger specification. |
| `RestMethods` | `src/Controller/RestMethods.php` | Interface defining `index()`, `show()`, `create()`, `update()`, `destroy()`. |

### MapServer/MapScript Rendering Pattern

The original uses the **php-mapscript extension** to build maps programmatically without static mapfiles. The pattern:

1. **Initialize**: `ms_newMapObjFromString("MAP END")` creates empty map object
2. **Configure**: Set projection, output format, extent, size
3. **Load Shapes**: Parse `shapefiles.yml` for layer paths and styles
4. **Add Layers**: Create `layerObj` for each shapefile, set data path, type, projection
5. **Add Symbols**: Create `symbolObj` for each marker shape with vertices
6. **Add Points**: Create `shapeObj`/`lineObj`/`pointObj` for user coordinates
7. **Add Regions**: Build MapServer filter expressions for region highlighting
8. **Add WKT**: Parse WKT strings, create shapes with `ms_shapeObjFromWkt()`
9. **Draw**: `$map_obj->drawQuery()` renders to image
10. **Output**: `$image->saveWebImage()` or `$image->saveImage("")` for direct output

Key code from `Mappr.php`:

```php
// Constructor
$this->map_obj = ms_newMapObjFromString("MAP END");

// Adding a point layer
$layer = new \layerObj($this->map_obj);
$layer->set("name", "layer_".$j);
$layer->set("status", MS_ON);
$layer->set("type", MS_LAYER_POINT);
$layer->setProjection(self::getProjection($this->default_projection));

$class = new \classObj($layer);
$style = new \styleObj($class);
$style->set("symbolname", $shape);
$style->set("size", $size);
$style->color->setRGB($color[0], $color[1], $color[2]);

$new_shape = new \shapeObj(MS_SHAPE_POINT);
$new_line = new \lineObj();
$new_point = new \pointObj();
$new_point->setXY($coord->x, $coord->y);
$new_line->add($new_point);
$new_shape->add($new_line);
$layer->addFeature($new_shape);
```

### Database Schema

The MySQL database contains 6 tables:

#### `users` - User accounts
```sql
CREATE TABLE users (
  uid bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  hash varchar(60) NOT NULL UNIQUE,      -- password_hash() of identifier
  identifier varchar(255) NOT NULL,       -- OpenID identifier URL
  username varchar(50),                    -- preferredUsername from OpenID
  displayname varchar(125),                -- displayName from OpenID
  email varchar(50),
  role int(11) DEFAULT 1,                  -- 1=user, 2=administrator
  created int(11),                         -- Unix timestamp
  access int(11)                           -- Last access Unix timestamp
);
```

#### `maps` - Saved map configurations
```sql
CREATE TABLE maps (
  mid bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  uid int(11) NOT NULL,                    -- FK to users.uid
  title varchar(255) NOT NULL,
  map longtext,                            -- JSON-encoded map parameters
  created int(11) NOT NULL,                -- Unix timestamp
  updated int(11)                          -- Unix timestamp
);
```

#### `shares` - Map sharing tokens
```sql
CREATE TABLE shares (
  sid int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  mid int(11) NOT NULL,                    -- FK to maps.mid
  created int(11) NOT NULL                 -- Unix timestamp
);
```

#### `citations` - Publication references
```sql
CREATE TABLE citations (
  id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  year int(11) NOT NULL,
  reference text NOT NULL,
  doi varchar(255) NOT NULL,
  link varchar(255) NOT NULL,
  first_author_surname varchar(255) NOT NULL,
  created int(11) NOT NULL
);
```

#### `stateprovinces` - Geographic lookup for region autocomplete
```sql
CREATE TABLE stateprovinces (
  id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  country_iso char(3),
  country varchar(128),
  stateprovince varchar(128),
  stateprovince_code char(2) NOT NULL
);
```

#### `migrations` - Phinx migration tracking
```sql
CREATE TABLE migrations (
  version bigint(14) NOT NULL,
  migration_name varchar(100),
  start_time timestamp,
  end_time timestamp,
  breakpoint tinyint(1) NOT NULL DEFAULT 0
);
```

### Authentication (Janrain/RPX)

The original uses **Janrain RPXNow** (now defunct) for OpenID authentication:

1. User clicks login, redirected to Janrain widget
2. Janrain returns token to callback URL
3. `Session::_makeCall()` POSTs token to `https://rpxnow.com/api/v2/auth_info` with API key
4. Response contains profile: `identifier`, `preferredUsername`, `displayName`, `email`
5. User created/updated in database, session written, cookie set

```php
// From Session.php
$post_data = [
    'token' => $this->_token,
    'apiKey' => RPX_KEY,
    'format' => 'json'
];
curl_setopt($curl, CURLOPT_URL, 'https://rpxnow.com/api/v2/auth_info');
```

### REST API

The API is accessible at `/api` and supports both GET and POST:

**GET /api** - Returns image directly
```
?ping=true              → {"status":"ok"}
?points[]=45,-75        → PNG image
?output=svg             → SVG image
```

**POST /api** - Returns JSON with image URL
```json
{
  "imageURL": "http://img.simplemappr.net/tmp/abc123.png",
  "expiry": "2024-01-15T18:00:00+00:00",
  "bad_points": [],
  "bad_drawings": []
}
```

**Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| `points[]` | array | Coordinate sets, one per legend item |
| `legend[]` | array | Legend labels for each point set |
| `shape[]` | array | Marker shapes per set |
| `size[]` | array | Marker sizes per set (6-16) |
| `color[]` | array | RGB colors per set (e.g., "255,0,0") |
| `shadow[]` | array | Enable shadows per set |
| `wkt[]` | array | WKT geometry strings with `data`, `title`, `color`, `border` |
| `shade[places]` | string | Region names to highlight |
| `shade[title]` | string | Region legend label |
| `shade[color]` | string | Region fill color |
| `projection` | string | EPSG/ESRI code |
| `bbox` | string | Bounding box "minx,miny,maxx,maxy" |
| `layers` | string | Comma-separated layer names |
| `output` | string | png, jpg, tif, svg |
| `width` | int | Image width (default 900) |
| `height` | int | Image height (default width/2) |
| `graticules` | bool | Show grid lines |
| `spacing` | int | Grid spacing in degrees |
| `legend` | bool | Embed legend |
| `scalebar` | bool | Embed scalebar |
| `border` | bool | Draw border |
| `watermark` | bool | Add URL watermark |

### Composer Dependencies

| Package | Version | Purpose | Still Relevant? |
|---------|---------|---------|-----------------|
| `league/csv` | ~8.2.0 | CSV file parsing | Yes |
| `natxet/CssMin` | ~3.0.4 | CSS minification | Maybe (use build tools) |
| `neitanod/forceutf8` | ~2.0 | UTF-8 encoding fixes | Yes |
| `phpoffice/phppresentation` | ~0.6.0 | PowerPoint generation | Yes |
| `phpoffice/phpword` | ~0.12.1 | Word doc generation | Yes |
| `phayes/geophp` | ~1.2 | GeoJSON/KML/WKT parsing | Yes |
| `phroute/phroute` | 2.* | URL routing | Maybe (use Slim/Laravel) |
| `robmorgan/phinx` | ~0.8.0 | Database migrations | Maybe (use native migrations) |
| `symfony/yaml` | * | YAML parsing | Yes |
| `twig/twig` | ~1.0 | Templating | Yes (upgrade to 3.x) |
| `twig/extensions` | ~1.1.0 | i18n for Twig | Yes |
| `suin/php-rss-writer` | >=1.0 | RSS feed generation | Maybe (if citations kept) |

---

## 2. New Stack Decisions

### PHP 7.4

**Rationale:** PHP 7.4 is broadly compatible with the original PHP 5.6 code while providing:
- Significant performance improvements (2-3x faster than 5.6)
- Typed properties, arrow functions, null coalescing assignment
- Still supported in many Docker images and hosting environments
- Gradual upgrade path to PHP 8.x when ready

Most code changes required:
- Replace deprecated `get_magic_quotes_gpc()` calls
- Update `password_hash()` usage (already compatible)
- Replace `each()` with `foreach` where used

### SQLite Instead of MySQL

**Rationale:**

| Factor | MySQL | SQLite |
|--------|-------|--------|
| Installation | Requires separate service | Built into PHP |
| Backup | `mysqldump` or replication | Copy single file |
| Docker complexity | Separate container, networking | File in volume |
| Data portability | Export/import SQL | Copy file |
| Connection overhead | TCP/socket connection | Direct file I/O |
| Concurrent writes | Excellent | Limited (sufficient for this app) |

The application has low write volume (user saves maps occasionally) and moderate read volume (map rendering). SQLite handles this easily.

**PDO Compatibility:** Both MySQL and SQLite use PDO, so queries remain largely unchanged:

```php
// MySQL
$conn = 'mysql:host=localhost;dbname=simplemappr;charset=utf8';

// SQLite
$conn = 'sqlite:/var/lib/simplemappr/simplemappr.db';
```

### MapServer via `shell_exec` Instead of php-mapscript

**Rationale:**

| Factor | php-mapscript | shell_exec + shp2img |
|--------|---------------|----------------------|
| Installation | Compile from source, version-specific | `apt install mapserver-bin` |
| PHP compatibility | Breaks with PHP upgrades | PHP version agnostic |
| Debugging | Segfaults crash PHP | Separate process, easier debugging |
| Resource isolation | Shares PHP memory | Separate process memory |
| API stability | Extension API changes | CLI stable for decades |

The `shp2img` command-line tool reads a mapfile and produces an image:

```bash
shp2img -m /tmp/map.map -o /tmp/output.png
```

Instead of building MapScript objects in PHP, we:
1. Generate a `.map` file as text
2. Call `shell_exec('shp2img -m ...')`
3. Read the output image

### Docker Compose for Development and Production

**Rationale:**

- **Consistency:** Same container runs locally and in production
- **Isolation:** MapServer dependencies don't pollute host system
- **Reproducibility:** `docker-compose up` gives working environment
- **Easy deployment:** Push image to registry, pull on server
- **Volume mounting:** SQLite database persists across container restarts

### League OAuth2 Replacing Janrain/RPX

**Rationale:**

- Janrain RPXNow was discontinued in 2018
- `league/oauth2-client` is the de facto PHP OAuth2 library
- Provider packages available for major services

**Provider Selection:**

| Provider | Rationale |
|----------|-----------|
| ORCID (primary) | Academic user base, researchers have ORCIDs, provides identifier stability |
| Google (fallback) | Ubiquitous, easy signup, good for non-researchers |

```php
use League\OAuth2\Client\Provider\GenericProvider;

$orcidProvider = new GenericProvider([
    'clientId'                => ORCID_CLIENT_ID,
    'clientSecret'            => ORCID_CLIENT_SECRET,
    'redirectUri'             => MAPPR_URL . '/callback/orcid',
    'urlAuthorize'            => 'https://orcid.org/oauth/authorize',
    'urlAccessToken'          => 'https://orcid.org/oauth/token',
    'urlResourceOwnerDetails' => 'https://pub.orcid.org/v3.0/{orcid}/person'
]);
```

### No Token Migration

**Rationale:**

- Original uses Janrain identifiers (URLs like `https://www.google.com/profiles/123456`)
- New system uses ORCID iDs (`0000-0002-1825-0097`) or Google sub claims
- No way to reliably match old identifiers to new OAuth identities
- Users can export their maps as JSON from the old site and import into the new one
- Clean break avoids complex migration logic and potential data integrity issues

### MapServer Rendering as Microservice

**Rationale:**

| Benefit | Description |
|---------|-------------|
| Language independence | Web layer can be rewritten in Python/Ruby/Go without touching rendering |
| Scaling | Render service can run on separate machines |
| Testing | Mock the render API for web layer tests |
| Caching | HTTP caching layer (Varnish, CloudFlare) can cache rendered images |
| Security | Render service has no database access, limited attack surface |

---

## 3. Rendering Microservice Design

### Overview

The rendering microservice is a lightweight HTTP API that accepts a JSON map description and returns a rendered image. It has no database access and no knowledge of users or saved maps.

### Endpoint

```
POST /render
Content-Type: application/json
Accept: image/png | image/svg+xml | image/jpeg | image/tiff

Response: Image bytes with appropriate Content-Type
```

### JSON Input Format

```json
{
  "output": "png",
  "width": 900,
  "height": 450,
  "projection": "epsg:4326",
  "bbox": [-180, -90, 180, 90],
  "rotation": 0,
  "origin": null,
  "layers": ["base", "countries", "stateprovinces", "lakes"],
  "graticules": {
    "enabled": true,
    "spacing": 10,
    "show_labels": true
  },
  "points": [
    {
      "legend": "Species A",
      "shape": "circle",
      "size": 10,
      "color": [255, 0, 0],
      "shadow": false,
      "coordinates": [
        [45.5, -75.5],
        [46.2, -74.8]
      ]
    },
    {
      "legend": "Species B",
      "shape": "triangle",
      "size": 8,
      "color": [0, 0, 255],
      "shadow": true,
      "coordinates": [
        [44.0, -76.0]
      ]
    }
  ],
  "regions": [
    {
      "legend": "Distribution",
      "color": [150, 200, 150],
      "border": true,
      "hatched": false,
      "places": ["Ontario", "Quebec", "CAN[ON|QC]"]
    }
  ],
  "wkt": [
    {
      "legend": "Study Area",
      "color": [200, 200, 100],
      "border": true,
      "data": "POLYGON((-80 45, -75 45, -75 40, -80 40, -80 45))"
    }
  ],
  "options": {
    "legend": true,
    "scalebar": true,
    "border": true,
    "border_thickness": 1.25,
    "watermark": false
  }
}
```

### Field Definitions

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `output` | string | No | `png` (default), `jpg`, `tif`, `svg` |
| `width` | int | No | Image width in pixels (default 900) |
| `height` | int | No | Image height in pixels (default width/2) |
| `projection` | string | No | EPSG/ESRI code (default `epsg:4326`) |
| `bbox` | array | No | `[minx, miny, maxx, maxy]` (default world extent) |
| `rotation` | int | No | Map rotation in degrees (default 0) |
| `origin` | float | No | Custom origin longitude for Lambert projections |
| `layers` | array | No | Layer names to render (default `["base", "countries"]`) |
| `graticules.enabled` | bool | No | Show grid lines |
| `graticules.spacing` | int | No | Grid spacing in degrees |
| `graticules.show_labels` | bool | No | Show coordinate labels on grid |
| `points` | array | No | Point marker sets |
| `points[].legend` | string | No | Legend label |
| `points[].shape` | string | No | Marker shape |
| `points[].size` | int | No | Marker size (6-16) |
| `points[].color` | array | Yes | RGB color `[r, g, b]` |
| `points[].shadow` | bool | No | Drop shadow |
| `points[].coordinates` | array | Yes | `[[lat, lon], ...]` |
| `regions` | array | No | Highlighted regions |
| `regions[].legend` | string | No | Legend label |
| `regions[].color` | array | Yes | RGB fill color |
| `regions[].border` | bool | No | Draw border |
| `regions[].hatched` | bool | No | Hatch fill pattern |
| `regions[].places` | array | Yes | Place names or codes |
| `wkt` | array | No | WKT geometry overlays |
| `wkt[].legend` | string | No | Legend label |
| `wkt[].color` | array | Yes | RGB color |
| `wkt[].border` | bool | No | Draw border for polygons |
| `wkt[].data` | string | Yes | WKT string |
| `options.legend` | bool | No | Embed legend in image |
| `options.scalebar` | bool | No | Embed scalebar in image |
| `options.border` | bool | No | Draw border around image |
| `options.border_thickness` | float | No | Border line thickness |
| `options.watermark` | bool | No | Add URL watermark |

### Available Layers

| Layer Name | Description |
|------------|-------------|
| `base` | Land outline |
| `countries` | Country boundaries |
| `stateprovinces` | State/province boundaries |
| `lakes` | Lakes (filled) |
| `lakesOutline` | Lakes (outline only) |
| `rivers` | Rivers |
| `oceans` | Oceans (filled) |
| `relief` | Natural Earth relief raster |
| `reliefgrey` | Greyscale relief |
| `blueMarble` | NASA Blue Marble imagery |
| `conservation` | Conservation International hotspots |
| `ecoregions` | WWF terrestrial ecoregions |
| `marine_ecoregions` | WWF marine ecoregions |
| `roads` | Major roads |
| `railroads` | Railroads |
| `placenames` | Place name labels |
| `countrynames` | Country name labels |
| `stateprovnames` | State/province name labels |

### Available Projections

| Code | Name |
|------|------|
| `epsg:4326` | Geographic (WGS84) |
| `esri:102009` | North America Lambert |
| `esri:102015` | South America Lambert |
| `esri:102014` | Europe Lambert |
| `esri:102012` | Asia Lambert |
| `esri:102024` | Africa Lambert |
| `epsg:3112` | Australia Lambert |
| `epsg:102017` | North Pole Azimuthal |
| `epsg:102019` | South Pole Azimuthal |
| `epsg:54030` | World Robinson |
| `epsg:3395` | World Mercator |

### Available Marker Shapes

**General (unfilled):** `plus`, `cross`, `asterisk`

**Closed (filled):** `circle`, `star`, `square`, `triangle`, `hexagon`, `inversetriangle`

**Open (outlined):** `opencircle`, `openstar`, `opensquare`, `opentriangle`, `openhexagon`, `inverseopentriangle`

**Sizes:** 6, 8, 10, 12, 14, 16 pixels

### Internal Processing

The render service performs these steps:

1. **Validate input** against JSON schema
2. **Generate mapfile** as string using template
3. **Write mapfile** to temp file
4. **Execute shp2img:**
   ```bash
   shp2img -m /tmp/map_abc123.map -o /tmp/output_abc123.png -all_debug 1
   ```
5. **Read output image** and return with appropriate headers
6. **Cleanup** temp files

### Example Mapfile Generation

```php
function generateMapfile(array $request): string {
    $map = "MAP\n";
    $map .= "  NAME \"simplemappr\"\n";
    $map .= "  STATUS ON\n";
    $map .= "  SIZE {$request['width']} {$request['height']}\n";
    $map .= "  EXTENT " . implode(' ', $request['bbox']) . "\n";
    $map .= "  UNITS DD\n";
    $map .= "  IMAGECOLOR 255 255 255\n";
    $map .= "  FONTSET \"/app/mapserver/fonts/fonts.list\"\n";

    // Projection
    $map .= "  PROJECTION\n";
    $map .= "    \"init={$request['projection']}\"\n";
    $map .= "  END\n";

    // Output format
    $map .= "  OUTPUTFORMAT\n";
    $map .= "    NAME \"png\"\n";
    $map .= "    DRIVER \"AGG/PNG\"\n";
    $map .= "    MIMETYPE \"image/png\"\n";
    $map .= "    IMAGEMODE RGB\n";
    $map .= "    EXTENSION \"png\"\n";
    $map .= "  END\n";

    // Add layers, symbols, etc.
    foreach ($request['layers'] as $layerName) {
        $map .= generateLayerBlock($layerName);
    }

    // Add point layers
    foreach ($request['points'] ?? [] as $i => $pointSet) {
        $map .= generatePointLayer($pointSet, $i);
    }

    $map .= "END\n";
    return $map;
}
```

### PHP Web App Integration

```php
class RenderClient {
    private string $renderUrl;

    public function __construct(string $renderUrl) {
        $this->renderUrl = $renderUrl;
    }

    public function render(array $mapDescription): string {
        $ch = curl_init($this->renderUrl . '/render');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($mapDescription),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: image/png'
            ],
            CURLOPT_RETURNTRANSFER => true
        ]);

        $image = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new RenderException("Render failed: HTTP $httpCode");
        }

        return $image;
    }
}

// Usage
$client = new RenderClient('http://render:8080');
$image = $client->render([
    'output' => 'png',
    'width' => 800,
    'height' => 400,
    'points' => [
        [
            'legend' => 'Sample',
            'shape' => 'circle',
            'size' => 10,
            'color' => [255, 0, 0],
            'coordinates' => [[45.5, -75.5]]
        ]
    ]
]);
header('Content-Type: image/png');
echo $image;
```

### Why This Boundary Makes the System Language-Agnostic

The render microservice encapsulates all MapServer complexity behind a simple HTTP/JSON interface:

- **No PHP-specific code needed:** Any language with HTTP client and JSON can call it
- **No MapServer installation required:** Client only needs HTTP access
- **Stable contract:** JSON schema is the interface, implementation can change
- **Testable:** Mock the endpoint for unit tests
- **Deployable:** Run render service on GPU-optimized or high-memory instances

A Python client would look like:

```python
import requests

response = requests.post('http://render:8080/render', json={
    'output': 'png',
    'width': 800,
    'points': [{'legend': 'Test', 'shape': 'circle', 'size': 10,
                'color': [255, 0, 0], 'coordinates': [[45.5, -75.5]]}]
})
with open('map.png', 'wb') as f:
    f.write(response.content)
```

---

## 4. JSON Export/Import Format Specification

### Overview

This format allows users to export their maps from the old SimpleMappr instance and import them into the new one. It is versioned and language-agnostic.

### Schema

```json
{
  "$schema": "http://json-schema.org/draft-07/schema#",
  "type": "object",
  "required": ["version", "exported_at", "maps"],
  "properties": {
    "version": {
      "type": "string",
      "pattern": "^\\d+\\.\\d+$",
      "description": "Schema version"
    },
    "exported_at": {
      "type": "string",
      "format": "date-time",
      "description": "ISO 8601 export timestamp"
    },
    "source": {
      "type": "string",
      "description": "Source system URL"
    },
    "maps": {
      "type": "array",
      "items": { "$ref": "#/definitions/map" }
    }
  },
  "definitions": {
    "map": {
      "type": "object",
      "required": ["title", "created"],
      "properties": {
        "title": { "type": "string", "minLength": 1, "maxLength": 255 },
        "created": { "type": "string", "format": "date-time" },
        "updated": { "type": "string", "format": "date-time" },
        "projection": { "type": "string" },
        "bbox": {
          "type": "object",
          "properties": {
            "minx": { "type": "number" },
            "miny": { "type": "number" },
            "maxx": { "type": "number" },
            "maxy": { "type": "number" }
          }
        },
        "rotation": { "type": "integer", "minimum": 0, "maximum": 360 },
        "origin": { "type": "number", "minimum": -180, "maximum": 180 },
        "layers": { "type": "array", "items": { "type": "string" } },
        "graticules": { "$ref": "#/definitions/graticules" },
        "points": {
          "type": "array",
          "items": { "$ref": "#/definitions/pointSet" }
        },
        "regions": {
          "type": "array",
          "items": { "$ref": "#/definitions/region" }
        },
        "wkt": {
          "type": "array",
          "items": { "$ref": "#/definitions/wktLayer" }
        },
        "options": { "$ref": "#/definitions/options" }
      }
    },
    "pointSet": {
      "type": "object",
      "required": ["coordinates"],
      "properties": {
        "legend": { "type": "string", "maxLength": 100 },
        "shape": {
          "type": "string",
          "enum": ["plus", "cross", "asterisk", "circle", "star", "square",
                   "triangle", "hexagon", "inversetriangle", "opencircle",
                   "openstar", "opensquare", "opentriangle", "openhexagon",
                   "inverseopentriangle"]
        },
        "size": { "type": "integer", "minimum": 6, "maximum": 16 },
        "color": { "$ref": "#/definitions/color" },
        "shadow": { "type": "boolean" },
        "coordinates": {
          "type": "array",
          "items": {
            "type": "object",
            "required": ["lat", "lon"],
            "properties": {
              "lat": { "type": "number", "minimum": -90, "maximum": 90 },
              "lon": { "type": "number", "minimum": -180, "maximum": 180 }
            }
          }
        }
      }
    },
    "region": {
      "type": "object",
      "required": ["places"],
      "properties": {
        "legend": { "type": "string", "maxLength": 100 },
        "color": { "$ref": "#/definitions/color" },
        "border": { "type": "boolean" },
        "hatched": { "type": "boolean" },
        "places": {
          "type": "array",
          "items": { "type": "string" }
        }
      }
    },
    "wktLayer": {
      "type": "object",
      "required": ["data"],
      "properties": {
        "legend": { "type": "string", "maxLength": 100 },
        "color": { "$ref": "#/definitions/color" },
        "border": { "type": "boolean" },
        "data": { "type": "string" }
      }
    },
    "graticules": {
      "type": "object",
      "properties": {
        "enabled": { "type": "boolean" },
        "spacing": { "type": "integer", "minimum": 1, "maximum": 90 },
        "show_labels": { "type": "boolean" }
      }
    },
    "options": {
      "type": "object",
      "properties": {
        "legend": { "type": "boolean" },
        "scalebar": { "type": "boolean" },
        "border": { "type": "boolean" },
        "border_thickness": { "type": "number", "minimum": 0.5, "maximum": 5 }
      }
    },
    "color": {
      "type": "object",
      "required": ["r", "g", "b"],
      "properties": {
        "r": { "type": "integer", "minimum": 0, "maximum": 255 },
        "g": { "type": "integer", "minimum": 0, "maximum": 255 },
        "b": { "type": "integer", "minimum": 0, "maximum": 255 }
      }
    }
  }
}
```

### Example Document

```json
{
  "version": "1.0",
  "exported_at": "2024-01-15T14:30:00Z",
  "source": "https://www.simplemappr.net",
  "maps": [
    {
      "title": "Monarch Butterfly Distribution",
      "created": "2023-06-15T10:00:00Z",
      "updated": "2023-12-01T15:30:00Z",
      "projection": "esri:102009",
      "bbox": {
        "minx": -130,
        "miny": 20,
        "maxx": -60,
        "maxy": 55
      },
      "rotation": 0,
      "origin": -96,
      "layers": ["base", "countries", "stateprovinces", "lakes"],
      "graticules": {
        "enabled": true,
        "spacing": 10,
        "show_labels": true
      },
      "points": [
        {
          "legend": "Summer breeding",
          "shape": "circle",
          "size": 10,
          "color": { "r": 255, "g": 165, "b": 0 },
          "shadow": false,
          "coordinates": [
            { "lat": 43.65, "lon": -79.38 },
            { "lat": 41.88, "lon": -87.63 },
            { "lat": 44.98, "lon": -93.27 },
            { "lat": 39.95, "lon": -75.17 }
          ]
        },
        {
          "legend": "Winter roost",
          "shape": "star",
          "size": 14,
          "color": { "r": 0, "g": 128, "b": 0 },
          "shadow": true,
          "coordinates": [
            { "lat": 19.42, "lon": -100.14 }
          ]
        }
      ],
      "regions": [
        {
          "legend": "Migration corridor",
          "color": { "r": 200, "g": 220, "b": 255 },
          "border": true,
          "hatched": false,
          "places": ["Texas", "Oklahoma", "Kansas", "Nebraska"]
        }
      ],
      "wkt": [
        {
          "legend": "Study area",
          "color": { "r": 255, "g": 255, "b": 200 },
          "border": true,
          "data": "POLYGON((-90 40, -80 40, -80 50, -90 50, -90 40))"
        }
      ],
      "options": {
        "legend": true,
        "scalebar": true,
        "border": true,
        "border_thickness": 1.5
      }
    },
    {
      "title": "Simple Test Map",
      "created": "2024-01-10T09:00:00Z",
      "projection": "epsg:4326",
      "layers": ["countries"],
      "points": [
        {
          "legend": "Sample point",
          "shape": "circle",
          "size": 8,
          "color": { "r": 255, "g": 0, "b": 0 },
          "coordinates": [
            { "lat": 45.5, "lon": -75.7 }
          ]
        }
      ],
      "options": {}
    }
  ]
}
```

### Validation Rules

| Field | Rule |
|-------|------|
| `version` | Must be supported version (currently `1.0`) |
| `maps` | Maximum 1000 maps per export |
| `title` | 1-255 characters, unique within export |
| `coordinates.lat` | -90 to 90 |
| `coordinates.lon` | -180 to 180 |
| `color.r/g/b` | 0 to 255 |
| `size` | 6, 8, 10, 12, 14, or 16 |
| `shape` | Must be from allowed list |
| `projection` | Must be supported projection code |
| `wkt.data` | Must be valid WKT (POINT, LINESTRING, POLYGON, MULTIPOLYGON) |
| `places` | Place names must be recognizable regions or use `CODE[AB|CD]` format |

### Import Flow

1. **Upload**: User uploads `.json` file via web form
2. **Parse**: Server parses JSON, validates against schema
3. **Preview**: Display list of maps found with thumbnails:
   ```
   Found 2 maps in export:
   ✓ Monarch Butterfly Distribution (4 points, 1 region, 1 WKT)
   ✓ Simple Test Map (1 point)

   [Select All] [Import Selected]
   ```
4. **Conflict check**: Compare titles with existing maps
   ```
   ⚠ "Monarch Butterfly Distribution" already exists
     ○ Skip  ○ Replace  ○ Rename to "Monarch Butterfly Distribution (2)"
   ```
5. **Import**: Insert maps into database with `imported_at` timestamp and `import_source` field
6. **Confirm**: Show success message with links to imported maps

### Export Endpoint

```
GET /export
Authorization: Bearer <session_token>

Response:
Content-Type: application/json
Content-Disposition: attachment; filename="simplemappr-export-2024-01-15.json"
```

---

## 5. Database Schema

### SQLite Schema

```sql
-- Enable foreign keys
PRAGMA foreign_keys = ON;

-- Users table
CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,

    -- OAuth identity
    provider TEXT NOT NULL CHECK (provider IN ('orcid', 'google')),
    provider_id TEXT NOT NULL,  -- ORCID iD or Google sub claim

    -- Profile (from OAuth)
    email TEXT,
    display_name TEXT,
    given_name TEXT,
    family_name TEXT,

    -- Application-specific
    role TEXT NOT NULL DEFAULT 'user' CHECK (role IN ('user', 'administrator')),

    -- Timestamps (ISO 8601 strings, SQLite has no native datetime)
    created_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%SZ', 'now')),
    last_login_at TEXT,

    -- Unique constraint on provider + provider_id
    UNIQUE (provider, provider_id)
);

CREATE INDEX idx_users_provider ON users(provider, provider_id);
CREATE INDEX idx_users_email ON users(email);

-- Maps table
CREATE TABLE maps (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,

    -- Map metadata
    title TEXT NOT NULL,

    -- Map configuration (JSON blob)
    config TEXT NOT NULL,

    -- Import tracking
    imported_at TEXT,           -- NULL if created natively
    import_source TEXT,         -- Source URL if imported

    -- Timestamps
    created_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%SZ', 'now')),
    updated_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%SZ', 'now')),

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,

    -- User can't have two maps with same title
    UNIQUE (user_id, title)
);

CREATE INDEX idx_maps_user_id ON maps(user_id);
CREATE INDEX idx_maps_created_at ON maps(created_at);
CREATE INDEX idx_maps_title ON maps(title);

-- Sessions table (for server-side session storage)
CREATE TABLE sessions (
    id TEXT PRIMARY KEY,        -- Random session ID
    user_id INTEGER NOT NULL,

    -- Session data (JSON)
    data TEXT,

    -- Timestamps
    created_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%SZ', 'now')),
    expires_at TEXT NOT NULL,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX idx_sessions_user_id ON sessions(user_id);
CREATE INDEX idx_sessions_expires_at ON sessions(expires_at);

-- Shares table (for public map sharing)
CREATE TABLE shares (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    map_id INTEGER NOT NULL UNIQUE,  -- One share per map

    -- Share token (random string for URL)
    token TEXT NOT NULL UNIQUE,

    -- Timestamps
    created_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%SZ', 'now')),

    FOREIGN KEY (map_id) REFERENCES maps(id) ON DELETE CASCADE
);

CREATE INDEX idx_shares_token ON shares(token);

-- Optional: Citations table (if keeping citation feature)
CREATE TABLE citations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    year INTEGER NOT NULL,
    reference TEXT NOT NULL,
    doi TEXT,
    link TEXT,
    first_author_surname TEXT,
    created_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%SZ', 'now'))
);

CREATE INDEX idx_citations_year ON citations(year);
```

### Differences from Original MySQL Schema

| Aspect | Original (MySQL) | New (SQLite) |
|--------|------------------|--------------|
| Primary keys | `bigint(20) UNSIGNED AUTO_INCREMENT` | `INTEGER PRIMARY KEY AUTOINCREMENT` |
| Timestamps | `int(11)` Unix timestamps | `TEXT` ISO 8601 strings |
| User identity | `identifier` (OpenID URL), `hash` | `provider` + `provider_id` |
| Foreign keys | Not enforced | `PRAGMA foreign_keys = ON` |
| Character set | `utf8`/`utf8mb4` | UTF-8 by default |
| Boolean | `tinyint(1)` | `INTEGER` 0/1 or `TEXT` check constraint |
| JSON storage | `longtext` | `TEXT` (same, but with JSON1 extension for queries) |
| User roles | `int(11)` with PHP mapping | `TEXT` with CHECK constraint |

### OAuth Identity Storage

The `provider` and `provider_id` columns store the OAuth identity:

| Provider | provider_id example |
|----------|---------------------|
| ORCID | `0000-0002-1825-0097` |
| Google | `118234123412341234123` (sub claim) |

This approach:
- Allows users to have accounts with multiple providers (future)
- Makes it clear which provider authenticated the user
- Avoids URL parsing issues from the old OpenID identifiers

### Distinguishing Imported Maps

Maps created natively have:
```sql
imported_at = NULL
import_source = NULL
```

Maps imported from JSON export have:
```sql
imported_at = '2024-01-15T14:30:00Z'
import_source = 'https://www.simplemappr.net'
```

This allows:
- Filtering to show only imported maps
- Displaying import timestamp in UI
- Tracking data provenance

### Example Queries

```sql
-- Get user by ORCID
SELECT * FROM users WHERE provider = 'orcid' AND provider_id = '0000-0002-1825-0097';

-- Get user's maps ordered by last update
SELECT * FROM maps WHERE user_id = ? ORDER BY updated_at DESC;

-- Get shared map by token
SELECT m.* FROM maps m
JOIN shares s ON m.id = s.map_id
WHERE s.token = ?;

-- Get imported maps for user
SELECT * FROM maps WHERE user_id = ? AND imported_at IS NOT NULL;

-- Update map (automatically update timestamp via trigger)
CREATE TRIGGER update_maps_timestamp
AFTER UPDATE ON maps
BEGIN
    UPDATE maps SET updated_at = strftime('%Y-%m-%dT%H:%M:%SZ', 'now')
    WHERE id = NEW.id;
END;

-- Clean expired sessions
DELETE FROM sessions WHERE expires_at < strftime('%Y-%m-%dT%H:%M:%SZ', 'now');
```

---

## 6. Docker Architecture

### Container Overview

```
┌─────────────────────────────────────────────────────────────┐
│                     Docker Network                           │
│                                                              │
│  ┌─────────────────────┐      ┌─────────────────────────┐   │
│  │        app          │      │        render           │   │
│  │                     │      │                         │   │
│  │  PHP 7.4 + Apache   │─────▶│  PHP 7.4 + MapServer    │   │
│  │  Web Application    │ HTTP │  Rendering Service      │   │
│  │                     │      │                         │   │
│  │  /var/lib/simplemappr      │  /app/mapserver/maps    │   │
│  │  └── simplemappr.db │      │  └── (shapefiles)       │   │
│  └──────────┬──────────┘      └─────────────────────────┘   │
│             │                                                │
│             │ volume mount                                   │
│  ┌──────────▼──────────┐                                    │
│  │    data volume      │                                    │
│  │   simplemappr_data  │                                    │
│  └─────────────────────┘                                    │
└─────────────────────────────────────────────────────────────┘
```

### docker-compose.yml

```yaml
version: '3.8'

services:
  app:
    build:
      context: .
      dockerfile: Dockerfile
      args:
        - BUILD_ENV=production
    image: simplemappr/app:latest
    container_name: simplemappr_app
    restart: unless-stopped
    ports:
      - "8080:80"
    environment:
      - ENVIRONMENT=production
      - RENDER_SERVICE_URL=http://render:8080
      - ORCID_CLIENT_ID=${ORCID_CLIENT_ID}
      - ORCID_CLIENT_SECRET=${ORCID_CLIENT_SECRET}
      - GOOGLE_CLIENT_ID=${GOOGLE_CLIENT_ID}
      - GOOGLE_CLIENT_SECRET=${GOOGLE_CLIENT_SECRET}
      - APP_URL=${APP_URL:-http://localhost:8080}
      - APP_SECRET=${APP_SECRET}
    volumes:
      - simplemappr_data:/var/lib/simplemappr
    depends_on:
      - render
    networks:
      - simplemappr_network
    # For Apple Silicon Macs, uncomment:
    # platform: linux/amd64

  render:
    build:
      context: .
      dockerfile: Dockerfile.render
    image: simplemappr/render:latest
    container_name: simplemappr_render
    restart: unless-stopped
    expose:
      - "8080"
    volumes:
      - ./mapserver/maps:/app/mapserver/maps:ro
    networks:
      - simplemappr_network
    # For Apple Silicon Macs, uncomment:
    # platform: linux/amd64

volumes:
  simplemappr_data:
    driver: local

networks:
  simplemappr_network:
    driver: bridge
```

### Dockerfile (Web App)

```dockerfile
FROM php:7.4-apache

# Install PHP extensions
RUN apt-get update && apt-get install -y \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libsqlite3-dev \
    unzip \
    git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_sqlite \
        gd \
        zip \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Enable Apache modules
RUN a2enmod rewrite headers

# Copy Apache configuration
COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf

# Create directories
RUN mkdir -p /var/lib/simplemappr \
    && chown -R www-data:www-data /var/lib/simplemappr

# Copy application code
WORKDIR /var/www/html
COPY --chown=www-data:www-data . .

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader

# Set permissions
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

CMD ["apache2-foreground"]
```

### Dockerfile.render (Render Service)

```dockerfile
FROM php:7.4-cli

# Install MapServer and dependencies
RUN apt-get update && apt-get install -y \
    mapserver-bin \
    libmapserver-dev \
    gdal-bin \
    libgdal-dev \
    proj-bin \
    libproj-dev \
    libgeos-dev \
    libfreetype6-dev \
    libpng-dev \
    libjpeg-dev \
    fonts-dejavu-core \
    && rm -rf /var/lib/apt/lists/*

# Verify MapServer installation
RUN shp2img -v

# Create app directory
WORKDIR /app

# Copy render service code
COPY render/ /app/

# Copy fonts
COPY mapserver/fonts /app/mapserver/fonts

# Create temp directory
RUN mkdir -p /tmp/mapserver \
    && chmod 777 /tmp/mapserver

EXPOSE 8080

# Simple PHP built-in server (replace with proper server in production)
CMD ["php", "-S", "0.0.0.0:8080", "-t", "/app/public"]
```

### SQLite File Persistence

The SQLite database is stored in a Docker named volume:

```yaml
volumes:
  simplemappr_data:
    driver: local
```

The volume is mounted at `/var/lib/simplemappr` in the container. The database file is `/var/lib/simplemappr/simplemappr.db`.

**Why a named volume instead of bind mount:**
- Persists across container recreation
- Better performance on Docker for Mac/Windows
- Managed by Docker (easy backup, migration)
- Works identically in development and production

### MapServer Installation

MapServer is installed via `apt` in the render container:

```dockerfile
RUN apt-get install -y \
    mapserver-bin \    # shp2img, mapserv
    libmapserver-dev   # Development headers (if needed)
```

This provides:
- `shp2img` - Command-line map renderer
- `mapserv` - CGI/FastCGI server (optional)
- All required libraries (GDAL, PROJ, GEOS, FreeType)

**No compilation required.** The Debian/Ubuntu packages are well-maintained and include all common drivers.

### Shapefile Handling

Natural Earth shapefiles are stored in `mapserver/maps/` and bind-mounted into the render container:

```yaml
volumes:
  - ./mapserver/maps:/app/mapserver/maps:ro  # Read-only
```

**Directory structure:**
```
mapserver/maps/
├── 10m_cultural/
│   └── 10m_cultural/
│       ├── ne_10m_admin_0_map_units.shp
│       ├── ne_10m_admin_1_states_provinces.shp
│       └── ...
├── 10m_physical/
│   ├── ne_10m_land.shp
│   ├── ne_10m_lakes.shp
│   └── ...
├── HYP_HR_SR_OB_DR/
│   └── HYP_HR_SR_OB_DR.tif
└── ...
```

**Download Natural Earth data:**
```bash
#!/bin/bash
# scripts/download-shapefiles.sh

mkdir -p mapserver/maps
cd mapserver/maps

# 10m Cultural
wget https://naciscdn.org/naturalearth/10m/cultural/ne_10m_admin_0_map_units.zip
wget https://naciscdn.org/naturalearth/10m/cultural/ne_10m_admin_1_states_provinces.zip
wget https://naciscdn.org/naturalearth/10m/cultural/ne_10m_admin_1_states_provinces_lines.zip

# 10m Physical
wget https://naciscdn.org/naturalearth/10m/physical/ne_10m_land.zip
wget https://naciscdn.org/naturalearth/10m/physical/ne_10m_lakes.zip
wget https://naciscdn.org/naturalearth/10m/physical/ne_10m_rivers_lake_centerlines.zip
wget https://naciscdn.org/naturalearth/10m/physical/ne_10m_ocean.zip

# Unzip all
for f in *.zip; do unzip -o "$f" -d "${f%.zip}"; done
rm *.zip
```

### Backup Strategy

SQLite backup is trivial: copy the file while the database is not being written to.

**Option 1: Simple cron job in host**
```bash
# /etc/cron.daily/backup-simplemappr
#!/bin/bash
BACKUP_DIR=/backups/simplemappr
DATE=$(date +%Y%m%d)

# Copy database from Docker volume
docker cp simplemappr_app:/var/lib/simplemappr/simplemappr.db "$BACKUP_DIR/simplemappr-$DATE.db"

# Keep last 30 days
find "$BACKUP_DIR" -name "*.db" -mtime +30 -delete
```

**Option 2: SQLite online backup API (safer)**
```bash
# In container
sqlite3 /var/lib/simplemappr/simplemappr.db ".backup /backups/simplemappr-$(date +%Y%m%d).db"
```

**Option 3: Docker exec with backup container**
```yaml
# docker-compose.yml
  backup:
    image: alpine
    volumes:
      - simplemappr_data:/data:ro
      - ./backups:/backups
    entrypoint: |
      /bin/sh -c 'while true; do
        cp /data/simplemappr.db /backups/simplemappr-$$(date +%Y%m%d-%H%M%S).db
        sleep 86400
      done'
```

### Apple Silicon (M1/M2/M3) Considerations

MapServer Docker images may not have native ARM64 builds. Use the `platform` directive to run under Rosetta 2 emulation:

```yaml
services:
  app:
    platform: linux/amd64
  render:
    platform: linux/amd64
```

**Performance impact:** ~20-30% slower than native, but adequate for development. Production deployments should use x86_64 servers.

**Alternative:** Build MapServer from source for ARM64, but this is complex and time-consuming.

### Development vs Production

**Development (`docker-compose.override.yml`):**
```yaml
version: '3.8'

services:
  app:
    build:
      args:
        - BUILD_ENV=development
    volumes:
      - .:/var/www/html  # Live code reload
      - simplemappr_data:/var/lib/simplemappr
    environment:
      - ENVIRONMENT=development
      - XDEBUG_MODE=develop,debug
    ports:
      - "8080:80"
```

**Production:**
```yaml
# docker-compose.prod.yml
version: '3.8'

services:
  app:
    image: simplemappr/app:${VERSION:-latest}
    restart: always
    environment:
      - ENVIRONMENT=production
    # No volume mount for code - use built image
```

---

## 7. Developer Guide for Porters

This section is for developers who want to reimplement the SimpleMappr web layer in a different language (Python, Ruby, Go, etc.).

### Architecture Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                        Your Web Layer                            │
│  (Python/Ruby/Go/Node.js/etc.)                                  │
│                                                                  │
│  ┌──────────────┐  ┌──────────────┐  ┌───────────────────────┐ │
│  │   Routes     │  │   Auth       │  │   Map Management      │ │
│  │   /          │  │   OAuth2     │  │   CRUD operations     │ │
│  │   /api       │  │   Sessions   │  │   Import/Export       │ │
│  │   /render    │  │              │  │                       │ │
│  └──────────────┘  └──────────────┘  └───────────────────────┘ │
│           │                                      │              │
│           │ HTTP/JSON                            │ SQL          │
│           ▼                                      ▼              │
│  ┌──────────────────┐              ┌─────────────────────────┐ │
│  │ Render Service   │              │   SQLite Database       │ │
│  │ (unchanged)      │              │   simplemappr.db        │ │
│  └──────────────────┘              └─────────────────────────┘ │
└─────────────────────────────────────────────────────────────────┘
```

### Interface 1: Render Service API

The render service is the **only** MapServer interface you need. It accepts JSON and returns images.

**Endpoint:** `POST /render`

**Request:**
```json
{
  "output": "png",
  "width": 900,
  "height": 450,
  "projection": "epsg:4326",
  "bbox": [-180, -90, 180, 90],
  "layers": ["countries"],
  "points": [
    {
      "legend": "Sample",
      "shape": "circle",
      "size": 10,
      "color": [255, 0, 0],
      "coordinates": [[45.5, -75.5]]
    }
  ]
}
```

**Response:** PNG image bytes with `Content-Type: image/png`

See **Section 3** for complete API documentation.

### Interface 2: SQLite Schema

The SQLite database is the **only** data interface you need. See **Section 5** for the complete schema.

**Key tables:**
- `users` - User accounts with OAuth identity
- `maps` - Saved map configurations (JSON in `config` column)
- `sessions` - Server-side session storage
- `shares` - Public sharing tokens

**Example queries in Python:**
```python
import sqlite3
import json

conn = sqlite3.connect('/var/lib/simplemappr/simplemappr.db')
conn.row_factory = sqlite3.Row

# Get user by ORCID
user = conn.execute(
    "SELECT * FROM users WHERE provider = ? AND provider_id = ?",
    ('orcid', '0000-0002-1825-0097')
).fetchone()

# Get user's maps
maps = conn.execute(
    "SELECT * FROM maps WHERE user_id = ? ORDER BY updated_at DESC",
    (user['id'],)
).fetchall()

# Parse map config
for m in maps:
    config = json.loads(m['config'])
    print(f"{m['title']}: {len(config.get('points', []))} point sets")

# Save a map
config = {
    'projection': 'epsg:4326',
    'layers': ['countries'],
    'points': [...]
}
conn.execute(
    "INSERT INTO maps (user_id, title, config) VALUES (?, ?, ?)",
    (user['id'], 'My Map', json.dumps(config))
)
conn.commit()
```

### Interface 3: JSON Export Format

The JSON export format is the **user data contract**. See **Section 4** for the complete specification.

**Key points:**
- Version field for forward compatibility
- ISO 8601 timestamps
- Coordinates as `{lat, lon}` objects
- Colors as `{r, g, b}` objects
- WKT strings for custom geometry

### REST API Endpoints

Your web layer should implement these endpoints to be compatible:

| Method | Path | Description |
|--------|------|-------------|
| `GET` | `/` | Main application page |
| `POST` | `/login/orcid` | ORCID OAuth redirect |
| `POST` | `/login/google` | Google OAuth redirect |
| `GET` | `/callback/orcid` | ORCID OAuth callback |
| `GET` | `/callback/google` | Google OAuth callback |
| `GET` | `/logout` | Destroy session |
| `POST` | `/render` | Render map (proxy to render service) |
| `GET` | `/api` | API documentation |
| `GET/POST` | `/api/map` | Generate map image |
| `GET` | `/maps` | List user's maps |
| `GET` | `/maps/{id}` | Get map by ID |
| `POST` | `/maps` | Create map |
| `PUT` | `/maps/{id}` | Update map |
| `DELETE` | `/maps/{id}` | Delete map |
| `POST` | `/maps/{id}/share` | Create share link |
| `DELETE` | `/maps/{id}/share` | Remove share link |
| `GET` | `/share/{token}` | View shared map |
| `GET` | `/export` | Export user's maps as JSON |
| `POST` | `/import` | Import maps from JSON |

### Equivalent Dependencies by Language

#### Python
```
# requirements.txt
flask>=2.0              # Web framework (or django, fastapi)
authlib>=1.0            # OAuth2 client
requests>=2.28          # HTTP client for render service
pillow>=9.0             # Image handling (optional)
```

#### Ruby
```ruby
# Gemfile
gem 'sinatra'           # Web framework (or rails)
gem 'omniauth-orcid'    # ORCID OAuth
gem 'omniauth-google-oauth2'
gem 'httparty'          # HTTP client
gem 'sqlite3'           # Database
```

#### Go
```go
// go.mod
require (
    github.com/gin-gonic/gin v1.9.0        // Web framework
    golang.org/x/oauth2 v0.15.0            // OAuth2
    github.com/mattn/go-sqlite3 v1.14.19   // SQLite driver
)
```

#### Node.js
```json
{
  "dependencies": {
    "express": "^4.18.0",
    "passport": "^0.7.0",
    "passport-orcid": "^0.0.4",
    "passport-google-oauth20": "^2.0.0",
    "better-sqlite3": "^9.0.0",
    "axios": "^1.6.0"
  }
}
```

### Minimal Implementation Checklist

1. **OAuth2 Login**
   - Implement ORCID and Google OAuth flows
   - Store user in `users` table with `provider` and `provider_id`
   - Create session in `sessions` table

2. **Session Management**
   - Generate random session ID, store in cookie
   - Validate session on protected routes
   - Clean expired sessions periodically

3. **Map CRUD**
   - List maps for logged-in user
   - Create/update map with JSON config
   - Delete map (with cascade to shares)

4. **Render Proxy**
   - Accept map parameters from frontend
   - Call render service with JSON
   - Return image to client

5. **Import/Export**
   - Export: Query maps, format as JSON, return file
   - Import: Parse JSON, validate, insert maps

6. **Share Links**
   - Generate random token, insert into `shares`
   - Public endpoint to view shared map

### Frontend Considerations

The original SimpleMappr uses:
- jQuery for DOM manipulation
- OpenLayers for interactive map preview
- Custom JavaScript for form handling

Your port could use:
- **React/Vue/Svelte** for UI components
- **Leaflet** or **MapLibre GL** for interactive preview
- **Modern fetch API** for AJAX calls

The render service handles all actual map generation, so the frontend only needs to:
1. Collect parameters from form
2. POST to render endpoint
3. Display returned image
4. Save/load configurations via REST API

---

## 8. Key Differences from Original

### What Has Changed

| Aspect | Original | New |
|--------|----------|-----|
| **PHP Version** | 5.6+ | 7.4+ |
| **Database** | MySQL | SQLite |
| **MapServer Integration** | php-mapscript extension | shell_exec + shp2img |
| **Authentication** | Janrain/RPX OpenID | League OAuth2 (ORCID/Google) |
| **User Identity** | OpenID identifier URL + hash | Provider + provider_id |
| **Deployment** | Manual LAMP setup | Docker Compose |
| **Routing** | Phroute | (Implementer's choice) |
| **Timestamps** | Unix integers | ISO 8601 strings |
| **Migrations** | Phinx | Native SQLite or tool of choice |
| **Architecture** | Monolithic | Web layer + render microservice |
| **Data Migration** | N/A | JSON export/import |

### What Has Stayed the Same

| Aspect | Details |
|--------|---------|
| **Core Functionality** | Point mapping, region shading, WKT overlays |
| **Output Formats** | PNG, JPG, SVG, TIF, KML, PPTX, DOCX |
| **Projections** | Same 11 projections with PROJ strings |
| **Marker Shapes** | Same shapes and sizes |
| **Layers** | Same Natural Earth shapefiles |
| **API Parameters** | Same parameter names and semantics |
| **Map Configuration** | JSON structure largely unchanged |
| **User Roles** | user, administrator |
| **Map Sharing** | Token-based public links |

### Breaking Changes

1. **Authentication**: Users must re-authenticate with ORCID or Google. Old sessions invalid.

2. **User Identity**: No automatic migration of user accounts. Users create new accounts.

3. **Saved Maps**: Must be exported from old system and imported to new. No automatic migration.

4. **URLs**: Share URLs will change (new tokens, possibly new domain).

5. **API Response**: POST to `/api` returns same structure but image URLs point to new domain.

6. **Database Access**: Direct MySQL queries won't work. Use SQLite or REST API.

### Migration Path for Users

1. Log in to old SimpleMappr (while still available)
2. Export maps using new export endpoint (to be added to old system)
3. Save JSON file
4. Log in to new SimpleMappr with ORCID or Google
5. Import JSON file
6. Verify maps imported correctly

### Migration Path for Administrators

1. Deploy new Docker-based system
2. Configure OAuth credentials (ORCID, Google)
3. Download Natural Earth shapefiles
4. Test render service
5. (Optional) Add export endpoint to old system
6. Announce migration timeline to users
7. Run both systems in parallel during transition
8. Decommission old system

---

## Appendix A: Configuration Files

### config/conf.php

```php
<?php
// Environment: development, production, testing
defined("ENVIRONMENT") || define("ENVIRONMENT", getenv('ENVIRONMENT') ?: "development");

// Application root
defined("ROOT") || define("ROOT", dirname(__DIR__));

// Application URL (no trailing slash)
defined("APP_URL") || define("APP_URL", getenv('APP_URL') ?: "http://localhost:8080");

// Render service URL
defined("RENDER_SERVICE_URL") || define("RENDER_SERVICE_URL",
    getenv('RENDER_SERVICE_URL') ?: "http://render:8080");

// Database path
defined("DATABASE_PATH") || define("DATABASE_PATH",
    getenv('DATABASE_PATH') ?: "/var/lib/simplemappr/simplemappr.db");

// OAuth: ORCID
defined("ORCID_CLIENT_ID") || define("ORCID_CLIENT_ID", getenv('ORCID_CLIENT_ID'));
defined("ORCID_CLIENT_SECRET") || define("ORCID_CLIENT_SECRET", getenv('ORCID_CLIENT_SECRET'));

// OAuth: Google
defined("GOOGLE_CLIENT_ID") || define("GOOGLE_CLIENT_ID", getenv('GOOGLE_CLIENT_ID'));
defined("GOOGLE_CLIENT_SECRET") || define("GOOGLE_CLIENT_SECRET", getenv('GOOGLE_CLIENT_SECRET'));

// Session secret
defined("APP_SECRET") || define("APP_SECRET", getenv('APP_SECRET') ?: 'change-me-in-production');

// Cookie settings
defined("COOKIE_TIMEOUT") || define("COOKIE_TIMEOUT", 60 * 60 * 24 * 14); // 2 weeks
```

### .env.example

```bash
# Environment
ENVIRONMENT=development

# Application
APP_URL=http://localhost:8080
APP_SECRET=your-random-secret-key-here

# OAuth: ORCID (https://orcid.org/developer-tools)
ORCID_CLIENT_ID=APP-XXXXXXXXXXXXXXXX
ORCID_CLIENT_SECRET=xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx

# OAuth: Google (https://console.cloud.google.com/apis/credentials)
GOOGLE_CLIENT_ID=xxxxxxxxxxxx-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=GOCSPX-xxxxxxxxxxxxxxxxxxxxxxxxx

# Render service (for external deployment)
RENDER_SERVICE_URL=http://render:8080
```

---

## Appendix B: Render Service Implementation

### render/public/index.php

```php
<?php
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($method === 'POST' && $path === '/render') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON']);
        exit;
    }

    try {
        $renderer = new MapRenderer();
        $image = $renderer->render($input);

        $contentType = [
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'svg' => 'image/svg+xml',
            'tif' => 'image/tiff'
        ][$input['output'] ?? 'png'] ?? 'image/png';

        header('Content-Type: ' . $contentType);
        echo $image;
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
} elseif ($method === 'GET' && $path === '/health') {
    echo json_encode(['status' => 'ok', 'mapserver' => trim(shell_exec('shp2img -v 2>&1'))]);
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Not found']);
}
```

### render/src/MapRenderer.php

```php
<?php
class MapRenderer {
    private string $tempDir = '/tmp/mapserver';
    private string $shapesDir = '/app/mapserver/maps';
    private string $fontsDir = '/app/mapserver/fonts';

    public function render(array $request): string {
        $mapfile = $this->generateMapfile($request);
        $mapPath = $this->tempDir . '/' . uniqid('map_') . '.map';
        $output = $request['output'] ?? 'png';
        $outputPath = $this->tempDir . '/' . uniqid('out_') . '.' . $output;

        file_put_contents($mapPath, $mapfile);

        $cmd = sprintf(
            'shp2img -m %s -o %s 2>&1',
            escapeshellarg($mapPath),
            escapeshellarg($outputPath)
        );

        $result = shell_exec($cmd);

        if (!file_exists($outputPath)) {
            unlink($mapPath);
            throw new Exception("Render failed: $result");
        }

        $image = file_get_contents($outputPath);

        // Cleanup
        unlink($mapPath);
        unlink($outputPath);

        return $image;
    }

    private function generateMapfile(array $request): string {
        $width = $request['width'] ?? 900;
        $height = $request['height'] ?? 450;
        $projection = $request['projection'] ?? 'epsg:4326';
        $bbox = $request['bbox'] ?? [-180, -90, 180, 90];

        $map = "MAP\n";
        $map .= "  NAME \"simplemappr\"\n";
        $map .= "  STATUS ON\n";
        $map .= "  SIZE $width $height\n";
        $map .= "  EXTENT {$bbox[0]} {$bbox[1]} {$bbox[2]} {$bbox[3]}\n";
        $map .= "  UNITS DD\n";
        $map .= "  IMAGECOLOR 255 255 255\n";
        $map .= "  FONTSET \"{$this->fontsDir}/fonts.list\"\n";
        $map .= "\n";

        // Web config
        $map .= "  WEB\n";
        $map .= "    IMAGEPATH \"{$this->tempDir}\"\n";
        $map .= "    IMAGEURL \"/tmp\"\n";
        $map .= "  END\n";
        $map .= "\n";

        // Projection
        $map .= "  PROJECTION\n";
        $map .= "    \"init=$projection\"\n";
        $map .= "  END\n";
        $map .= "\n";

        // Output format
        $map .= $this->getOutputFormat($request['output'] ?? 'png');

        // Symbols
        $map .= $this->getSymbols();

        // Layers
        foreach ($request['layers'] ?? ['countries'] as $layerName) {
            $map .= $this->getLayerBlock($layerName, $projection);
        }

        // Point layers
        foreach ($request['points'] ?? [] as $i => $pointSet) {
            $map .= $this->getPointLayer($pointSet, $i, $projection);
        }

        // Region layers
        foreach ($request['regions'] ?? [] as $i => $region) {
            $map .= $this->getRegionLayer($region, $i, $projection);
        }

        // WKT layers
        foreach ($request['wkt'] ?? [] as $i => $wkt) {
            $map .= $this->getWktLayer($wkt, $i, $projection);
        }

        // Legend
        if ($request['options']['legend'] ?? false) {
            $map .= $this->getLegend();
        }

        // Scalebar
        if ($request['options']['scalebar'] ?? false) {
            $map .= $this->getScalebar();
        }

        $map .= "END\n";

        return $map;
    }

    // ... additional methods for each block type
}
```

---

*Document version: 1.0*
*Last updated: 2024-01-15*
*For use with SimpleMappr Docker migration project*
