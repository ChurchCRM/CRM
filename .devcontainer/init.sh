#!/bin/bash

# Initialize development environment for GitHub Codespaces
echo "🚀 Initializing ChurchCRM development environment..."

# Create .env file if it doesn't exist
if [ ! -f "docker/.env" ]; then
    echo "📄 Creating docker/.env file..."
    cp docker/.env docker/.env.backup 2>/dev/null || true
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
XDEBUG_MODE="debug"
XDEBUG_CONFIG="client_host=host.docker.internal client_port=9003"
EOF
fi

# Ensure logs directory exists with proper permissions
mkdir -p src/logs
chmod -R 755 src/logs

echo "✅ Environment initialization complete"