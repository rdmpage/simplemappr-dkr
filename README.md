# SimpleMappr.cloud

[![DOI](https://zenodo.org/badge/1178665330.svg)](https://doi.org/10.5281/zenodo.19226225)

A Docker-based port of [SimpleMappr](https://github.com/dshorthouse/SimpleMappr), a web application for creating publication-quality point maps. Enter geographic coordinates, choose map layers and styles, then preview and download your map.

## Quick Start

### Prerequisites

- Docker and Docker Compose
- ~500MB disk space for map shapefiles

### 1. Clone and configure

```bash
git clone https://github.com/rdmpage/simplemappr-dkr.git
cd simplemappr-dkr
cp .env.example .env
```

### 2. Download map data

```bash
mkdir -p mapserver/maps
bash scripts/download-naturalearth.sh ./mapserver/maps

# Optional: biodiversity hotspots and WWF ecoregion layers
bash scripts/download-external-data.sh ./mapserver/maps
```

### 3. Build and start

```bash
docker compose up -d
```

The application will be available at http://localhost:8080

### 4. Generate projection thumbnails

```bash
bash scripts/generate-projection-thumbnails.sh
```

## Configuration

| Variable | Description | Default |
|----------|-------------|---------|
| `ENVIRONMENT` | `development` or `production` | `development` |
| `APP_PORT` | Port to expose the application on | `8080` |

For production (e.g. port 80), set `APP_PORT=80` in `.env`.

## Testing

The test suite has three layers. The render service must be running for the API and visual tests.

### Unit tests

Tests render service logic (projections, layers, mapfile generation) with no Docker dependency:

```bash
cd render && vendor/bin/phpunit --testdox
```

### API tests

Tests HTTP status codes, image dimensions, all projections, output formats, and error handling:

```bash
bash tests/api-test.sh

# Against a remote server:
RENDER_URL=http://your-server/render bash tests/api-test.sh
```

### Visual regression tests

Renders known configurations and compares against stored reference images using a pixel-difference threshold. Covers the projections that previously had rendering artifacts (horizontal lines in Lambert projections, blank South Pole):

```bash
bash tests/visual/visual-test.sh
```

If a test fails, a diff image is saved to `tests/visual/diffs/` for inspection.

To update the reference images after an intentional rendering change:

```bash
bash tests/visual/generate-references.sh
git add tests/visual/reference/ && git commit -m "Update visual regression references"
```

## Updating

```bash
git pull origin main
docker compose build --no-cache render
docker compose up -d
bash scripts/generate-projection-thumbnails.sh
```

## Documentation

- [Deployment guide](docs/DEPLOYMENT.md) — full production setup including Hetzner, HTTPS, and firewall
- [Architecture](docs/ARCHITECTURE.md) — system design and container overview

## License

MIT License — see LICENSE file for details.
