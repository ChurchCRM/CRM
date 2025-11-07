#!/bin/bash
set -e

echo "🚀 ChurchCRM Development Environment Setup"
echo "=========================================="

# Check prerequisites
echo "📋 Checking prerequisites..."

if ! command -v docker &> /dev/null; then
    echo "❌ Docker is required but not installed"
    exit 1
fi

if ! command -v npm &> /dev/null; then
    echo "❌ Node.js/npm is required but not installed"
    exit 1
fi

echo "✅ Prerequisites met"

# Install Node dependencies
echo "📦 Installing Node.js dependencies..."
npm ci

# Initialize Git LFS if available
if command -v git-lfs &> /dev/null; then
    echo "🔧 Initializing Git LFS..."
    git lfs install --local
else
    echo "⚠️  Git LFS not found - install with: sudo apk add git-lfs (Alpine) or sudo apt-get install git-lfs (Ubuntu)"
fi

# Set up Docker containers
echo "🐳 Starting Docker containers..."
npm run docker:dev:start

# Wait for containers to start
echo "⏳ Waiting for containers to initialize..."
sleep 15

# Build inside the dev container (which has all PHP extensions and tools)
echo "🔨 Building ChurchCRM inside dev container..."
docker compose -f docker/docker-compose.yaml --profile dev exec webserver-dev bash -c "
  source /root/.nvm/nvm.sh && 
  cd /home/ChurchCRM && 
  npm ci && 
  npm run deploy
"

# Wait for services to be ready
echo "⏳ Waiting for services to initialize..."
sleep 10

# Test if the API is responding
echo "🧪 Testing API connectivity..."
max_attempts=12
attempt=1
until curl -f http://127.0.0.1/api/public/echo &>/dev/null || [ $attempt -ge $max_attempts ]; do
  echo "   Attempt $attempt/$max_attempts - waiting for server..."
  sleep 5
  attempt=$((attempt + 1))
done

if [ $attempt -ge $max_attempts ]; then
  echo "⚠️  Server may still be starting up. Check logs with: npm run docker:dev:logs"
else
  echo "✅ API is responding"
fi

# Make logs directory writable
echo "📝 Setting up log permissions..."
mkdir -p src/logs
chmod a+rwx src/logs

# Final status
echo ""
echo "🎉 Setup Complete!"
echo "=================="
echo "🌐 ChurchCRM Web:    http://localhost"
echo "🔑 Admin Login:      admin / changeme"
echo "📧 MailHog UI:       http://localhost:8025"
echo "🗄️  Adminer DB UI:    http://localhost:8088"
echo "   └─ DB Login:      churchcrm / changeme"
echo ""
echo "💡 Quick Commands:"
echo "   • View logs:      npm run docker:dev:logs"
echo "   • Run tests:      npm run test"
echo "   • Stop Docker:    npm run docker:dev:stop"
echo "   • Rebuild:        npm run build:frontend"
echo ""
echo "📖 For more info, see CONTRIBUTING.md"