# ChurchCRM Localization System

## Feature PRs

Wrap new UI strings in `gettext()` / `i18next.t()` and commit the source only.
Do not run `npm run locale:build` and do not commit `locale/terms/messages.po`.

Extraction runs on every merge to `master`:
`.github/workflows/locale-generate-terms.yml`

Locale list: `src/locale/locales.json`.
Agent rules: `.agents/skills/churchcrm/i18n-localization.md`.

## Operator commands (CI / maintainers)

| Command | Purpose |
|---------|---------|
| `npm run locale:build` | Extract terms into `messages.po`, prune removed terms from every locale, rewrite the missing queue (merge-to-master job) |
| `npm run locale:missing` | Missing-term batches from the repo (`locale/terms/missing/`, checked in until translated) |
| `npm run locale:translate:list` | Locales with missing terms in the batches |
| `npm run locale:apply` | Write filled batches into `src/locale/i18n` and rebuild each `.po`/`.mo` (`--prune` drops removed terms, `--check` verifies) |
| `npm run locale:audit` | Completeness report from the repo |

Translations live in the repo; there is no outside translation service. The full loop: `docs/maintainers/locale-pipeline.md`.

Add a language: `node locale/scripts/locale-add.js --name "Korean" --code "ko" --locale "ko_KR" --country "KR"`

Scripts: `locale/scripts/`.
