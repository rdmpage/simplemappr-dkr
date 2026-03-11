# SimpleMappr Docker - Project TODO

## Legend
- [ ] Not started
- [x] Completed
- [~] In progress

---

## Phase 1: Infrastructure (Completed)

- [x] Architecture documentation (`docs/architecture.md`)
- [x] Docker environment
  - [x] Dockerfile for web app
  - [x] Dockerfile for render service
  - [x] docker-compose.yml (production)
  - [x] docker-compose.override.yml (development)
  - [x] Apache configuration
- [x] Database setup
  - [x] SQLite schema (users, maps, sessions, shares)
  - [x] Database singleton class
- [x] Render microservice
  - [x] MapRenderer class
  - [x] MapfileGenerator class
  - [x] Projections definitions
  - [x] Layers definitions
  - [x] Symbol/marker definitions
  - [x] Health check endpoint
- [x] Configuration
  - [x] Environment-based config (conf.php)
  - [x] .env.example template
- [x] Shapefile download script

---

## Phase 2: Authentication

- [ ] OAuth2 integration
  - [ ] Install league/oauth2-client via Composer
  - [ ] ORCID provider setup
    - [ ] OAuth controller/routes
    - [ ] Token exchange
    - [ ] User profile fetching
  - [ ] Google provider setup
    - [ ] OAuth controller/routes
    - [ ] Token exchange
    - [ ] User profile fetching
- [ ] Session management
  - [ ] Session creation on login
  - [ ] Session validation middleware
  - [ ] Session destruction on logout
  - [ ] Cookie handling
- [ ] User management
  - [ ] Create user on first login
  - [ ] Update last_login_at
  - [ ] User roles (user, administrator)

---

## Phase 3: Core Web Application

- [ ] Routing
  - [ ] Install routing library (e.g., nikic/fast-route or slim/slim)
  - [ ] Define all routes
  - [ ] Middleware for authentication
  - [ ] Middleware for CORS
- [ ] Controllers
  - [ ] HomeController (landing page)
  - [ ] AuthController (login, logout, callbacks)
  - [ ] MapController (CRUD operations)
  - [ ] ApiController (REST API)
  - [ ] ExportController (JSON export)
  - [ ] ImportController (JSON import)
  - [ ] ShareController (public sharing)
- [ ] Views/Templates
  - [ ] Install Twig 3.x
  - [ ] Base layout template
  - [ ] Home page
  - [ ] Login page
  - [ ] Map editor page
  - [ ] My maps list page
  - [ ] Shared map view page
  - [ ] Import preview page

---

## Phase 4: Map Management

- [ ] Map CRUD
  - [ ] Create new map
  - [ ] Load saved map
  - [ ] Update map (auto-save or manual)
  - [ ] Delete map
  - [ ] List user's maps with pagination
  - [ ] Search maps by title
- [ ] Map configuration
  - [ ] Store as JSON in database
  - [ ] Validate configuration schema
  - [ ] Handle coordinates (multiple point sets)
  - [ ] Handle regions
  - [ ] Handle WKT drawings
  - [ ] Handle layers selection
  - [ ] Handle projection selection
  - [ ] Handle options (legend, scalebar, etc.)
- [ ] Map sharing
  - [ ] Generate share token
  - [ ] Public share URL
  - [ ] Remove share
  - [ ] View shared map (read-only)

---

## Phase 5: Render Integration

- [ ] Render client class
  - [ ] HTTP client for render service
  - [ ] Error handling
  - [ ] Timeout configuration
- [ ] Render endpoints
  - [ ] POST /render - proxy to render service
  - [ ] GET /api/map - render with query params
  - [ ] Support all output formats (PNG, JPG, SVG, TIF)
- [ ] Download functionality
  - [ ] Direct image download
  - [ ] PPTX export (PHPPresentation)
  - [ ] DOCX export (PHPWord)
  - [ ] KML export

---

## Phase 6: JSON Import/Export

- [ ] Export functionality
  - [ ] Export all user maps
  - [ ] Export selected maps
  - [ ] JSON schema validation
  - [ ] Versioned format (v1.0)
