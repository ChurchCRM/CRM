# Simplified Locale Translation Workflow <!-- learned: 2026-04-01 -->

## Overview

The ChurchCRM locale translation workflow has been simplified to separate concerns and reduce redundant API calls.

**Three clear phases:**
1. **Translate** — Claude translates missing terms for all locales (commits immediately per locale)
2. **Upload** — Validate and upload to POEditor for human review (no automatic download)
3. **Download** — GitHub Action automatically downloads reviewed translations

---

## Phase 1: Translate Missing Terms

Use the `/locale-translate` skill to translate all missing terms for a locale or all locales:

```bash
# List locales with missing terms
/locale-translate --list

# Translate a single locale
/locale-translate --locale fr

# Translate all locales
/locale-translate --all
```

**Workflow:**
- Claude reads untranslated terms from `locale/terms/missing/{locale}/` batch files
- Applies church-appropriate vocabulary per locale denomination
- Commits immediately after each locale (one commit per locale, never batched)
- Example commit: `locale: translate fr (French - France, 154 terms)`

**Key principle:** Each locale gets its own commit as a safe save point. If interrupted mid-`--all`, simply resume from the next locale.

---

## Phase 2: Upload to POEditor

After all translations are complete and committed, upload to POEditor:

```bash
npm run locale:upload:missing
```

**Upload script behavior:**
- Discovers all locale folders in `locale/terms/missing/`
- Validates each locale:
  - Splits terms into: valid translations, suspect (identical to key), empty
  - Shows sample of translations for review
  - Skips suspect and empty terms
- **Prompts for confirmation** per locale (or use `--yes` to auto-approve)
- **Uploads to POEditor** with metadata (parsing & update counts)
- **Does NOT download** — that now happens via GH Action

**Flags:**
- `--locale es,fr,de` — Translate specific locales (comma-separated)
- `--yes` or `-y` — Skip confirmation prompts
- `--dry-run` — Show what would be uploaded without sending

**Next steps:**
- Share translations with your POEditor translation team
- Wait for human review and approval
- Download happens automatically via GitHub Action

---

## Phase 3: Automated Download (GitHub Action)

The `locale-release` GitHub Action automatically handles the download phase:

1. Runs on schedule or on-demand
2. Calls `npm run locale:download` to fetch approved translations from POEditor
3. Generates/updates:
   - `src/locale/i18n/{locale}.json` — JSON translations
   - `src/locale/textdomain/{locale}/LC_MESSAGES/messages.{po,mo}` — gettext format
   - `locale/terms/missing/{locale}/` — batch files for next iteration
4. Creates a PR with all updates

**Why this is cleaner:**
- Single source of truth: GH Action runs the download once
- No redundant API calls: upload script doesn't download anymore
- Clearer git history: translation commits separate from download commits
- Easier to debug: each phase has its own script and logs

---

## Example Workflow: 7.1.0 Release

```bash
# 1. Translate all missing terms
/locale-translate --all
# → Multiple commits like "locale: translate fr (French, 154 terms)"

# 2. Push to origin
git push

# 3. Notify POEditor team
# (share church vocabulary rules from .agents/skills/churchcrm/locale-ai-translation.md)

# 4. Upload to POEditor
npm run locale:upload:missing --yes
# → Validates, shows samples, uploads
# → Output: "Done — 1234 term(s) uploaded"

# 5. Wait for POEditor approval
# (team reviews and translates)

# 6. GitHub Action downloads (automatic)
# → Creates PR with src/locale/i18n/ updates

# 7. Review and merge PR
gh pr view
gh pr merge <pr-number>
```

---

## Simplified vs. Previous Workflow

| Phase | Previous | Now |
|-------|----------|-----|
| **Translate** | `/locale-translate --all` | `/locale-translate --all` (same) |
| **Upload** | `npm run locale:upload:missing` + automatic downloader call | `npm run locale:upload:missing` (only upload) |
| **Download** | Happened immediately after each locale upload (expensive) | Automatic via GH Action (scheduled or on-demand) |
| **Git commits** | Mixed: translation + download in one phase | Separate: translation commits, then download PR |

**Advantages:**
- 🚀 **Faster:** No waiting for downloads after each upload
- 💰 **Fewer API calls:** One download pass instead of per-locale
- 🔍 **Clearer history:** Translation commits not mixed with download commits
- ⚙️ **Easier to debug:** Each phase is independent

---

## Helper Scripts

All locale operations use helper scripts under `locale/scripts/`:

| Script | Purpose | Driven by |
|--------|---------|-----------|
| `locale-translate.js` | Read/apply batch files | `/locale-translate` skill |
| `poeditor-upload-missing.js` | Validate & upload to POEditor | `npm run locale:upload:missing` |
| `poeditor-downloader.js` | Download from POEditor, generate formats | `npm run locale:download` (GH Action) |
| `locale-config.js` | Centralized configuration | All locale scripts |

---

## Troubleshooting

**Q: How do I resume if interrupted during translation?**
- Run `/locale-translate --all` again — it skips already-translated locales

**Q: What if upload fails?**
- Check POEditor API token in `.env`
- Run `npm run locale:upload:missing` again (with or without `--yes`)

**Q: When will the download happen after my upload?**
- The GH Action runs on schedule or can be triggered manually
- It automatically downloads and creates a PR with the translated files

**Q: Can I download manually while waiting?**
- Yes: `npm run locale:download` (but the GH Action will do this anyway)

---

## Related Documentation

- `.agents/skills/churchcrm/locale-ai-translation.md` — Church vocabulary rules & denomination context
- `.agents/skills/churchcrm/i18n-localization.md` — Locale configuration, string extraction
- `.claude/commands/locale-translate.md` — `/locale-translate` skill reference
- `.claude/commands/locale-release.md` — Full release checklist (for releases, not just translations)
