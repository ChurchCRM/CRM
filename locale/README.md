# ChurchCRM Localization System

This directory contains the complete localization infrastructure for ChurchCRM, including POEditor integration, Gettext support, and translation management tools.

## 📁 Directory Structure

```
locale/
├── README.md                       # This documentation
├── JSONKeys/                       # Generated JSON translation files
├── locales/                        # i18next translation files
├── scripts/
│   ├── i18next-parser.config.js    # i18next parser configuration
│   ├── locale-audit.js             # Locale completeness audit script
│   ├── locale-build-db.js          # Database term extraction script (renamed)
│   ├── locale-build-static.js      # Static data (countries/locales) extraction (renamed)
│   └── locale-build.js              # Main term extraction orchestrator (renamed)
├── messages.po                     # Master Gettext template file
└── poeditor-audit.md              # Locale completeness report
```

## 🛠️ Available NPM Scripts

### Locale Management
- `npm run locale:audit` - Generate locale completeness report
- `npm run locale:download` - Download latest translations from POEditor
- `npm run locale:term-extract` - Extract all translatable terms for POEditor upload

### Manual Scripts (require parameters)
- `node locale/locale-add.js` - Add new language support

## 🌐 POEditor Integration

ChurchCRM uses [POEditor](https://poeditor.com) as the primary translation management platform.

### Setup Requirements

1. **Environment Configuration**
   - Copy `.env.example` to `.env` (or `.env.local` for local-only overrides)
   - Set `POEDITOR_TOKEN` from your POEditor API access (https://poeditor.com/account/api)
   - Database credentials default to `localhost/churchcrm/changeme` (works for both local dev and Docker)

2. **.env Configuration**
   ```bash
   # Database (optional - defaults shown)
   DB_HOST=localhost
   DB_PORT=3306
   DB_NAME=churchcrm
   DB_USER=churchcrm
   DB_PASSWORD=changeme
   
   # POEditor (required for locale:download)
   POEDITOR_TOKEN=your_api_token_here
   ```

### POEditor Workflow

1. **Extract Terms**: `npm run locale:build` → Generates `messages.po` with all translatable terms
2. **Upload to POEditor**: Upload `locale/messages.po` to POEditor project
3. **Translate**: Contributors translate terms in POEditor web interface
4. **Download**: `npm run locale:download` downloads completed translations
5. **Deploy**: Translations are converted to runtime formats

### Missing Terms Workflow

For identifying and prioritizing untranslated terms:

1. **Generate Missing Terms**: `npm run locale:missing`
   - Compares POEditor terms against each locale's translated terms
   - Creates JSON files in `locale/terms/missing/{locale}/`
   - Files are batched (default 150 terms per file) for easy POEditor import

2. **Upload to POEditor**: 
   - Go to POEditor → Your Project → Import
   - Select language and upload the JSON files from `locale/terms/missing/{locale}/`
   - POEditor will highlight these as needing translation

3. **Translation Priority**:
   - Files are named `{locale}-1.json`, `{locale}-2.json`, etc.
   - Lower numbers = higher priority (most common terms)
   - Translators should complete batch 1 before moving to batch 2

4. **Download Updates**: After translators complete work, run `npm run locale:download`

### Quick Reference Commands

```bash
# Full translation workflow
npm run locale:build      # Extract all terms from source code
npm run locale:download   # Download translations from POEditor
npm run locale:audit      # Generate completeness report
npm run locale:missing    # Generate missing term files for each locale
```

## 📝 Gettext System

ChurchCRM uses GNU Gettext for internationalization, supporting multiple output formats.

### Supported File Types

- **PHP Files**: Extracted using `xgettext` for PHP
- **JavaScript/React**: Extracted using `i18next-parser`
- **Database**: Custom extraction via `extract-db-locale-terms.js`

### Translation Functions

#### PHP
```php
gettext('Text to translate')
_('Text to translate')
ngettext('singular', 'plural', $count)
```

#### JavaScript/React
```javascript
i18next.t('Text to translate')
t('Text to translate')
```

### Term Extraction Process

The `npm run locale:build` script (`scripts/locale-build.js`) performs a comprehensive extraction:

1. **Database Terms** - Extracts terms from database queries, user configurations, and system data
   - Uses `locale-extract-db.js` with direct MySQL connectivity  
   - Implements deduplication to prevent conflicts
   - Generates 112+ unique database terms with proper context

2. **Static Data** - Extracts countries and locale names for translation
   - Uses `locale-extract-static.js` to generate static terms
   - Pulls authoritative country data from PHP Countries class
   - Includes multilingual country names (e.g., "China (中国)")
   - Generates 297+ static data terms

3. **PHP Source Code** - Extracts gettext calls from PHP files
   - Uses GNU `xgettext` to scan all PHP source files
   - Excludes vendor directories automatically
   - Captures 1,800+ terms from application logic

4. **JavaScript/React** - Extracts i18next translation calls
   - Uses `i18next-parser` with proper npx execution
   - Scans both React (.tsx) and vanilla JS files
   - Generates 97+ JavaScript terms including critical UI elements

5. **File Merging** - Combines all sources into final output
   - Uses `msgcat --use-first --no-wrap` for clean merging
   - Handles duplicate terms with first-occurrence preference
   - Produces final `locale/messages.po` with 2,292+ total terms

**Result**: Complete term coverage ensuring no translatable content is missed.

## 🚀 Adding New Languages

### Automatic Setup
```bash
node locale/locale-add.js --name "Korean" --code "ko" --locale "ko_KR" --country "KR" --datatables "Korean"
```

### Manual Process

1. **Add to Locales Configuration**
   - Edit `src/locale/locales.json`
   - Add language entry with all required fields

2. **Create Directory Structure**
   ```bash
   mkdir -p src/locale/textdomain/ko_KR/LC_MESSAGES
   ```

3. **Generate Translation Files**
   - Run `npm run locale:term-extract` to extract terms
   - Upload `messages.po` to POEditor
   - Add language in POEditor interface

4. **Download and Build**
   ```bash
   npm run locale:download
   ```

## 📊 Translation Status

Run `npm run locale:audit` to generate a comprehensive report showing:
- Translation completeness per language
- Missing locale support
- POEditor project statistics

The report is saved to `locale/poeditor-audit.md` and includes:
- 🟢 Complete (≥95% translated)
- 🟡 Partial (50-94% translated)  
- 🔴 Incomplete (<50% translated)

## 🔧 System Configuration

### Required Dependencies

#### NPM Packages
- `i18next` - JavaScript internationalization
- `i18next-conv` - Format conversion
- `i18next-parser` - Term extraction
- `mysql2` - Database connectivity for term extraction
- `grunt-poeditor-gd` - POEditor integration

#### System Tools
- `xgettext` - GNU Gettext extraction
- `msgcat` - Message catalog merging
- `msgfmt` - Compiled message generation

### Runtime Files

The system generates several runtime files:

#### Vendor Locales
- `src/locale/vendor/datatables/*.json` - DataTables locale files
- `src/locale/vendor/moment/*.js` - Moment.js locale files
- `src/locale/vendor/bootstrap-datepicker/*.js` - DatePicker locale files
- `src/locale/vendor/select2/*.js` - Select2 locale files
- All copied from node_modules during build

#### PHP Gettext
- `src/locale/textdomain/*/LC_MESSAGES/*.mo` - Compiled Gettext files
- Used by PHP `gettext()` functions
- Generated during build process

#### JSON Files
- `src/locale/i18n/*.json` - Translation key mappings
- Used for JavaScript internationalization
- Downloaded from POEditor

## 🐛 Troubleshooting

### Common Issues

1. **Missing Translations in UI**
   - Run `npm run locale:download` to get latest translations
   - Check if language is properly configured in `locales.json`
   - Verify Gettext files exist in `textdomain/`

2. **POEditor Sync Failures**
   - Verify `POEDITOR_TOKEN` is set in `.env` file
   - Check network connectivity to POEditor API
   - Ensure token has proper POEditor API permissions

3. **Term Extraction Errors**
   - Ensure `xgettext` is installed on system
   - Check file permissions in locale directories
   - Verify PHP files use proper translation functions

### Debug Commands

```bash
# Check locale configuration
cat src/locale/locales.json

# Verify extracted terms
head -20 locale/messages.po

# Check generated JavaScript files
ls -la src/locale/vendor/js/

# Validate Gettext compilation
find src/locale/textdomain -name "*.mo"
```

## 📚 Additional Resources

- [POEditor Documentation](https://poeditor.com/docs/)
- [GNU Gettext Manual](https://www.gnu.org/software/gettext/manual/)
- [i18next Documentation](https://www.i18next.com/)
- [ChurchCRM Translation Guide](https://github.com/ChurchCRM/CRM/wiki/Translation)

## 🤝 Contributing Translations

1. **Join POEditor Project**: Contact maintainers for access
2. **Choose Language**: Select from available or request new language
3. **Translate Terms**: Use POEditor web interface for translations
4. **Test Changes**: Download and test translations locally
5. **Submit**: Translations sync automatically to repository

---

For technical support with the localization system, please open an issue on the [ChurchCRM GitHub repository](https://github.com/ChurchCRM/CRM/issues).