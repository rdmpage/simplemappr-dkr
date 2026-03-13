# SimpleMappr Docker

A Docker-based deployment of [SimpleMappr](https://github.com/dshorthouse/SimpleMappr), a point map web application for quality publications and presentations.

## Features

Priorities discussed with David Shorthouse, the original developer, ordered from most to least important:

1. **Raster-based shaded relief** — support for [Natural Earth cross-blend hypsometry](https://www.naturalearthdata.com/downloads/10m-raster-data/10m-cross-blend-hypso/), used in the majority of SimpleMappr outputs
2. **Zoom, pan, and crop** — essential for raster layers, where output resolution is otherwise too poor for publication
3. **Point layers** — coordinate-based markers, the core use case
4. **Map storage and user authentication** — saves layers and settings between sessions; deferred to a later version
5. **Region and shape drawing layers** — relatively little use compared to points
6. **API** — used by integrators such as Tropicos and Yale; not a priority for initial release
7. **Word and PowerPoint export** — low priority

## Quick Start

### Prerequisites

- Docker and Docker Compose
- ~2GB disk space for Natural Earth shapefiles

### 1. Clone and Setup

```bash
cd simplemappr-dkr

# Copy environment file
cp .env.example .env
```

### 2. Download Shapefiles

```bash
./scripts/download-shapefiles.sh
```

This downloads Natural Earth data (~500MB) required for map rendering.

### 3. Build and Run

```bash
docker-compose build
docker-compose up
```

The application will be available at http://localhost:8080

### 4. Verify Installation

- Open http://localhost:8080 - should show the map editor
- Open http://localhost:8080/health - should show service status
- Open http://localhost:8080/status - should show map data availability

## Configuration

### Environment Variables

| Variable | Description | Default |
|----------|-------------|---------|
| `ENVIRONMENT` | development/production | development |
| `APP_URL` | Public URL of application | http://localhost:8080 |
| `APP_SECRET` | Session encryption key | (change in production) |

## Development

### Live Reload

The development configuration (`docker-compose.override.yml`) mounts source code into the container for live editing.

```bash
# Start in development mode (default)
docker-compose up

# Rebuild after Dockerfile changes
docker-compose build
docker-compose up
```

### Logs

```bash
# All services
docker-compose logs -f

# Specific service
docker-compose logs -f app
docker-compose logs -f render
```

### Database Access

```bash
# Open SQLite CLI
docker-compose exec app sqlite3 /var/lib/simplemappr/simplemappr.db
```

## Documentation

- [Architecture](docs/ARCHITECTURE.md) — System design and container overview
- [Deployment](docs/DEPLOYMENT.md) — Production deployment guide

## License

MIT License - see LICENSE file for details.
