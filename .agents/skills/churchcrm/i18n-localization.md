---
title: i18n & Localization
intent: How to add UI strings. Extraction is CI on merge to master.
---

# i18n

Locale list: `src/locale/locales.json`.
Operator / POEditor workflow: `locale-translation-workflow.md` and `locale/README.md`.
Extract job: `.github/workflows/locale-generate-terms.yml` (`npm run locale:build` on merge to `master`).

## Feature PR

1. Wrap PHP UI text in `gettext()` / `ngettext()`
2. Wrap JS UI text in `i18next.t()`
3. Reuse an existing msgid when the words are the same role (People not Persons in UI)
4. Commit the source only — no `messages.po`, no `locale:build`

## Phrases

Use one whole phrase (`gettext('Delete Group')`) or `sprintf(gettext('Delete %s'), …)`.
Do not glue `gettext('Delete') . ' ' . gettext('Group')` — SOV locales cannot reorder pieces.

Title Case for chrome (labels, buttons, headings). Sentence case for help and toasts.
Keep acronyms `ID`, `URL`, `API`.

Family status in UI: Active / Inactive. Actions: Set Active / Set Inactive.

JS: `i18next` loads in the footer — wrap inline `i18next.t()` in `$(document).ready()` / `DOMContentLoaded`.

Plugins ship their own translations. Do not put plugin strings in core `messages.po`.
