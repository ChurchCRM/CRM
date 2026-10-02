---
title: i18n & Localization
intent: How to add UI strings. Extraction is CI on merge to master.
---

# i18n

Locale list: `src/locale/locales.json`.
Operator / POEditor workflow: `locale-translation-workflow.md` and `locale/README.md`.
Extract job: `.github/workflows/locale-generate-terms.yml` (`npm run locale:build` on merge to `master`).

## Feature PR

1. Wrap PHP UI text in `gettext()`
2. Wrap JS UI text in `i18next.t()`
3. Reuse an existing msgid when the words are the same role (People not Persons in UI)
4. Commit the source only — no `messages.po`, no `locale:build`

## Phrases

Use one whole phrase (`gettext('Delete Group')`) or `sprintf(gettext('Delete %s'), …)`.
Do not glue `gettext('Delete') . ' ' . gettext('Group')` — SOV locales cannot reorder pieces.

Title Case for chrome (labels, buttons, headings). Sentence case for help and toasts.
Keep acronyms `ID`, `URL`, `API`.

Family status in UI: Active / Inactive. Actions: Set Active / Set Inactive.

## Counts

Put the number after a label so no language needs plural forms: `sprintf(gettext('Families emailed: %d'), $n)`, `i18next.t('Members copied: {{total}}', { total: n })`.
Do not add new `ngettext()` calls, and do not pass `count` to `i18next.t()`: `count` makes the extractor emit `one`/`other` terms, and languages with more forms (ru, pl, cs, ar) then fall back to `other`.
Unit abbreviations (`{{minutes}} min left`) are fine. Keep existing translated plurals; rewording a msgid discards its translations.

## Moving UI

Translations are keyed by the English msgid, not the file. Moving a `gettext()` call to another page keeps its translations; rewording it discards them in every locale.

- Move the string verbatim. Do copy edits in a separate PR.
- Settings: reuse the `ConfigItem` tooltip via `SystemConfig::getSettingsConfig()` instead of retyping a label or tooltip in the view.
- Before adding a string, grep `src/locale/` for the old msgid and reuse it. A new msgid is a cost, so justify it in the PR body.
- Do not invert wording on a move ("Hide X" → "Show X"). That is a new term.

JS: `i18next` loads in the footer — wrap inline `i18next.t()` in `$(document).ready()` / `DOMContentLoaded`.

Plugins ship their own translations. Do not put plugin strings in core `messages.po`.
