#!/bin/bash

# Post-start script for ChurchCRM development environment
# This runs after the container is fully started

echo "🔧 Running post-start setup..."

# Ensure Node.js is available in PATH
if ! command -v node &> /dev/null; then
    echo "📦 Setting up Node.js PATH..."
    source /root/.nvm/nvm.sh
    nvm use --lts
fi

# Ensure proper permissions
chmod -R 755 /home/ChurchCRM/src/logs 2>/dev/null || true

# Check if services are ready
echo "🏥 Checking service health..."
timeout 30 bash -c 'until curl -s http://localhost/api/public/echo > /dev/null; do sleep 2; done' || echo "⚠️ Web service may take a moment to start"

echo "✅ Post-start setup complete"