- [ ] Import functionality
  - [ ] Upload JSON file
  - [ ] Parse and validate
  - [ ] Preview maps before import
  - [ ] Handle title conflicts (skip/replace/rename)
  - [ ] Track imported_at and import_source

---

## Phase 7: REST API

- [ ] API endpoints
  - [ ] GET /api - API documentation
  - [ ] GET /api/ping - Health check
  - [ ] POST /api/render - Render map from JSON
  - [ ] GET /api/projections - List projections
  - [ ] GET /api/layers - List layers
  - [ ] GET /api/shapes - List marker shapes
- [ ] API authentication (optional)
  - [ ] API key generation
  - [ ] Rate limiting
- [ ] API documentation
  - [ ] OpenAPI/Swagger spec
  - [ ] Interactive documentation page

---

## Phase 8: Frontend

- [ ] JavaScript application
  - [ ] Map editor interface
  - [ ] Coordinate input (textareas)
  - [ ] Layer selection checkboxes
  - [ ] Projection dropdown
  - [ ] Marker shape/size/color pickers
  - [ ] Region input
  - [ ] WKT drawing input
  - [ ] Live preview using Leaflet/OpenLayers
  - [ ] Download buttons
  - [ ] Save/Load functionality
- [ ] CSS/Styling
  - [ ] Responsive layout
  - [ ] Form styling
  - [ ] Map preview styling
- [ ] Assets
  - [ ] Minification for production
  - [ ] Cache busting

---

## Phase 9: Testing

- [ ] Unit tests
  - [ ] Database class tests
  - [ ] MapfileGenerator tests
  - [ ] Coordinate parsing tests
- [ ] Integration tests
  - [ ] Render service tests
  - [ ] API endpoint tests
  - [ ] OAuth flow tests (mocked)
- [ ] End-to-end tests
  - [ ] User login flow
  - [ ] Create and save map
  - [ ] Export and import
- [ ] Test infrastructure
  - [ ] PHPUnit configuration
  - [ ] Test database fixtures
  - [ ] CI/CD pipeline (GitHub Actions)

---

## Phase 10: Production Readiness

- [ ] Security
  - [ ] CSRF protection
  - [ ] XSS prevention
  - [ ] SQL injection prevention (already using PDO)
  - [ ] Rate limiting
  - [ ] Security headers
- [ ] Performance
  - [ ] Opcache configuration
  - [ ] Database query optimization
  - [ ] Image caching
- [ ] Monitoring
  - [ ] Error logging
  - [ ] Access logging
  - [ ] Health check endpoints
- [ ] Documentation
  - [ ] User guide
  - [ ] API documentation
  - [ ] Deployment guide
- [ ] Deployment
  - [ ] Production docker-compose
  - [ ] SSL/TLS configuration
  - [ ] Backup automation
  - [ ] Domain configuration

---

## Backlog / Nice to Have

- [ ] Internationalization (i18n)
  - [ ] English
  - [ ] French (from original)
- [ ] Citation management (from original)
  - [ ] Add citations
  - [ ] RSS feed
- [ ] Admin panel
  - [ ] User management
  - [ ] Usage statistics
  - [ ] System health
- [ ] Batch operations
  - [ ] Bulk map export
  - [ ] Bulk map delete
- [ ] Map versioning
  - [ ] Save map versions
  - [ ] Restore previous version
- [ ] Collaboration
  - [ ] Share maps with other users
  - [ ] Team workspaces

---

## Current Status

**Completed:** Phase 1 (Infrastructure)

**Next up:** Phase 2 (Authentication)

To start Phase 2, run:
```bash
# Install OAuth dependencies
docker-compose exec app composer require league/oauth2-client league/oauth2-google

# Then implement OAuth controllers
```

---

## Notes

- Original codebase reference: `/Users/rpage/Sites/simplemappr`
- Architecture doc: `docs/architecture.md`
- The render service is functional but needs shapefiles downloaded
- Database auto-initializes on first connection
