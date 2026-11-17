---
title: Locale Translation
intent: Translate missing ChurchCRM UI terms in the repo without losing work.
---

# Locale Translation

Translate missing UI terms for ChurchCRM. Translations live in the repo (`src/locale/i18n/<locale>.json` is the source of truth, with `.po`/`.mo` generated from it); there is no outside service. The complete locale loop, branches, and plural handling live in [the locale pipeline](../../../docs/maintainers/locale-pipeline.md);
the release checklist is in [the release process](../../../docs/maintainers/release-process.md#phase-1-localization).

## Agent rules

- Start every session on a fresh `locale/translate/**` branch with `node locale/scripts/locale-branch-manager.js --init`. Never reuse an earlier locale or `copilot/*` branch. `--init` runs the run's one preflight (dependencies installed, git identity set) and fails before anything is created when the checkout is not ready.
- Run `npm run locale:missing` to write the batches from the repo, then check what is left with `node locale/scripts/locale-translate.js --list`. A full `locale:missing` run also writes `locale/terms/context.json`, the code location of each queued term; `node locale/scripts/locale-translate.js --export` includes those locations in its payload — read them before translating an ambiguous short string.
- Translate only terms still missing. Use church vocabulary (congregation, ministries, offerings) appropriate to the locale; preserve placeholders, markup, URLs, and brand names.
- Follow every supplied plural key; do not add or remove forms or join them with `|`.
- Commit the whole run in one pass, the same for 1 term or 1,000: `node locale/scripts/locale-branch-manager.js --commit-and-push --terms <total>`. With no `--locale` it applies every locale's filled batches and makes a single commit and push. It writes only the locale JSON (`locale:apply --json-only`) and commits the JSON, `locale/terms/english-ok.json`, `locale/terms/context.json` and the queue folders `locale/terms/missing/<code>/` (applied terms leave them). Scope the same flow to one locale or family with `--locale <code[,code...]> --language "<name>"` when a run is deliberately partial.
- The PR carries JSON and queue only. After it merges, the **Locale: rebuild generated files** workflow rebuilds the `.po`/`.mo` on master and opens the rolling rebuild PR; the **Locale: check translations** workflow gates every locale PR (script tests, queue in sync, and `.po`/`.mo` matching the JSON on PRs that carry generated files).
- If validation or tooling fails, preserve the batches, fix failures within the assigned scope, and retry the failed step. Batches are consumed only after the locale files are written, so a failed apply loses nothing. Retry `--commit-and-push` after a failed push; it pushes an existing commit without creating another. Stop and report when recovery requires a maintainer decision.
- To fix an existing translation, write the corrected value into a batch file of that locale (any `locale/terms/missing/<code>/*.json`) and run `npm run locale:apply -- --json-only`, then commit as above.
- English variants (`en` is en-GB, `en-au`, `en-ca`, `en-za`, `en-jm`) are `skip_audit`, so `locale:missing` and `--list` skip them. Write their regional spellings by hand to `locale/terms/missing/<code>/<code>-1.json`, only for terms whose spelling differs from the US source, then run `npm run locale:apply -- --json-only`.
