---
title: i18n & Localization
intent: How to add UI strings. Extraction is CI on merge to master.
---

# i18n

Locale list: `src/locale/locales.json`.
Locale loop: [docs/maintainers/locale-pipeline.md](../../../docs/maintainers/locale-pipeline.md). Design: [docs/maintainers/locale-design.md](../../../docs/maintainers/locale-design.md).
Translation-agent rules: [Locale Translation](./locale-translation-workflow.md).
Feature localization guidance: `locale/README.md`.
Translations live in the repo: `src/locale/i18n/<locale>.json` is the source of truth, and each locale `.po`/`.mo` is built from it (`npm run locale:apply`). There is no outside translation service.
Extract job: `.github/workflows/locale-generate-terms.yml` (`Locale: extract terms`: waits 15 minutes after the last merge to `master`, then `npm run locale:build` (extract, prune, missing queue) and one terms PR, `locale: new terms from code (+N / -M) - <date>`, on `locale/terms/<version>`).
The terms PR body lists each new and removed msgid, where it is in the code, and **Needs a look** flags (code-like, HTML, whitespace, near-duplicate, reworded term that loses translations). Generator: `locale/scripts/lib/locale-terms-diff.js`; run it locally with `node locale/scripts/lib/locale-terms-diff.js --base HEAD` after `npm run locale:build` to see what your PR will add.

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

## Terms

- Source strings use US English spelling (color, organize, canceled, behavior, catalog). Regional spelling lives in the English variant locales (`en` is en-GB, `en-au`, `en-ca`, `en-za`, `en-jm`), never in the source.
- Reuse an existing msgid before adding one. Grep `locale/messages.po` for case, trailing punctuation, colon and ellipsis variants (`Save Changes`, `Loading...`) and use the existing form.
- Add no near-duplicate terms: one msgid per wording, differing only by case or punctuation.
- A PR that rewords a source term re-translates it in the same PR, so no locale loses it: `npm run locale:missing -- --term "New wording"`, translate, `npm run locale:apply` ([locale-pipeline.md](../../../docs/maintainers/locale-pipeline.md#rewording-a-string)). List each old term → new term in the PR body.

Family status in UI: Active / Inactive. Actions: Set Active / Set Inactive.

## Counts

Put the number after a label so no language needs plural forms: `sprintf(gettext('Families emailed: %d'), $n)`, `i18next.t('Members copied: {{total}}', { total: n })`.
Use a named variable such as `total` in `i18next.t()`: a `count` option makes the extractor emit `one`/`other` terms, and languages with more forms (ru, pl, cs, ar) fall back to `other`.
Unit abbreviations (`{{minutes}} min left`) are fine. Keep existing translated plurals; rewording a msgid discards its translations unless the PR re-translates it.

## Moving UI

Translations are keyed by the English msgid, not the file. Moving a `gettext()` call to another page keeps its translations; rewording it discards them in every locale.

- Move the string verbatim. Do copy edits in a separate PR.
- Settings: reuse the `ConfigItem` tooltip via `SystemConfig::getSettingsConfig()` instead of retyping a label or tooltip in the view.
- Before adding a string, grep `src/locale/` for the old msgid and reuse it. A new msgid is a cost, so justify it in the PR body.
- Keep the wording on a move ("Hide X" → "Show X" is a new term).

JS: `i18next` loads in the footer — wrap inline `i18next.t()` in `$(document).ready()` / `DOMContentLoaded`.

Plugins ship their own translations. Plugin strings stay out of core `messages.po`.
