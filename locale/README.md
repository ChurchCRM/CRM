# ChurchCRM Localization System

This directory contains the complete localization infrastructure for ChurchCRM, including POEditor integration, Gettext support, and translation management tools.

## 📁 Directory Structure

```
locale/
├── README.md                    # This documentation
├── db-strings/                  # Database string extraction (temporary)
├── JSONKeys/                    # Generated JSON translation files
├── locales/                     # i18next translation files
├── extract-db-locale-terms.js  # Database term extraction script
├── i18next-parser.config.js    # i18next parser configuration
├── locale-add.js               # Language setup automation script
├── locale-audit.js             # Locale completeness audit script
├── messages.po                  # Master Gettext template file
├── poeditor-audit.md           # Locale completeness report
└── update-locale.sh            # Term extraction script
```

## 🛠️ Available NPM Scripts

### Locale Management
- `npm run locale:audit` - Generate locale completeness report
- `npm run locale:clean` - Clean all translation files
- `npm run locale:download` - Download latest translations from POEditor
- `npm run locale:gen` - Extract all translatable terms

### Manual Scripts (require parameters)
- `node locale/locale-add.js` - Add new language support

## 🌐 POEditor Integration

ChurchCRM uses [POEditor](https://poeditor.com) as the primary translation management platform.

### Setup Requirements

1. **POEditor Project Configuration**
   - Project ID and API token must be configured in `BuildConfig.json`
   - See `BuildConfig.json.example` for structure

2. **BuildConfig.json Structure**
   ```json
   {
     "POEditor": {
       "id": "YOUR_PROJECT_ID",
       "token": "YOUR_API_TOKEN"
     }
   }
   ```

### POEditor Workflow

1. **Upload Terms**: `locale:gen` → Upload `messages.po` to POEditor
2. **Translate**: Contributors translate terms in POEditor web interface
3. **Download**: `locale:download` downloads completed translations
4. **Deploy**: Translations are converted to runtime formats

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

The `locale:gen` script (`update-locale.sh`) performs:

1. **PHP Term Extraction**
   ```bash
   find . -iname '*.php' | xargs xgettext --from-code=UTF-8 -o ../locale/messages.po
   ```

2. **Database Term Extraction**
   ```bash
   node extract-db-locale-terms.js
   ```

3. **JavaScript Term Extraction**
   ```bash
   i18next -c locale/i18next-parser.config.js
   ```

4. **Term Merging**
   ```bash
   msgcat locale/messages.po locale/locales/en/translation.po -o locale/messages.po
   ```

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
   - Run `npm run locale:gen` to extract terms
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

#### JavaScript Locales
- `src/locale/js/*.js` - Browser-ready translation files
- Generated from POEditor downloads
- Automatically included in builds

#### PHP Gettext
- `src/locale/textdomain/*/LC_MESSAGES/*.mo` - Compiled Gettext files
- Used by PHP `gettext()` functions
- Generated during build process

#### JSON Files
- `locale/JSONKeys/*.json` - Translation key mappings
- Used for JavaScript internationalization
- Generated during locale generation

## 🐛 Troubleshooting

### Common Issues

1. **Missing Translations in UI**
   - Run `npm run locale:download` to get latest translations
   - Check if language is properly configured in `locales.json`
   - Verify Gettext files exist in `textdomain/`

2. **POEditor Sync Failures**
   - Verify `BuildConfig.json` has correct API credentials
   - Check network connectivity to POEditor API
   - Ensure project ID matches your POEditor project

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
ls -la src/locale/js/

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