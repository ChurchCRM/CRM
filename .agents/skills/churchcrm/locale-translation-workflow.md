---
title: Locale Translation
intent: Translate missing ChurchCRM UI terms without losing work or bypassing the upload workflow.
---

# Locale Translation

Translate missing UI terms for ChurchCRM. The complete locale loop, branches,
uploads, and plural handling live in [the locale pipeline](../../../docs/maintainers/locale-pipeline.md);
the release checklist is in [the release process](../../../docs/maintainers/release-process.md#phase-1-localization-sync).

## Agent rules

- Before translating, check remaining terms with `node locale/scripts/locale-translate.js --list`.
- Start every session on a fresh `locale/translate/**` branch with `node locale/scripts/locale-branch-manager.js --init`. Never reuse an earlier locale or `copilot/*` branch.
- Translate only terms still missing. Use church vocabulary (congregation, ministries, offerings) appropriate to the locale; preserve placeholders, markup, URLs, and brand names.
- Follow every supplied plural key; do not add or remove forms or join them with `|`.
- Commit and push after every locale. Stage only that locale's missing-term files and `locale/terms/english-ok.json` when needed.
- A push with your own credentials triggers the translation upload. An Actions `GITHUB_TOKEN` push does not; report affected locales for a manual upload instead.
- If a step fails, stop and report before moving to another locale. Committing each locale protects completed work from session loss.
