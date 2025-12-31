# ChurchCRM Development Container

Development container configuration for GitHub Codespaces and VS Code Dev Containers.

## What Happens Automatically

When you open ChurchCRM in a Codespace or Dev Container, the setup automatically:

1. **✓ Checks Out Code** - Full repository cloned to workspace
2. **✓ Installs Tools** - PHP 8.2+, Node.js LTS, Composer, Git LFS, Docker
3. **✓ Installs Dependencies** - `npm ci` (Node packages) + `composer install` (PHP packages)
4. **✓ Builds Frontend** - Webpack, Grunt tasks, locale files via `npm run build:frontend`
5. **✓ Configures Environment** - Creates `docker/.env`, sets up directories
6. **✓ VS Code Extensions** - PHP IntelliSense, Copilot, TypeScript, Xdebug

**Docker services are NOT started** - you control when to start them.

## Quick Start

### GitHub Codespaces
1. Click "Code" → "Codespaces" → "Create codespace on [branch]"
2. Wait 2-3 minutes for automatic setup
3. **Code is ready to edit immediately** - `npm run build` will work
4. When ready to test: `npm run docker:dev:start`
5. Access app at http://localhost (admin/changeme)

### VS Code Dev Containers
1. Install "Dev Containers" extension
2. Open repo in VS Code
3. Click "Reopen in Container"
4. Wait for setup to complete
5. Start Docker when ready: `npm run docker:dev:start`

## What's Ready After Setup

```bash
# These commands work immediately (no Docker required):
npm run build              # ✓ Full build (all dependencies already installed)
npm run build:frontend     # ✓ Rebuild JS/CSS
npm run build:php          # ✓ Update PHP dependencies

# These require Docker to be started first:
npm run docker:dev:start   # Start MariaDB, Apache, MailHog, Adminer
npm run test               # Run Cypress tests
```

## Docker Services

Services available after running `npm run docker:dev:start`:

| Service | Port | Purpose | URL |
|---------|------|---------|-----|
| ChurchCRM Web | 80 | Main application | http://localhost |
| MariaDB | 3306 | Database server | N/A (internal) |
| MailHog UI | 8025 | Email testing | http://localhost:8025 |
| Adminer | 8088 | Database admin | http://localhost:8088 |

Default credentials: `admin` / `changeme`

## Common Tasks

```bash
# Build commands (Docker NOT required):
npm run build              # Full build (PHP + frontend)
npm run build:frontend     # Webpack build of JS/CSS
npm run build:php          # Composer dependency updates
npm run deploy             # Production build with signatures
npm ci                     # Reinstall npm packages

# Docker service management:
npm run docker:dev:start   # Start all services
npm run docker:dev:stop    # Stop all services
npm run docker:dev:logs    # View container logs

# Testing (requires Docker running):
npm run test               # Run all Cypress tests
npm run test:ui            # Interactive test runner
npm run test:api           # API tests only
```

## Configuration

### Environment Variables (`docker/.env`)

The init script creates a default `.env` file. Customize as needed:

```bash
# Database credentials
MYSQL_ROOT_PASSWORD=changeme
MYSQL_USER=churchcrm
MYSQL_PASSWORD=changeme

# Ports (change if you have conflicts)
DEV_WEBSERVER_PORT=80
DEV_DATABASE_PORT=3306
DEV_MAILSERVER_GUI_PORT=8025
DEV_ADMINER_PORT=8088

# XDebug for PHP debugging
XDEBUG_MODE=debug
XDEBUG_CONFIG=client_host=host.docker.internal client_port=9003
```

## Troubleshooting

### Build Issues
- **"npm ci failed"**: Check Node.js version (must be LTS), retry
- **"composer install failed"**: Verify PHP 8.2+ is available: `php -v`
- **"Grunt task failed"**: Ensure all npm packages installed: `npm ci`

### Docker Issues
- **Services won't start**: Check Docker is running: `docker ps`
- **Port conflicts**: Change ports in `docker/.env`, restart services
- **Database won't start**: Check logs: `npm run docker:dev:logs`

### Permission Issues
- **"Cannot write to src/logs"**: Run `chmod -R 777 src/logs`
- **"Composer cache error"**: Run `cd src && composer clear-cache`

### Code/Build Issues
- **"Cannot find module"**: Re-run `npm ci` and `npm run build:frontend`
- **"Class not found (PHP)"**: Re-run `cd src && composer install`
- **"Locale files missing"**: Run `npm run postinstall`

## Development Workflow

### Typical Development Session

```bash
# 1. Open Codespace (auto-setup runs)
# 2. Wait for "Setup Complete!" message
# 3. Verify build works:
npm run build

# 4. Start Docker when needed:
npm run docker:dev:start

# 5. Make code changes...
# 6. Rebuild as needed:
npm run build:frontend

# 7. Test changes:
npm run test

# 8. Stop Docker before closing:
npm run docker:dev:stop
```

### Working Without Docker

You can develop without Docker by:
- Editing code and running builds
- Testing JavaScript/TypeScript in isolation
- Running Composer/npm commands
- Using external database for PHP testing

Docker is only required for:
- Full application testing
- Cypress integration tests
- Email testing (MailHog)
- Database UI (Adminer)

## What the Setup Actually Does

The `postCreateCommand` in `devcontainer.json` runs `.devcontainer/init.sh` which:

1. Verifies code is checked out (`src/` directory exists)
2. Creates `docker/.env` from template
3. Creates required directories (`src/logs`, `src/vendor`, `src/locale/textdomain`)
4. Installs Git LFS
5. Runs `npm ci` (installs Node packages + runs postinstall hook for locale files)
6. Runs `composer install --no-dev` in `src/`
7. Runs `npm run build:frontend` (Grunt + Webpack + Prettier)

After this, you can immediately run `npm run build` or any build command.

## For More Information

- **Contributing Guide**: ../CONTRIBUTING.md
- **Project README**: ../README.md
- **Docker Setup**: ../docker/README.md
- **Cypress Testing**: ../cypress/README.md (if exists)

---

*Last Updated: November 2025*
