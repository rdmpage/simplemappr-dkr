# SimpleMappr Docker

A modern Docker-based deployment of [SimpleMappr](https://github.com/dshorthouse/SimpleMappr), a point map web application for quality publications and presentations.

## Quick Start

### Prerequisites

- Docker and Docker Compose
- ~2GB disk space for Natural Earth shapefiles

### 1. Clone and Setup

```bash
cd simplemappr-dkr

# Copy environment file
cp .env.example .env

# Edit .env with your OAuth credentials (optional for basic testing)
```

### 2. Download Shapefiles

```bash
./scripts/download-shapefiles.sh
```

This downloads Natural Earth data (~500MB) required for map rendering.

### 3. Build and Run

```bash
# Build containers
docker-compose build

# Start services
docker-compose up
```

The application will be available at http://localhost:8080

### 4. Verify Installation

- Open http://localhost:8080 - should show home page
- Open http://localhost:8080/health - should show service status
- Open http://localhost:8080/api - should show API info

## Architecture

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
│  └─────────────────────┘      └─────────────────────────┘   │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

### Services

- **app**: PHP 7.4 web application with Apache
- **render**: PHP 7.4 render microservice with MapServer

### Volumes

- `simplemappr_data`: Persistent SQLite database storage

## Configuration

### Environment Variables

| Variable | Description | Default |
|----------|-------------|---------|
| `ENVIRONMENT` | development/production | development |
| `APP_URL` | Public URL of application | http://localhost:8080 |
| `APP_SECRET` | Session encryption key | (change in production) |
| `ORCID_CLIENT_ID` | ORCID OAuth client ID | - |
| `ORCID_CLIENT_SECRET` | ORCID OAuth client secret | - |
| `GOOGLE_CLIENT_ID` | Google OAuth client ID | - |
| `GOOGLE_CLIENT_SECRET` | Google OAuth client secret | - |

### OAuth Setup

#### ORCID

1. Register at https://orcid.org/developer-tools
2. Set redirect URI to `{APP_URL}/callback/orcid`
3. Add credentials to `.env`

#### Google

1. Create project at https://console.cloud.google.com
2. Enable Google+ API
3. Create OAuth 2.0 credentials
4. Set redirect URI to `{APP_URL}/callback/google`
5. Add credentials to `.env`

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

# Example queries
.tables
SELECT * FROM users;
.quit
```

## API

### Render Map

```bash
curl -X POST http://localhost:8080/render \
  -H "Content-Type: application/json" \
  -d '{
    "output": "png",
    "width": 400,
    "height": 200,
    "layers": ["countries"],
    "points": [{
      "legend": "Sample",
      "shape": "circle",
      "size": 10,
      "color": [255, 0, 0],
      "coordinates": [[45.5, -75.5]]
    }]
  }' \
  --output map.png
```

### Available Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/` | GET | Home page |
| `/health` | GET | Health check |
| `/api` | GET | API documentation |
| `/render` | POST | Render map from JSON |

## Production Deployment

### Build Production Image

```bash
docker-compose -f docker-compose.yml build
```

### Run Production

```bash
# Use production compose file only (no override)
docker-compose -f docker-compose.yml up -d
```

### Apple Silicon (M1/M2/M3)

Uncomment the `platform: linux/amd64` lines in `docker-compose.yml` if MapServer doesn't work natively.

### Backup Database

```bash
# Copy database from container
docker cp simplemappr_app:/var/lib/simplemappr/simplemappr.db ./backup.db
```

## Documentation

See [docs/architecture.md](docs/architecture.md) for detailed architecture documentation.

## License

MIT License - see LICENSE file for details.
