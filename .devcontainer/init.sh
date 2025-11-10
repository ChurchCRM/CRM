#!/bin/bash
set -e

echo ""
echo "========================================================================"
echo "ChurchCRM Development Environment Setup"
echo "========================================================================"
echo ""

# Verify we're in the correct directory
if [ ! -f "package.json" ]; then
    echo "ERROR: Not in ChurchCRM root directory"
    exit 1
fi

# Ensure src directory exists (code must be checked out)
if [ ! -d "src" ]; then
    echo "ERROR: src directory not found!"
    echo "Current directory: $(pwd)"
    ls -la
    exit 1
fi

echo "✓ Source code verified"

# Create .env file if it doesn't exist
if [ ! -f "docker/.env" ]; then
    echo "Creating docker/.env file..."
    mkdir -p docker
    cat > docker/.env << 'EOF'
# ChurchCRM Development Environment Configuration
MYSQL_DB_HOST=database
MYSQL_ROOT_PASSWORD=changeme
MYSQL_DATABASE=churchcrm
MYSQL_USER=churchcrm
MYSQL_PASSWORD=changeme

# Development server ports
DEV_WEBSERVER_PORT=80
DEV_DATABASE_PORT=3306
DEV_MAILSERVER_PORT=1025
DEV_MAILSERVER_GUI_PORT=8025
DEV_ADMINER_PORT=8088

# Debug configuration
XDEBUG_MODE=debug
XDEBUG_CONFIG=client_host=host.docker.internal client_port=9003
EOF
    echo "✓ Created docker/.env"
else
    echo "✓ docker/.env already exists"
fi

# Ensure required directories exist with proper permissions
echo "Setting up directories..."
mkdir -p src/logs src/vendor src/locale/textdomain
chmod -R 777 src/logs 2>/dev/null || sudo chmod -R 777 src/logs || true
echo "✓ Directories prepared"

# Install Git LFS
echo "Installing Git LFS..."
git lfs install
echo "✓ Git LFS installed"

# Install Node.js dependencies
echo "Installing Node.js dependencies (this may take a few minutes)..."
npm ci
echo "✓ npm dependencies installed"

# The postinstall hook runs automatically after npm ci
# It executes: grunt genLocaleJSFiles
echo "✓ Locale files generated (via postinstall)"

# Install PHP dependencies with Composer
echo "Installing PHP dependencies (Composer)..."
cd src
if ! composer validate --no-check-publish 2>/dev/null; then
    echo "WARNING: composer.json validation warning (non-critical)"
fi
# Use composer update on first setup to sync lock file with composer.json
composer update --no-dev --no-interaction --prefer-dist
cd ..
echo "✓ Composer dependencies installed"

# Build frontend assets (Webpack + Grunt tasks)
echo "Building frontend assets..."
echo "  - Running Grunt tasks (curl-dir, copy, patchDataTablesCSS)..."
echo "  - Running Webpack build..."
echo "  - Formatting code with Prettier..."
npm run build:frontend
echo "✓ Frontend assets built"

echo ""
echo "========================================================================"
echo "Setup Complete!"
echo "========================================================================"
echo ""
echo "Verification:"
echo "  ✓ Code checked out"
echo "  ✓ npm dependencies installed"
echo "  ✓ Composer dependencies installed"
echo "  ✓ Frontend assets built"
echo "  ✓ Locale files generated"
echo "  ✓ Environment configured"
echo ""
echo "Available Commands:"
echo ""
echo "  npm run build           - Full build (PHP + frontend)"
echo "  npm run build:frontend  - Rebuild JS/CSS only"
echo "  npm run build:php       - Rebuild PHP dependencies"
echo "  npm run deploy          - Production build with signatures"
echo ""
echo "  npm run docker:dev:start  - Start Docker services"
echo "  npm run docker:dev:stop   - Stop Docker services"
echo "  npm run docker:dev:logs   - View container logs"
echo ""
echo "  npm run test            - Run Cypress tests (requires Docker)"
echo "  npm run test:ui         - Interactive test runner"
echo ""
echo "Docker Services (start when ready):"
echo ""
echo "  1. Review docker/.env configuration if needed"
echo "  2. Start services: npm run docker:dev:start"
echo "  3. Access:"
echo "     Web: http://localhost (admin/changeme)"
echo "     MailHog: http://localhost:8025"
echo "     Adminer: http://localhost:8088"
echo ""
echo "Documentation: see CONTRIBUTING.md"
echo ""
