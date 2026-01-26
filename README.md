# CloudFlare R2 Offload CDN

A production-ready WordPress plugin for offloading media and static assets to Cloudflare R2 with automatic CDN delivery. Built with modern standards (PSR-4 autoloading, WordPress Coding Standards, Vite for assets), this plugin provides enterprise-grade security and extensibility.

## Requirements

- WordPress 6.0+
- PHP 7.4+
- Composer
- Node.js 18+
- Docker (optional)

## Features (v2.0)

### Core Capabilities
- **R2 Media Offload**: Automatically upload media to Cloudflare R2 with queue-based processing
- **CDN Image Optimization**: WebP/AVIF conversion, responsive images with automatic breakpoints
- **Worker Auto-Deploy**: One-click Cloudflare Worker deployment via API
- **Cost Tracking**: Monitor image transformation usage with monthly summaries
- **Bulk Operations**: Offload or restore multiple media files at once

### Integrations
- **WooCommerce**: Product images automatically served via CDN
- **Gutenberg**: Native support for image blocks with CDN URLs
- **REST API**: Full REST endpoints for programmatic access

### Admin Features
- **Tabbed Admin UI**: Dashboard, General, Advanced, Integrations tabs
- **Real-time Stats**: Track transformations, bandwidth, and costs
- **AJAX Settings Save**: No page refresh, toast notifications
- **Media Library Extension**: Offload status column and row actions

### Security & Quality
- **Enterprise Security**: Nonce verification, rate limiting (10/min), capability checks, encryption
- **Production Ready**: PSR-4 autoloading, 70%+ test coverage, WordPress Coding Standards compliance
- **Build Tools**: Vite for assets, build.sh for ZIP/SVN deployment
- **Docker Support**: Isolated local environment with random ports
- **Extensible Architecture**: Hooks, filters, clean separation of concerns

## Quick Setup

1. **Configure R2**: Enter R2 credentials in Settings > Advanced tab
2. **Test Connection**: Click "Test Connection" to verify credentials
3. **Add API Token**: Enter Cloudflare API Token for Worker deployment
4. **Deploy Worker**: Click "Deploy Worker" to enable CDN transformation
5. **Enable CDN**: Turn on CDN in Settings > General tab
6. **Bulk Offload**: Use Media Library to migrate existing media files

## Quick Start (Development)

```bash
# Initialize project
./scripts/init.sh

# Install dependencies
composer install
npm install

# Build assets
npm run build

# Start local environment (optional)
docker-compose up -d
```

**Access**: Dashboard > CloudFlare R2 Offload My Plugin CDN

## Development Workflow

### Docker Environment

```bash
docker-compose up -d      # Start services
docker-compose down       # Stop services
docker-compose logs -f    # View logs
```

Ports are auto-generated in `.env` by init.sh to avoid conflicts.

### Build Commands

```bash
./scripts/build.sh build       # Build to dist/
./scripts/build.sh zip         # Create ZIP archive
./scripts/build.sh deploy-svn  # Deploy to SVN structure
./scripts/build.sh version X.X # Bump version
./scripts/build.sh clean       # Clean outputs
```

### Code Quality

```bash
composer phpcs          # Check WordPress Coding Standards
composer phpcbf         # Auto-fix style issues
composer test           # Run PHPUnit tests
```

### Assets

```bash
npm run dev             # Watch mode (development)
npm run build           # Production build (minified)
```

## REST API

### Endpoints

**Get Attachment Status**
```bash
GET /wp-json/cfr2/v1/status/{id}
```
Returns offload status, R2 URL, and metadata for an attachment.

**Trigger Offload**
```bash
POST /wp-json/cfr2/v1/offload/{id}
```
Queue attachment for offload. Requires authentication.

**Get Usage Stats**
```bash
GET /wp-json/cfr2/v1/stats?period=month
```
Returns transformation counts and bandwidth usage.

**Bulk Offload**
```bash
POST /wp-json/cfr2/v1/bulk-offload
Body: {"ids": [1, 2, 3]}
```
Queue multiple attachments. Requires admin permission.

## Project Structure

```
src/
├── Admin/              # Admin interface
│   ├── AdminMenu.php   # Menu registration + AJAX handler
│   ├── SettingsPage.php # Tabbed UI renderer
│   └── Tabs/           # Tab components
├── Services/           # Core services
│   ├── R2Client.php    # R2 operations
│   ├── URLRewriter.php # CDN URL rewriting
│   ├── OffloadService.php # Offload workflow
│   ├── StatsTracker.php # Usage tracking
│   └── WorkerDeployer.php # Worker deployment
├── Integrations/       # Third-party integrations
├── Core/               # Core functionality
├── PublicSide/         # Frontend features
├── Interfaces/         # Contracts
└── Traits/             # Shared behavior
```

## Documentation

- **[Project Overview & PDR](docs/project-overview-pdr.md)** - Project goals and requirements
- **[Codebase Summary](docs/codebase-summary.md)** - Code structure and organization
- **[Code Standards](docs/code-standards.md)** - Coding guidelines and best practices
- **[System Architecture](docs/system-architecture.md)** - Architecture patterns and data flow
- **[Project Roadmap](docs/project-roadmap.md)** - Upcoming phases and features
- **[Deployment Guide](docs/deployment-guide.md)** - Build and deployment procedures

## License

GPL-2.0+
