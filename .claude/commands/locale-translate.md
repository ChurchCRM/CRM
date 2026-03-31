# /locale-translate

Translate missing ChurchCRM UI terms across one or all locales using church-appropriate ecclesiastical vocabulary.

## Usage

```bash
# List locales with missing terms
/locale-translate --list

# Translate a single locale
/locale-translate --locale <poEditorCode>
# Example: /locale-translate --locale fr

# Translate all locales with missing terms
/locale-translate --all
```

## Overview

This skill automates translation of untranslated terms by:

1. **Reading** untranslated terms from `locale/terms/missing/{locale}/*.json`
2. **Translating** with Claude, applying denomination-aware church vocabulary
3. **Applying** translations back to batch files
4. **Committing** after each locale (one commit per locale, every time)

**Key principle:** Commit immediately after each locale is translated. Do not batch multiple locales into one commit.

---

## Locale Codes

Use the 2-letter or locale-specific POEditor code:

- **Spanish variants:** `es` (Spain), `es-MX` (Mexico), `es-AR` (Argentina), `es-CO` (Colombia), `es-SV` (El Salvador)
- **Portuguese variants:** `pt` (Portugal), `pt-br` (Brazil)
- **Chinese variants:** `zh-CN` (China), `zh-TW` (Taiwan)
- **European:** `fr`, `de`, `it`, `nl`, `pl`, `ro`, `ru`, `cs`, `hu`, `et`, `fi`, `sv`, `nb`, `el`, `he`
- **Other:** `ar`, `af`, `sq`, `am`, `id`, `ja`, `ko`, `sw`, `ta`, `te`, `th`, `tr`, `uk`, `vi`

Run `/locale-translate --list` to see all available locales with missing term counts.

---

## Church Vocabulary Rules

The skill applies these rules automatically:

| English (generic) | Church-appropriate translation |
|---|---|
| Members / Users / Clients | Congregation / Parishioners / Faithful |
| Leads / Prospects | Visitors / Seekers |
| Groups / Teams | Small Groups / Life Groups / Ministries |
| Giving / Payments / Transactions | Offerings / Tithes / Stewardship / Contributions |
| Pledge | Financial pledge / commitment to the church |
| Deposit | Offering deposit |
| Cart (selection) | Selection / List / Roster |
| Family | Family (keep as-is) |

Standard UI terms (Save, Cancel, Delete, Search, etc.) → use normal native-language UI translations.

**Format specifiers** (`%d`, `%s`, `%1$s`) are preserved verbatim in all translations.

### Denomination Context by Locale

Translations use denomination-appropriate ecclesiastical vocabulary for each region:

- **Coptic Orthodox** (Egyptian Arabic / `ar`)
- **Roman Catholic** (Spanish, Italian, French, Portuguese, Polish)
- **Orthodox** (Russian, Ukrainian, Greek, Romanian)
- **Lutheran** (German, Scandinavian, Baltic)
- **Evangelical/Presbyterian** (Korean, Chinese, Indonesian)
- **Anglican/Ecumenical** (English variants)
- **Broadly Christian** (all others)

---

## Plural Forms

Batch files use this structure for terms with plural forms:

```json
{
  "%d person": { "one": "", "other": "" },
  "%d group": { "one": "", "other": "" }
}
```

The skill provides grammatically correct singular and plural forms per the target language's plural rules:

```json
{
  "%d person": { "one": "1 personne", "other": "%d personnes" },
  "%d group": { "one": "1 groupe", "other": "%d groupes" }
}
```

---

## Workflow

### Single Locale

1. Run `/locale-translate --locale <code>`
2. Claude translates all batch files for that locale
3. Files are automatically updated in `locale/terms/missing/<locale>/`
4. Changes are committed immediately
5. User sees status message with term count

### All Locales

1. Run `/locale-translate --all`
2. Locales are processed one at a time
3. After each locale is translated:
   - Changes committed
   - Status reported (can continue or stop)
4. After all locales complete, user is prompted to upload to POEditor

---

## Commit Workflow

**IMPORTANT:** Each locale gets its own commit. Never batch multiple locales.

After translating each locale, the skill:

```bash
git add locale/terms/missing/<locale>/
git commit -m "locale: translate <code> (<language name>, <N> terms)"
git push
```

Example commits:
```
locale: translate fr (French - France, 77 terms)
locale: translate de (German - Germany, 78 terms)
locale: translate it (Italian - Italy, 70 terms)
```

This creates safe save points — if a session is interrupted mid-way through `--all`, the next session can resume without losing completed work.

---

## Shell Escaping for Large Translations

When applying large JSON translation objects, the skill writes to a temp file to avoid shell escaping issues:

```bash
cat > /tmp/<locale>-<batchnum>-trans.json << 'ENDJSON'
{ "key": "translation" }
ENDJSON

node locale/scripts/locale-translate.js --apply \
  --file locale/terms/missing/<locale>/<locale>-<batchnum>.json \
  --translations "$(cat /tmp/<locale>-<batchnum>-trans.json)"

rm /tmp/<locale>-<batchnum>-trans.json
```

This is handled automatically by the skill and requires no user action.

---

## Helper Script Interface

The skill uses the `locale/scripts/locale-translate.js` helper for file I/O:

```bash
# List all locales with missing terms
node locale/scripts/locale-translate.js --list

# Get metadata for a locale (paths, term counts, language name)
node locale/scripts/locale-translate.js --info --locale <code>

# Read untranslated terms from one batch file
node locale/scripts/locale-translate.js --read-file --file locale/terms/missing/<locale>/<locale>-<n>.json

# Apply translations to a batch file
node locale/scripts/locale-translate.js --apply \
  --file locale/terms/missing/<locale>/<locale>-<n>.json \
  --translations '<json>'
```

---

## After Translation

Once all desired locales are translated:

1. **Upload to POEditor** — Upload the filled batch files from `locale/terms/missing/` to POEditor
2. **Notify reviewers** — Share the locale vocabulary rules with your POEditor translation team for human review
3. **After approval** — Run `npm run locale:download` to pull approved translations into `src/locale/i18n/`
4. **Final commit** — `git add src/locale/i18n/ && git commit -m "locale: download reviewed translations"`

---

## Troubleshooting

**"Unknown locale code"** — Run `/locale-translate --list` to see valid codes

**"No untranslated terms"** — The locale has been fully translated, or `npm run locale:download` hasn't been run yet

**"Batch file not found"** — The helper script couldn't locate the file. Ensure `locale/terms/missing/` exists and contains the locale code

**"Invalid translations JSON"** — The JSON syntax is malformed. The skill checks this before applying

---

## Related Skills & Documentation

- [AI Locale Translation](.agents/skills/churchcrm/locale-ai-translation.md) — full reference, church vocabulary tables
- [i18n & Localization](.agents/skills/churchcrm/i18n-localization.md) — term consolidation, locale rebuild, string extraction
- [Locale Release Workflow](.claude/commands/locale-release.md) — full pre-release checklist
- [Git Workflow](.agents/skills/churchcrm/git-workflow.md) — branching, commit best practices
