---
title: Cypress Testing
intent: How to add ChurchCRM E2E tests. Commands live in package.json.
---

# Cypress Testing

Layout and how-to: `cypress/README.md`.
Scripts: `package.json` (`test`, `test:api`, `test:ui`).
Config: `cypress/configs/docker.config.ts`.
App under test: `DEVELOPING.md` (`npm run docker:test:start`).

## Where tests go

- API: `cypress/e2e/api/`
- UI: `cypress/e2e/ui/`
- Helpers: `cypress/support/` — use `cy.setupAdminSession()` (or the current support helper) instead of inventing login

## Must

- New endpoint or user-visible flow gets a test in the same PR (`maintainer-review-gates.md`)
- Clear `src/logs/$(date +%Y-%m-%d)-*.log` before a local run; read the log even if the spec passes
- Stable selectors: `id`, `data-cy`, `name`, href/text — not visual utility classes
- Do not put optional demo values in Cypress seed if that would break the suite. Demo import is `src/admin/demo/config.json`
- No `.only` / `.skip` in committed specs
- No narrative comments that repeat the `it()` title. One line only when Cypress or CI would otherwise surprise the next editor.

## PDF reports

- Assert report text with `pdfText()` from `cypress/support/pdf-text.js`: `cy.request({ encoding: "binary" })`, inflate each FlateDecode stream with `DecompressionStream("deflate")`, collect the `(text) Tj` operands. No PDF parser dependency needed
- Assert a sequence after a unique marker, not `includes` alone, so the test proves which record printed what
- The directory report pre-selects classifications (Unassigned people never print); `DELETE /api/family/{id}` only unlinks members, use `?deleteMembers=true` when the test created people

```bash
npx cypress run --config-file cypress/configs/docker.config.ts \
  --spec "cypress/e2e/path/to/spec.js"
```
