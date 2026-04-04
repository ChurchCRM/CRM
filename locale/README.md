# ChurchCRM Localization System

This directory contains all localization tools and workflows for ChurchCRM — from term extraction to POEditor management to translation downloads.

## 📋 Quick Start

### I want to...

- **Add a new language**: `node locale/scripts/locale-add.js --name "Korean" --code "ko" --locale "ko_KR" --country "KR"`
- **Extract new terms**: `npm run locale:build`
- **Download translations**: `npm run locale:download` (auto-runs audit when complete)
- **Check translation status**: `npm run locale:audit`
- **Translate missing terms with AI**: `/locale-translate --all` (in Claude Code)
- **Upload AI translations to POEditor**: `npm run locale:upload:missing`

## 🛠️ Available Commands

### Run these NPM scripts from the CRM root:

| Command | Purpose |
|---------|---------|
| `npm run locale:build` | Extract all translatable terms → `locale/messages.po` |
| `npm run locale:download` | Download translations from POEditor + generate missing-term batches + run audit |
| `npm run locale:audit` | Generate translation completeness report |
| `npm run locale:translate:list` | List locales with missing terms |
| `npm run locale:upload:missing` | Upload AI-translated term batches to POEditor |
| `npm run locale:lint` | Lint locale string usage across source files |

### Manual scripts:

- `node locale/scripts/poeditor-downloader.js --locale <code>` — Download one locale only
- `node locale/scripts/locale-add.js` — Add new language support
- `node locale/scripts/locale-branch-manager.js` — Manage locale translation branches (used by `/locale-translate` skill)

---

## 🌐 Complete Workflow

### Setup (one-time)

