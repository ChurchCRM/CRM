#!/bin/bash

# Parallel Testing Demonstration Script
# This script demonstrates how to run both root and subdirectory tests in parallel

set -e

echo "🚀 ChurchCRM Parallel Testing Demo"
echo "===================================="
echo ""

# Colors for output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to cleanup on exit
cleanup() {
    echo ""
    echo -e "${YELLOW}🧹 Cleaning up...${NC}"
    npm run docker:ci:root:down 2>/dev/null || true
    npm run docker:ci:subdir:down 2>/dev/null || true
}

trap cleanup EXIT

echo -e "${BLUE}Step 1: Starting Root Path Environment${NC}"
echo "Running on http://localhost:80"
npm run docker:ci:root:start &
ROOT_PID=$!

echo ""
echo -e "${BLUE}Step 2: Starting Subdirectory Environment${NC}"
echo "Running on http://localhost:8080/churchcrm"
npm run docker:ci:subdir:start &
SUBDIR_PID=$!

# Wait for both to complete
wait $ROOT_PID
wait $SUBDIR_PID

echo ""
echo -e "${GREEN}✅ Both environments started successfully!${NC}"
echo ""

# Wait for services to be ready
echo -e "${BLUE}Step 3: Waiting for services to be ready...${NC}"
sleep 15

# Test root path endpoint
echo ""
echo -e "${BLUE}Step 4: Testing Root Path Server${NC}"
max_attempts=12
attempt=1
until curl -f http://127.0.0.1/api/public/echo 2>/dev/null || [ $attempt -eq $max_attempts ]; do
    echo "Attempt $attempt/$max_attempts..."
    sleep 3
    attempt=$((attempt + 1))
done

if [ $attempt -eq $max_attempts ]; then
    echo -e "${YELLOW}❌ Root path server not responding${NC}"
    exit 1
fi
echo -e "${GREEN}✅ Root path server is ready!${NC}"

# Test subdirectory endpoint
echo ""
echo -e "${BLUE}Step 5: Testing Subdirectory Server${NC}"
attempt=1
until curl -f http://127.0.0.1:8080/churchcrm/api/public/echo 2>/dev/null || [ $attempt -eq $max_attempts ]; do
    echo "Attempt $attempt/$max_attempts..."
    sleep 3
    attempt=$((attempt + 1))
done

if [ $attempt -eq $max_attempts ]; then
    echo -e "${YELLOW}❌ Subdirectory server not responding${NC}"
    exit 1
fi
echo -e "${GREEN}✅ Subdirectory server is ready!${NC}"

echo ""
echo "================================================================"
echo -e "${GREEN}🎉 Both environments are running in parallel!${NC}"
echo "================================================================"
echo ""
echo "Root Path:       http://localhost:80"
echo "Subdirectory:    http://localhost:8080/churchcrm"
echo ""
echo "To run tests in parallel:"
echo "  Terminal 1: npm run test:root"
echo "  Terminal 2: npm run test:subdir"
echo ""
echo "Press Ctrl+C to stop both environments"
echo ""

# Keep script running until user interrupts
read -r -p "Press Enter to stop and cleanup..."
