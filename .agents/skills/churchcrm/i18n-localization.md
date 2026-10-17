---
title: i18n & Localization
intent: How to add UI strings. Extraction is CI on merge to master.
---

# i18n

Locale list: `src/locale/locales.json`.
Locale loop: [docs/maintainers/locale-pipeline.md](../../../docs/maintainers/locale-pipeline.md).
Translation-agent rules: [Locale Translation](./locale-translation-workflow.md).
Feature localization guidance: `locale/README.md`.
Extract job: `.github/workflows/locale-generate-terms.yml` (`npm run locale:build` on merge to `master`).

## Feature PR

1. Wrap PHP UI text in `gettext()`
2. Wrap JS UI text in `i18next.t()`
3. Reuse an existing msgid when the words are the same role (People not Persons in UI)
4. Commit the source only; `messages.po` and `locale:build` run on merge to `master`

## Phrases

Use one whole phrase (`gettext('Delete Group')`) or `sprintf(gettext('Delete %s'), …)`.
SOV locales cannot reorder concatenated pieces such as `gettext('Delete') . ' ' . gettext('Group')`.

Title Case for chrome (labels, buttons, headings). Sentence case for help and toasts.
Keep acronyms `ID`, `URL`, `API`.

Family status in UI: Active / Inactive. Actions: Set Active / Set Inactive.

## Counts

Put the number after a label so no language needs plural forms: `sprintf(gettext('Families emailed: %d'), $n)`, `i18next.t('Members copied: {{total}}', { total: n })`.
Use a named variable such as `total` in `i18next.t()`: a `count` option makes the extractor emit `one`/`other` terms, and languages with more forms (ru, pl, cs, ar) fall back to `other`.
Unit abbreviations (`{{minutes}} min left`) are fine. Keep existing translated plurals; rewording a msgid discards its translations.

## Moving UI

Translations are keyed by the English msgid, not the file. Moving a `gettext()` call to another page keeps its translations; rewording it discards them in every locale.

- Move the string verbatim. Do copy edits in a separate PR.
- Settings: reuse the `ConfigItem` tooltip via `SystemConfig::getSettingsConfig()` instead of retyping a label or tooltip in the view.
- Before adding a string, grep `src/locale/` for the old msgid and reuse it. A new msgid is a cost, so justify it in the PR body.
- Keep the wording on a move ("Hide X" → "Show X" is a new term).

JS: `i18next` loads in the footer — wrap inline `i18next.t()` in `$(document).ready()` / `DOMContentLoaded`.

Plugins ship their own translations. Plugin strings stay out of core `messages.po`.
