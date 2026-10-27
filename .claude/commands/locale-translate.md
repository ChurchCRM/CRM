# /locale-translate

Translate missing ChurchCRM UI terms for one or all locales.

**Arguments:** `--list`, `--locale <code>`, or `--all`

Follow the agent rules in [`.agents/skills/churchcrm/locale-translation-workflow.md`](../../.agents/skills/churchcrm/locale-translation-workflow.md).
The complete locale loop, branches, uploads, and plural handling are in [`docs/maintainers/locale-pipeline.md`](../../docs/maintainers/locale-pipeline.md).

## Mandatory rules

- Start every session on a fresh `locale/translate/**` branch (`node locale/scripts/locale-branch-manager.js --init`). Never reuse a prior branch.
- Commit and push after every locale. The push with your own credentials uploads to POEditor.
- If a step fails, stop and report.

## Related

- [Locale Translation skill](../../.agents/skills/churchcrm/locale-translation-workflow.md)
- [Locale pipeline](../../docs/maintainers/locale-pipeline.md)
- [Release process](../../docs/maintainers/release-process.md)
