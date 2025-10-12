#!/bin/bash

# Get the directory where this script is located
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"

echo "🚀 Starting locale extraction from $PROJECT_ROOT"

cd "$PROJECT_ROOT"

# Extract DB Terms first to create initial messages.po
echo "🗄️  Extracting database terms..."
node locale/scripts/locale-extract-db.js

# Get the temporary directory path from the Node.js script
DB_STRINGS_DIR=$(node locale/scripts/locale-extract-db.js --temp-dir)
echo "Using temporary directory: $DB_STRINGS_DIR"

# Start with database terms as the base
if [ -f "$DB_STRINGS_DIR/database-terms.po" ]; then
    echo "� Starting with database terms..."
    cp "$DB_STRINGS_DIR/database-terms.po" locale/messages.po
else
    echo "⚠️  No database terms file found, creating empty messages.po"
    echo "# ChurchCRM locale file" > locale/messages.po
fi

# Extract PHP Terms and merge
echo "📄 Extracting and merging PHP terms..."
cd src
find . -iname '*.php' | sort | grep -v ./vendor | xargs xgettext --no-location --no-wrap --join-existing --from-code=UTF-8 -o ../locale/messages.po -L PHP

cd "$PROJECT_ROOT"

# Extract JS & React Terms
echo "⚛️  Extracting JavaScript/React terms..."
if command -v i18next &> /dev/null; then
    i18next -c locale/i18next-parser.config.js
    i18next-conv -l en -s locale/locales/en/translation.json -t locale/locales/en/translation.po
    
    # merge PHP & DB & JS Terms only if translation.po was created
    if [ -f "locale/locales/en/translation.po" ]; then
        echo "🔗 Merging all translation files..."
        # Use msgcat with --use-first to avoid conflicts and clean output
        msgcat --use-first --no-wrap locale/messages.po locale/locales/en/translation.po -o locale/messages.po.tmp
        mv locale/messages.po.tmp locale/messages.po
    else
        echo "⚠️  No JavaScript translation file generated"
    fi
    
    # Cleanup
    echo "🧹 Cleaning up temporary files..."
    rm -f locale/locales/en/translation.*
else
    echo "⚠️  i18next not found - skipping JavaScript term extraction"
fi

# Clean up temporary database strings directory
if [ -n "$DB_STRINGS_DIR" ] && [ -d "$DB_STRINGS_DIR" ]; then
    echo "🧹 Cleaning up temporary database strings directory..."
    rm -rf "$DB_STRINGS_DIR"
fi

echo "✅ Locale extraction completed!"
