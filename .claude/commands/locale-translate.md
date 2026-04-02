# /locale-translate

Translate missing ChurchCRM UI terms across one or all locales using church-appropriate ecclesiastical vocabulary.

## Usage

```bash
# List locales with missing terms
/locale-translate --list

# Translate a single locale (creates temp branch with auto-detected version)
/locale-translate --locale <poEditorCode>
# Example: /locale-translate --locale fr

# Translate all locales with missing terms (auto-detected version)
/locale-translate --all

# Translate with explicit version (optional)
/locale-translate --all --version 7.1.0
# Only needed if version can't be auto-detected
```

## Overview

This skill automates translation of untranslated terms with **automatic branch creation and per-locale pushes** to prevent data loss on cloud systems:

1. **Create temp branch** — `locale/{version}-{YYYY-MM-DD}` (e.g., `locale/7.1.0-2026-04-01`)
2. **For each locale**:
   - Read untranslated terms from `locale/terms/missing/{locale}/*.json`
   - Translate with Claude, applying denomination-aware church vocabulary
   - Apply translations back to batch files
   - **Commit AND PUSH immediately** (one commit per locale)
3. **Resume support** — If session times out, rerun `/locale-translate --all` to resume from next untranslated locale

**Key principle:** 
- ✅ One commit per locale (never batch)
- ✅ **Push after every commit** (prevents data loss on cloud timeouts)
- ✅ Branch prevents conflicts with main
- ✅ Easy to merge when complete via single PR

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

### ⛔ DO NOT TRANSLATE These Terms

The following terms must NEVER be localized — always leave them unchanged:

**Universal Abbreviations & Symbols:**
- `N/A` — Leave as `N/A` (not "Not Applicable")
- `@` — Email symbol (e.g., `name@example.com`)
- `SMS`, `SMTP`, `IMAP`, `POP3` — Communication protocols
- `API`, `HTTP`, `HTTPS`, `OAuth`, `REST` — Web standards
- `JSON`, `CSV`, `XML`, `HTML`, `CSS` — Data/markup formats
- `URL`, `URI`, `UUID`, `RFC`, `ISO`, `UTC` — Technical abbreviations

**Brand & Product Names:**
- `ChurchCRM` — Our application name
- `Vonage` — SMS/communication provider
- `Mailchimp` — Email service
- `GitHub` — Code repository platform
- `Google Meet`, `Slack`, `Zoom` — Third-party services
- `POEditor` — Translation platform

**Technical Terms:**
- `JavaScript`, `PHP`, `Python`, `SQL` — Programming languages
- `MySQL`, `PostgreSQL` — Databases
- `Docker`, `Kubernetes` — Infrastructure tools

**Email/URL Examples:**
- `name@example.com` — Placeholder email
- Any URL or domain example

If a batch file contains any of these terms with EMPTY translations, **leave them empty** — do not translate them.

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

## Branch & Commit Workflow

**IMPORTANT:** Each locale gets its own commit and push. Never batch multiple locales.

### Automatic Branch Creation

When you run `/locale-translate --all` or `/locale-translate --locale <code>`, the skill:

1. **Checks current branch** — if not on a locale branch, creates one:
   ```bash
   git checkout -b locale/{VERSION}-{YYYY-MM-DD}
   git push -u origin locale/{VERSION}-{YYYY-MM-DD}
   ```
   Example: `locale/7.1.0-2026-04-01`

2. **Reuses existing branch** — if you're already on a locale branch (from a resumed session), continues there

### Per-Locale Commit & Push

After translating each locale:

```bash
git add locale/terms/missing/<locale>/
git commit -m "locale: translate <code> (<language name>, <N> terms)"
git push origin locale/{VERSION}-{YYYY-MM-DD}
```

Example commits:
```
locale: translate fr (French - France, 77 terms)
locale: translate de (German - Germany, 78 terms)
locale: translate it (Italian - Italy, 70 terms)
```