1. Copy `.env.example` → `.env` (or `.env.local`)
2. Add `POEDITOR_TOKEN` from the [POEditor account API](https://poeditor.com/account/api)
3. Database defaults work for local dev: `localhost/churchcrm/changeme`

### Translation cycle

1. **Extract**: `npm run locale:build` → Creates `locale/messages.po` with all terms
2. **Upload**: Upload `messages.po` to POEditor (web dashboard)
3. **Translate**: Contributors translate in POEditor interface
4. **Download**: `npm run locale:download` → Gets translations + missing-term batches + audit
5. **Fill gaps** (optional): `/locale-translate --all` → AI translation with church vocabulary
6. **Upload AI translations**: `npm run locale:upload:missing` → Uploads filled batches to POEditor
7. **Deploy**: Translations are compiled and built into the app

### Missing Terms

The downloader handles both downloads AND missing-term batches (no separate step needed).

**Output**: `locale/terms/missing/{poEditorCode}/{code}-N.json`  
**Batch size**: Up to 150 terms per file  
**Use**: Process with `/locale-translate`, then upload with `npm run locale:upload:missing`

---

## 📁 Directory Structure

```
locale/
├── README.md (this file)
├── scripts/
│   ├── locale-config.js          # Centralized path/settings config
│   ├── poeditor-downloader.js    # Download all translations from POEditor
│   ├── locale-audit.js           # Translation completeness audit
│   ├── locale-build.js           # Term extraction coordinator
│   ├── locale-build-db.js        # Extract database terms
│   ├── locale-build-static.js    # Extract static data (countries)
│   ├── locale-build-plugin-help.js # Extract plugin help.json terms
│   ├── locale-translate.js       # File helper for /locale-translate skill
│   ├── locale-branch-manager.js  # Git branch management for translation sessions
│   ├── locale-add.js             # Add new language support
│   ├── poeditor-upload-missing.js # Upload AI-translated batches to POEditor
│   └── i18next.config.ts         # i18next-cli extraction config
├── terms/
│   └── missing/                  # Untranslated-term batches (committed)
├── messages.po                   # Master translation template (upload to POEditor)
├── messages.json                 # Master term list (downloaded from POEditor)
├── poeditor-audit.md             # Generated completeness report
└── .work/                        # Build-time temp files (git-ignored, wiped after each build)
    ├── db-strings/               # DB extraction temp
    ├── static-strings/           # Static data extraction temp
    ├── plugin-help/              # Plugin help extraction temp
    └── locales/en/               # JS/React i18next extraction temp
```

---

## 📝 Localization Concepts

### System vs User Locale

- **System default**: For logged-out users, background jobs, and PHP `setlocale()` calls.
- **User preference**: Individual users can override in their profile → takes precedence.
- **Precedence**: User choice → System default → Browser language → English fallback

For testing: Always verify BOTH logged-out (system) and logged-in (user override) flows.

### PHP vs JavaScript

ChurchCRM uses two separate systems:

- **PHP/Gettext**: Server-rendered pages, uses `.mo` files and OS locales
- **JavaScript/i18next**: Client UI, uses JSON files from `src/locale/i18n/`

Both must be in sync. If some of your UI is in English and some is translated, check both systems.

---

## 🔧 How Term Extraction Works

The `npm run locale:build` script runs five extraction methods:

### 1. Database Terms
- Queries database for user-defined terms, system data
- Generates 112+ terms with proper context
- Uses direct MySQL connectivity (via `.env` config)

### 2. Static Data
- Extracts country names and locale display names
- Pulls from PHP Countries class library
- Generates 297+ terms (e.g., "China (中国)")

### 3. Plugin Help
- Scans `src/plugins/core/` and `src/plugins/community/` for `help.json` files
- Extracts summary, section titles/content, and link labels
- Deduplicates terms shared across plugins

### 4. PHP Source Code
- Scans all PHP files for `gettext()`, `_()`, `ngettext()` calls
- Uses GNU `xgettext` tool; excludes vendor/
- Captures 1,800+ terms from application logic

### 5. JavaScript/React
- Scans for i18next calls: `t()`, `i18next.t()`
- Uses `i18next-parser` for `.tsx` and `.js` files
- Generates 97+ UI element translations

### Result

All sources merged into `locale/messages.po` with **2,292+ terms**, no duplicates, ready for POEditor upload.

---

## ➕ Adding New Languages

### Quickest way:

```bash
node locale/scripts/locale-add.js --name "Korean" --code "ko" --locale "ko_KR" --country "KR" --datatables "Korean"
```

The script will:
1. Add the language to `src/locale/locales.json`
2. Create directories for translation files
3. Generate empty translation files

### Verify it worked:

```bash
# Extract terms (uses new language)
npm run locale:build

# Upload to POEditor manually
# Then download to populate the language:
npm run locale:download
```

---

## 📊 Translation Status

```bash
npm run locale:audit
```

Generates `locale/poeditor-audit.md` showing:

- 🟢 **Complete** (≥95% translated)
- 🟡 **Partial** (50-94% translated)
- 🔴 **Incomplete** (<50% translated)

Plus POEditor project statistics (total terms, languages, contributors).

---

## 🚀 Release Workflow (AI Translation)

For releases, automate missing-term translation using Claude Code:

1. Download all new translations: `npm run locale:download`
2. List locales needing work: `npm run locale:translate:list`
3. Translate with AI: `/locale-translate --all` (in Claude Code)
4. Upload to POEditor: `npm run locale:upload:missing`
5. Download approved: `npm run locale:download`
6. Commit: `git add src/locale/i18n locale/terms/missing && git commit -m "locale: ..."`

Full release guide: [.claude/commands/locale-release.md](../../.claude/commands/locale-release.md)

---

## 🐛 Troubleshooting

### POEditor token not working

✅ Check: `echo $POEDITOR_TOKEN` in terminal  
✅ Verify: `.env` file has `POEDITOR_TOKEN=...`  
✅ Regenerate: Get a fresh token from the [POEditor account API](https://poeditor.com/account/api)

### Terms not extracting

✅ Check: PHP files use `gettext()` or `_()` — not bare strings  
✅ Verify: `xgettext` is installed (`which xgettext`)  
✅ Check permissions: Can you write to `locale/` directory?

### Download fails

```bash
# Verify connection
curl -I https://api.poeditor.com/v2/projects/details

# Check API limits
# POEditor allows 1 request/sec; wait if rate-limited

# Try single locale
node locale/scripts/poeditor-downloader.js --locale fr --verbose
```

### Translations not appearing in UI

- **PHP strings**: Check `src/locale/textdomain/*/LC_MESSAGES/messages.mo` exists
- **JavaScript**: Check `src/locale/i18n/*.json` exists and has your keys
- **OS locale**: On production, verify system has the locale installed (`locale -a | grep es_ES`)

---

## 📚 Resources

| Resource | URL |
|----------|-----|
| POEditor Docs | [POEditor Docs](https://poeditor.com/docs/) |
| GNU Gettext | [GNU Gettext Manual](https://www.gnu.org/software/gettext/manual/) |
| i18next Docs | [i18next](https://www.i18next.com/) |
| ChurchCRM Translation Guide | [Translation Guide (wiki)](https://github.com/ChurchCRM/CRM/wiki/Translation) |

---

## 🤝 Contributing Translations

1. **Join POEditor project**: Ask maintainers for access
2. **Pick a language**: Select from available or request support
3. **Translate**: Use POEditor web interface for all terms
4. **Test**: Download and verify translations locally
5. **Submit**: Translations auto-sync to the repo

## Questions?

Open an issue: [GitHub Issues](https://github.com/ChurchCRM/CRM/issues)
