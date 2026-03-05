# Locale Release Workflow

Run the full localization pipeline before a release: regenerate missing terms, translate them with Claude Code, then prepare them for POEditor upload.

## Steps

### Step 1 — Regenerate missing terms

```bash
npm run locale:missing
```

This compares `locale/messages.json` against all installed `src/locale/i18n/*.json` files and writes fresh empty batch files to `locale/terms/missing/{locale}/`.

### Step 2 — Preview what needs translation

```bash
npm run locale:translate:list
```

Review the list of locales and term counts.

### Step 3 — Translate all missing terms

Invoke the translation skill:

```
/locale-translate --all
```

Claude Code will read each batch file, apply church-appropriate vocabulary for the locale's denomination context, and write the translated terms back into `locale/terms/missing/`.

To translate a single locale instead:
```
/locale-translate --locale <poEditorCode>
```

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

### Step 6 — Upload to POEditor

Upload the filled batch files from `locale/terms/missing/` to POEditor. POEditor is the source of truth for all translations.

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

- [AI Locale Translation](.agents/skills/churchcrm/locale-ai-translation.md) — full reference
- [i18n & Localization](.agents/skills/churchcrm/i18n-localization.md) — term consolidation and locale rebuild
