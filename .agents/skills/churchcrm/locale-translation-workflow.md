---
title: Locale Translation
intent: Translate missing ChurchCRM UI terms in the repo without losing work.
---

# Locale Translation

Translate missing UI terms for ChurchCRM. Translations live in the repo (`src/locale/i18n/<locale>.json`, with `.po`/`.mo` built from it); there is no outside service. The complete locale loop, branches, and plural handling live in [the locale pipeline](../../../docs/maintainers/locale-pipeline.md);
the release checklist is in [the release process](../../../docs/maintainers/release-process.md#phase-1-localization).

## Agent rules

- Start every session on a fresh `locale/translate/**` branch with `node locale/scripts/locale-branch-manager.js --init`. Never reuse an earlier locale or `copilot/*` branch.
- Run `npm run locale:missing` to write the batches from the repo, then check what is left with `node locale/scripts/locale-translate.js --list`.
- Translate only terms still missing. Use church vocabulary (congregation, ministries, offerings) appropriate to the locale; preserve placeholders, markup, URLs, and brand names.
- Follow every supplied plural key; do not add or remove forms or join them with `|`.
- After every locale, run `node locale/scripts/locale-branch-manager.js --commit-and-push --locale <code> --language "<name>" --terms <n>`. It runs `npm run locale:apply` for that locale and commits only its JSON, `.po`, `.mo` and `locale/terms/english-ok.json`, plus that locale's queue folder `locale/terms/missing/<code>/` (applied terms leave it). The terms PR commits the queue.
- When done, open a PR from the branch. It merges like any other PR after review.
- If a step fails (including a value `locale:apply` rejects), stop and report before moving to another locale. Committing each locale protects completed work from session loss.
- To fix an existing translation, write the corrected value into a batch file of that locale (any `locale/terms/missing/<code>/*.json`) and run `npm run locale:apply`.
- English variants (`en` is en-GB, `en-au`, `en-ca`, `en-za`, `en-jm`) are `skip_audit`, so `locale:missing` and `--list` skip them. Write their regional spellings by hand to `locale/terms/missing/<code>/<code>-1.json`, only for terms whose spelling differs from the US source, then run `npm run locale:apply`.
