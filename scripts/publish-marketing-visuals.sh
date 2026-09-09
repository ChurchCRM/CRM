#!/bin/bash
#
# Publish marketing visuals to both marketing and churchcrm.io repositories
# Copies screenshots and videos from CRM artifacts to both documentation sites
# Usage: npm run publish:visuals or ./scripts/publish-marketing-visuals.sh

set -e

CRM_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
MARKETING_DIR="$(dirname "$CRM_DIR")/marketing"
CHURCHCRM_IO_DIR="$(dirname "$CRM_DIR")/ChurchCRM.io"
ARTIFACTS_DIR="$CRM_DIR/playwright/artifacts"

# Verify source artifacts exist
if [ ! -d "$ARTIFACTS_DIR" ]; then
  echo "❌ Error: No artifacts directory found at $ARTIFACTS_DIR"
  echo "   Run 'npm run marketing' first to generate screenshots and videos"
  exit 1
fi

# Verify destination directories exist
if [ ! -d "$MARKETING_DIR" ]; then
  echo "❌ Error: Marketing directory not found at $MARKETING_DIR"
  echo "   Expected marketing repo at same level as CRM directory"
  exit 1
fi

if [ ! -d "$CHURCHCRM_IO_DIR" ]; then
  echo "❌ Error: ChurchCRM.io directory not found at $CHURCHCRM_IO_DIR"
  echo "   Expected churchcrm.io repo at same level as CRM directory"
  exit 1
fi

echo "📸 Publishing marketing visuals to both repositories..."
echo "   Source: $ARTIFACTS_DIR"
echo ""

# ─────────────────────────────────────────────────────────────
# Marketing repository: assets/screenshots, assets/videos
# ─────────────────────────────────────────────────────────────
echo "📁 Publishing to Marketing repo..."
mkdir -p "$MARKETING_DIR/assets/screenshots"
mkdir -p "$MARKETING_DIR/assets/videos"
mkdir -p "$MARKETING_DIR/assets/metadata"

if [ -d "$ARTIFACTS_DIR/screenshots" ]; then
  cp -r "$ARTIFACTS_DIR/screenshots"/* "$MARKETING_DIR/assets/screenshots/" 2>/dev/null || true
  echo "   ✓ Screenshots → assets/screenshots/"
fi

if [ -d "$ARTIFACTS_DIR/videos" ]; then
  cp -r "$ARTIFACTS_DIR/videos"/* "$MARKETING_DIR/assets/videos/" 2>/dev/null || true
  echo "   ✓ Videos → assets/videos/"
fi

if [ -d "$ARTIFACTS_DIR/metadata" ]; then
  cp -r "$ARTIFACTS_DIR/metadata"/* "$MARKETING_DIR/assets/metadata/" 2>/dev/null || true
  echo "   ✓ Metadata → assets/metadata/"
fi

# ─────────────────────────────────────────────────────────────
# ChurchCRM.io documentation: images/screenshots, images/videos
# ─────────────────────────────────────────────────────────────
echo ""
echo "📁 Publishing to ChurchCRM.io docs..."
mkdir -p "$CHURCHCRM_IO_DIR/images/screenshots"
mkdir -p "$CHURCHCRM_IO_DIR/images/videos"
mkdir -p "$CHURCHCRM_IO_DIR/images/metadata"

if [ -d "$ARTIFACTS_DIR/screenshots" ]; then
  cp -r "$ARTIFACTS_DIR/screenshots"/* "$CHURCHCRM_IO_DIR/images/screenshots/" 2>/dev/null || true
  echo "   ✓ Screenshots → images/screenshots/"
fi

if [ -d "$ARTIFACTS_DIR/videos" ]; then
  cp -r "$ARTIFACTS_DIR/videos"/* "$CHURCHCRM_IO_DIR/images/videos/" 2>/dev/null || true
  echo "   ✓ Videos → images/videos/"
fi

if [ -d "$ARTIFACTS_DIR/metadata" ]; then
  cp -r "$ARTIFACTS_DIR/metadata"/* "$CHURCHCRM_IO_DIR/images/metadata/" 2>/dev/null || true
  echo "   ✓ Metadata → images/metadata/"
fi

echo ""
echo "✅ Marketing visuals published to both repositories!"
echo ""
echo "Next steps:"
echo "   Marketing repo:"
echo "     1. cd $MARKETING_DIR"
echo "     2. git add assets/screenshots assets/videos assets/metadata"
echo "     3. git commit -m 'chore: update marketing visuals from CRM'"
echo "     4. git push"
echo ""
echo "   ChurchCRM.io docs:"
echo "     1. cd $CHURCHCRM_IO_DIR"
echo "     2. git add images/screenshots images/videos images/metadata"
echo "     3. git commit -m 'chore: update product screenshots and videos'"
echo "     4. git push"
