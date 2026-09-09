#!/bin/bash
#
# Publish marketing visuals to the marketing repository
# Moves screenshots and videos from CRM artifacts to marketing directory
# Usage: npm run publish:visuals or ./scripts/publish-marketing-visuals.sh

set -e

CRM_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
MARKETING_DIR="$(dirname "$CRM_DIR")/marketing"
ARTIFACTS_DIR="$CRM_DIR/playwright/artifacts"

# Verify directories exist
if [ ! -d "$ARTIFACTS_DIR" ]; then
  echo "❌ Error: No artifacts directory found at $ARTIFACTS_DIR"
  echo "   Run 'npm run marketing' first to generate screenshots and videos"
  exit 1
fi

if [ ! -d "$MARKETING_DIR" ]; then
  echo "❌ Error: Marketing directory not found at $MARKETING_DIR"
  echo "   Expected marketing repo at same level as CRM directory"
  exit 1
fi

# Create target directories
mkdir -p "$MARKETING_DIR/static/screenshots"
mkdir -p "$MARKETING_DIR/static/videos"
mkdir -p "$MARKETING_DIR/static/metadata"

echo "📸 Publishing marketing visuals..."
echo "   From: $ARTIFACTS_DIR"
echo "   To:   $MARKETING_DIR"

# Copy screenshots
if [ -d "$ARTIFACTS_DIR/screenshots" ]; then
  echo "   Copying screenshots..."
  cp -r "$ARTIFACTS_DIR/screenshots"/* "$MARKETING_DIR/static/screenshots/" 2>/dev/null || true
  echo "   ✓ Screenshots copied"
fi

# Copy videos
if [ -d "$ARTIFACTS_DIR/videos" ]; then
  echo "   Copying videos..."
  cp -r "$ARTIFACTS_DIR/videos"/* "$MARKETING_DIR/static/videos/" 2>/dev/null || true
  echo "   ✓ Videos copied"
fi

# Copy metadata
if [ -d "$ARTIFACTS_DIR/metadata" ]; then
  echo "   Copying metadata..."
  cp -r "$ARTIFACTS_DIR/metadata"/* "$MARKETING_DIR/static/metadata/" 2>/dev/null || true
  echo "   ✓ Metadata copied"
fi

echo ""
echo "✅ Marketing visuals published successfully!"
echo ""
echo "📁 Directory structure:"
echo "   static/screenshots/ — All device form factors"
echo "   static/videos/      — Setup workflow recordings"
echo "   static/metadata/    — Asset metadata"
echo ""
echo "Next steps:"
echo "   1. cd $MARKETING_DIR"
echo "   2. git add static/"
echo "   3. git commit -m 'chore: update marketing visuals'"
echo "   4. git push"
