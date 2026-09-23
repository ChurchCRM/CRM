# ChurchCRM Localization System

## Feature PRs

Wrap new UI strings in `gettext()` / `i18next.t()` and commit the source only.
Do not run `npm run locale:build` and do not commit `locale/terms/messages.po`.

Extraction runs on every merge to `master`:
`.github/workflows/locale-generate-terms.yml`

Locale list: `locale/locales.json` (or `src/locale/locales.json` if that is the checked-in file).
Agent rules: `.agents/skills/churchcrm/i18n-localization.md`

## Operator commands (CI / maintainers)

Run from the CRM root:

| Command | Purpose |
|---------|---------|
| `npm run locale:build` | Extract terms into `messages.po` (merge-to-master job) |
| `npm run locale:download` | POEditor download + missing-term batches |
| `npm run locale:audit` | Completeness report |
| `npm run locale:translate:list` | Locales with missing terms |

Add a language: `node locale/locale-add.js --name "Korean" --code "ko" --locale "ko_KR" --country "KR"`

Scripts live in `locale/scripts/`.