**Why push after every commit:**
- ✅ Safe against cloud timeouts — work is on remote
- ✅ Easy to resume — next session checks what's already pushed
- ✅ Progress visibility — can monitor branch on GitHub
- ✅ No lost work — if session crashes, nothing is lost locally

### Resume Support (Cloud Timeout Safety)

If your cloud session times out mid-translation:

1. **Rerun the command** — starts from the next untranslated locale
   ```bash
   /locale-translate --all
   ```

2. **Skill detects** — you're already on the locale branch → continues there

3. **Skips completed locales** — resumes from next missing locale

All previously pushed commits remain safe on the remote.

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

The skill uses two helpers:

### File I/O — `locale/scripts/locale-translate.js`

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

### Branch Management — `locale/scripts/locale-branch-manager.js`

```bash
# Initialize a locale translation branch (version auto-detected from package.json)
node locale/scripts/locale-branch-manager.js --init
# Output: { branch: "locale/7.1.0-2026-04-01" }

# Initialize with explicit version (if auto-detect fails)
node locale/scripts/locale-branch-manager.js --init --version 7.1.0
# Output: { branch: "locale/7.1.0-2026-04-01" }

# Get current branch
node locale/scripts/locale-branch-manager.js --current
# Output: { branch: "locale/7.1.0-2026-04-01" }

# Check if on a locale branch
node locale/scripts/locale-branch-manager.js --is-locale-branch
# Output: { isLocaleBranch: true }

# Extract version from current locale branch
node locale/scripts/locale-branch-manager.js --get-version
# Output: { version: "7.1.0" }

# Commit and push a translated locale
node locale/scripts/locale-branch-manager.js --commit-and-push \
  --locale fr --language "French - France" --terms 154

# Get list of already-translated locales on current branch
node locale/scripts/locale-branch-manager.js --get-translated
# Output: { translated: ["af", "sq", "am", "ar"] }
```

---

## Complete Agent Workflow

When you run `/locale-translate --all`, here's what the Agent does:

```
1. Auto-detect version from package.json
   → node locale/scripts/locale-branch-manager.js --init
   → Auto-detects version 7.1.0 from package.json
   → Creates locale/7.1.0-2026-04-01 branch (or reuses existing)

2. For each untranslated locale:
   a. Get remaining locales (skip already-translated)
   b. Read untranslated terms from batch files
   c. Translate with Claude + church vocabulary
   d. Apply translations locally
   e. Commit and push
      → node locale/scripts/locale-branch-manager.js --commit-and-push \
           --locale fr --language "French - France" --terms 154
   f. Show progress (e.g., "locale/7.1.0-2026-04-01: 5/39 locales")

3. After all locales:
   → Branch is on remote with all commits
   → User can proceed to upload phase
```

**Safety features:**
- ✅ Every commit is pushed immediately (can't lose to timeout)
- ✅ Can resume by running command again (skips completed locales)
- ✅ All work is on `locale/{version}-{date}` branch (won't conflict with main)
- ✅ Version auto-detected from package.json (no manual entry needed)

---

## After Translation (Simplified Workflow)

Once all desired locales are translated and committed:

1. **Push commits** — `git push` to publish translation commits
2. **Upload to POEditor** — Run `npm run locale:upload:missing`
   - Validates batch files (checks for real translations)
   - Shows sample of each locale for confirmation
   - Uploads to POEditor for human reviewer approval
   - **Does NOT download** — download will happen via GitHub Action
3. **Wait for POEditor approval** — Share locale vocabulary rules with your translation team
4. **GH Action downloads** — The `locale-release` workflow automatically downloads reviewed translations
   - Creates a new PR with `src/locale/i18n/` updates
   - Review and merge when ready

**Why this is simpler:**
- No redundant download steps — one source of truth (GH Action)
- Cleaner git history — translation commits separate from download commits
- Reduced POEditor API calls — no double-fetch after upload

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
