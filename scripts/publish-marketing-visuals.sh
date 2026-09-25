#!/bin/bash
#
# Publish marketing visuals to the churchcrm.io repository
# Copies screenshots and videos from CRM artifacts to the canonical website
# Usage: npm run publish:visuals or ./scripts/publish-marketing-visuals.sh

set -e

CRM_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CHURCHCRM_IO_DIR="$(dirname "$CRM_DIR")/ChurchCRM.io"
ARTIFACTS_DIR="$CRM_DIR/playwright/artifacts"

# Verify source artifacts exist
if [ ! -d "$ARTIFACTS_DIR" ]; then
  echo "❌ Error: No artifacts directory found at $ARTIFACTS_DIR"
  echo "   Run 'npm run marketing' first to generate screenshots and videos"
  exit 1
fi

# Verify destination directory exists
if [ ! -d "$CHURCHCRM_IO_DIR" ]; then
  echo "❌ Error: ChurchCRM.io directory not found at $CHURCHCRM_IO_DIR"
  echo "   Expected churchcrm.io repo at same level as CRM directory"
  exit 1
fi

echo "📸 Publishing marketing visuals to the canonical website repo..."
echo "   Source: $ARTIFACTS_DIR"
echo ""

# ─────────────────────────────────────────────────────────────
# ChurchCRM.io is the single canonical store for published screenshots
# and videos — every other consumer (marketing content, docs, social)
# links the live https://churchcrm.io/images/screenshots/... URL rather
# than holding its own copy of the binaries. Hugo's staticDir defaults
# to static/ (no override in hugo.toml) — anything outside static/ is
# never copied into public/ at build time and never ships to the live
# site. See README.md "Screenshots are stored in static/images/ and
# static/images/screenshots/".
#
# manifest.json and screenshot-metadata.json are published to data/ so
# the website can load screenshot metadata dynamically without manual
# template updates. Per-image metadata JSON sidecars (metadata/*.json)
# stay CRM-local for pipeline debugging only.
# ─────────────────────────────────────────────────────────────
echo "📁 Publishing to ChurchCRM.io..."
mkdir -p "$CHURCHCRM_IO_DIR/static/images/screenshots"
mkdir -p "$CHURCHCRM_IO_DIR/static/images/videos"
mkdir -p "$CHURCHCRM_IO_DIR/data"

# Copy manifest and metadata for dynamic gallery loading
if [ -f "$ARTIFACTS_DIR/manifest.json" ]; then
  cp "$ARTIFACTS_DIR/manifest.json" "$CHURCHCRM_IO_DIR/data/manifest.json"
  echo "   ✓ Manifest → data/manifest.json"
fi

if [ -f "$CRM_DIR/playwright/screenshot-metadata.json" ]; then
  cp "$CRM_DIR/playwright/screenshot-metadata.json" "$CHURCHCRM_IO_DIR/data/screenshot-metadata.json"
  echo "   ✓ Metadata → data/screenshot-metadata.json"
fi

if [ -d "$ARTIFACTS_DIR/screenshots" ]; then
  cp -r "$ARTIFACTS_DIR/screenshots"/* "$CHURCHCRM_IO_DIR/static/images/screenshots/" 2>/dev/null || true
  echo "   ✓ Screenshots → static/images/screenshots/"
fi

if [ -d "$ARTIFACTS_DIR/videos" ]; then
  cp -r "$ARTIFACTS_DIR/videos"/* "$CHURCHCRM_IO_DIR/static/images/videos/" 2>/dev/null || true
  echo "   ✓ Videos → static/images/videos/"
fi

echo ""
echo "✅ Marketing visuals published to ChurchCRM.io!"
echo ""
echo "Next steps:"
echo "   cd $CHURCHCRM_IO_DIR"
echo "   git add data/manifest.json data/screenshot-metadata.json static/images/screenshots static/images/videos"
echo "   git commit -m 'chore: update product screenshots and videos'"
echo "   git push"
echo ""
echo "The manifest.json enables dynamic screenshot gallery loading."
echo "Reference images from marketing/docs content as:"
echo "   https://churchcrm.io/images/screenshots/<device>/<name>.png"
