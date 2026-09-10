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
#
# Metadata JSON sidecars (playwright/artifacts/metadata/) are deliberately
# NOT published — nothing in either destination repo reads them. Hugo can't
# load JSON sidecars per-image at template time without extra plumbing, so
# screenshots/single.html hand-copies each shot's title/purpose instead
# (see that file's own comment). They stay CRM-local for pipeline
# debugging only.
# ─────────────────────────────────────────────────────────────
echo "📁 Publishing to Marketing repo..."
mkdir -p "$MARKETING_DIR/assets/screenshots"
mkdir -p "$MARKETING_DIR/assets/videos"

if [ -d "$ARTIFACTS_DIR/screenshots" ]; then
  cp -r "$ARTIFACTS_DIR/screenshots"/* "$MARKETING_DIR/assets/screenshots/" 2>/dev/null || true
  echo "   ✓ Screenshots → assets/screenshots/"
fi

if [ -d "$ARTIFACTS_DIR/videos" ]; then
  cp -r "$ARTIFACTS_DIR/videos"/* "$MARKETING_DIR/assets/videos/" 2>/dev/null || true
  echo "   ✓ Videos → assets/videos/"
fi

# ─────────────────────────────────────────────────────────────
# ChurchCRM.io documentation: static/images/screenshots, static/images/videos
# Hugo's staticDir defaults to static/ (no override in hugo.toml) — anything
# outside static/ is never copied into public/ at build time and never
# ships to the live site. See README.md "Screenshots are stored in
# static/images/ and static/images/screenshots/".
# ─────────────────────────────────────────────────────────────
echo ""
echo "📁 Publishing to ChurchCRM.io docs..."
mkdir -p "$CHURCHCRM_IO_DIR/static/images/screenshots"
mkdir -p "$CHURCHCRM_IO_DIR/static/images/videos"

if [ -d "$ARTIFACTS_DIR/screenshots" ]; then
  cp -r "$ARTIFACTS_DIR/screenshots"/* "$CHURCHCRM_IO_DIR/static/images/screenshots/" 2>/dev/null || true
  echo "   ✓ Screenshots → static/images/screenshots/"
fi

if [ -d "$ARTIFACTS_DIR/videos" ]; then
  cp -r "$ARTIFACTS_DIR/videos"/* "$CHURCHCRM_IO_DIR/static/images/videos/" 2>/dev/null || true
  echo "   ✓ Videos → static/images/videos/"
fi

echo ""
echo "✅ Marketing visuals published to both repositories!"
echo ""
echo "Next steps:"
echo "   Marketing repo:"
echo "     1. cd $MARKETING_DIR"
echo "     2. git add assets/screenshots assets/videos"
echo "     3. git commit -m 'chore: update marketing visuals from CRM'"
echo "     4. git push"
echo ""
echo "   ChurchCRM.io docs:"
echo "     1. cd $CHURCHCRM_IO_DIR"
echo "     2. git add static/images/screenshots static/images/videos"
echo "     3. git commit -m 'chore: update product screenshots and videos'"
echo "     4. git push"
