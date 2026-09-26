# Locale Release Workflow

Run the full localization pipeline before a release: regenerate missing terms, translate them with Claude Code, then prepare them for POEditor upload.

## Steps

### Step 1 — Generate missing-term batches (via main downloader)

```bash
# Full run (all locales):
npm run locale:download

# Single-locale only (e.g. French):
node locale/scripts/poeditor-downloader.js --locale fr
```

The downloader now writes missing-term batch files to `locale/terms/missing/{locale}/` when appropriate.

### Step 2 — Preview what needs translation

```bash
npm run locale:translate:list
```

Review the list of locales and term counts.

### Step 3 — Translate all missing terms

**Always on a brand-new branch.** `/locale-translate` creates a fresh `locale/translate/{version}-{YYYY-MM-DD}-{HHMMSS}` branch on every invocation — it never reuses a prior run's branch. If you are resuming a release after a timeout, just rerun the command; a new branch will be cut and already-uploaded locales will be skipped by POEditor.

Invoke the translation skill:

```
/locale-translate --all
```

Claude Code will read each batch file, apply church-appropriate vocabulary for the locale's denomination context, and write the translated terms back into `locale/terms/missing/`.

To translate a single locale instead:
```
/locale-translate --locale <poEditorCode>
```

### Step 3.5 — Commit + Push after EVERY locale (MANDATORY) <!-- learned: 2026-04-09 -->

**⛔ NON-NEGOTIABLE: After EVERY locale, immediately commit → push.** The push uploads it: [`locale-upload-missing.yml`](../../.github/workflows/locale-upload-missing.yml) runs on every push to `locale/translate/**`, uploads the changed locales to POEditor, and starts the POEditor sync.

```bash
# After each locale is translated and applied:
git add locale/terms/missing/<CODE>/ locale/terms/english-ok.json
git commit -m "locale: translate <CODE> (<LANGUAGE>, <N> terms)"
git push origin $(git branch --show-current)
```

**Never batch multiple locales into one commit. Never skip the push.** Upload by hand (`node locale/scripts/poeditor-upload-missing.js --locale <CODE> --yes`) only if the workflow run for your push failed, or your session pushes with the Actions `GITHUB_TOKEN` (those pushes start no workflows).

**Why?**
- **Commit** = save point (protects against session crash)
- **Push** = remote backup, and it triggers the POEditor upload

We have lost hours of translated work because agents translated 20+ locales without committing or pushing. This rule exists to prevent that from ever happening again.

### Step 4 — Verify

```bash
npm run locale:translate:list
```

Locales that are fully translated will no longer appear in the list.

### Step 5 — Notify POEditor contributors

The `/locale-translate` skill automatically writes `locale/terms/missing/REVIEW_NOTES.md` after translation. This file contains a checklist and message for your POEditor contributors explaining that the translations are AI-generated and need human review.

Share that file (or its contents) with your translation team before or alongside your POEditor upload so reviewers know what to check:
- Ecclesiastical vocabulary appropriate for their denomination/region
- Plural form correctness
- Format specifiers (`%d`, `%s`) preserved
- Natural-sounding UI labels

### Step 6 — Upload to POEditor (already done per-locale)

If Step 3.5 was followed correctly, all translated locales are already uploaded to POEditor. Verify with:

```bash
# Check if any locales still need upload (should show 0 or only empty locales)
node locale/scripts/poeditor-upload-missing.js --dry-run
```

If any were missed (e.g., upload failed during translation), upload them now:
```bash
npm run locale:upload:missing -- --yes
```

After contributors have reviewed and approved translations in POEditor:

```bash
npm run locale:download
```

This pulls the approved translations back into `src/locale/i18n/` and runs `locale:audit`.

### Step 7 — Commit

```bash
git add src/locale/i18n/
git commit -m "locale: translate missing terms for release"
```

## Church Vocabulary Summary

The `/locale-translate` skill enforces these rules automatically:

| Generic CRM term | Church translation |
|---|---|
| Members / Users | Congregation / Parishioners |
| Leads / Prospects | Visitors / Seekers |
| Groups | Small Groups / Ministries |
| Giving / Payments | Offerings / Tithes / Stewardship |
| Transactions | Contributions |

Denomination context is applied per locale (Coptic Orthodox for Egyptian Arabic, Catholic for IT/ES/FR, Orthodox for RU/UA/GR, Lutheran for DE/SE/NO/FI, Evangelical for KR/TW, etc.)

## Related Skills

- [Locale pipeline](../../docs/locale-pipeline.md) — branches, workflows and plural handling
- [i18n & Localization](../../.agents/skills/churchcrm/i18n-localization.md) — term consolidation and locale rebuild
