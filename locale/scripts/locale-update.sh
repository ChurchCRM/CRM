#!/bin/bash

# Get the directory where this script is located
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"

echo "🚀 Starting locale extraction from $PROJECT_ROOT"

cd "$PROJECT_ROOT"

# Extract PHP Terms
echo "📄 Extracting PHP terms..."
cd src
find . -iname '*.php' | sort | grep -v ./vendor | xargs xgettext --no-location --no-wrap --from-code=UTF-8 -o ../locale/messages.po -L PHP

cd "$PROJECT_ROOT/locale"

# Extract DB Terms
echo "🗄️  Extracting database terms..."
node scripts/locale-extract-db.js

cd db-strings
find . -iname "*.php" | sort | xargs xgettext --no-location --no-wrap --join-existing --from-code=UTF-8 -o ../messages.po

cd "$PROJECT_ROOT"

# Extract JS & React Terms
echo "⚛️  Extracting JavaScript/React terms..."
if command -v i18next &> /dev/null; then
    i18next -c locale/i18next-parser.config.js
    i18next-conv -l en -s locale/locales/en/translation.json -t locale/locales/en/translation.po
    
    # merge PHP & DB & JS Terms
    echo "🔗 Merging all translation files..."
    msgcat locale/messages.po locale/locales/en/translation.po -o locale/messages.po
    
    # Cleanup
    echo "🧹 Cleaning up temporary files..."
    rm -f locale/locales/en/translation.*
else
    echo "⚠️  i18next not found - skipping JavaScript term extraction"
fi

rm -f locale/db-strings/*

echo "✅ Locale extraction completed!"
