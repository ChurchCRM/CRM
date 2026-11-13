# /locale-translate

Translate missing ChurchCRM UI terms for one or all locales.

**Arguments:** `--list`, `--locale <code>`, or `--all`

Follow the agent rules in [`.agents/skills/churchcrm/locale-translation-workflow.md`](../../.agents/skills/churchcrm/locale-translation-workflow.md).
The complete locale loop, branches, and plural handling are in [`docs/maintainers/locale-pipeline.md`](../../docs/maintainers/locale-pipeline.md).

## Mandatory rules

- Start every session on a fresh `locale/translate/**` branch (`node locale/scripts/locale-branch-manager.js --init`). Never reuse a prior branch.
- Run `npm run locale:missing` first. The queue `locale/terms/missing/` is checked in (the terms PR writes it); this refreshes it.
- After every locale, `node locale/scripts/locale-branch-manager.js --commit-and-push …` writes it into the repo (`npm run locale:apply`) and commits and pushes it, including that locale's emptied queue folder. Open a PR when done.
- If a step fails, stop and report.

## Related

- [Locale Translation skill](../../.agents/skills/churchcrm/locale-translation-workflow.md)
- [Locale pipeline](../../docs/maintainers/locale-pipeline.md)
- [Release process](../../docs/maintainers/release-process.md)
