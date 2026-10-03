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
- Helpers: `cypress/support/` — use `cy.setupAdminSession()` (or the current support helper) instead of inventing login. After `cy.request()`, the PHP session is dead: call `cy.freshAdminFormLogin(options)` from `cypress/support/test-env.js`. Do not add a one-line local wrapper when the only difference is those options.

## Must

- New endpoint or user-visible flow gets a test in the same PR (`maintainer-review-gates.md`)
- Clear `src/logs/$(date +%Y-%m-%d)-*.log` before a local run; read the log even if the spec passes
- Before push, run the specs the change touches locally: `npm run docker:test:reset:db`, then `cypress run` with a comma-separated `--spec` list (new specs plus the existing ones for pages you changed). The reset replaces the test database with the seed; never aim a run at a database that holds real data. Seed rows that specs approve or delete are gone until the next reset. Specs that need a record should create their own, not consume a seed row
- Stable selectors: `id`, `data-cy`, `name`, href/text — not visual utility classes
- Do not put optional demo values in Cypress seed if that would break the suite. Demo import is `src/admin/demo/config.json`
- No `.only` / `.skip` in committed specs
- `allowCypressEnv` is false. Do not call `Cypress.env()`. Secrets go through `cy.readEnv(key)` or `cy.rememberTestEnv(keys)` / `Cypress.testEnv(key)` in `cypress/support/test-env.js`. Public run flags (`rowCountGuard`, `LOCALE_TIER`) use `Cypress.expose()`; select the locale tier with `--expose LOCALE_TIER=full`
- No narrative comments that repeat the `it()` title. One line only when Cypress or CI would otherwise surprise the next editor.

## PDF reports

- Assert report text with `pdfText()` from `cypress/support/pdf-text.js`: `cy.request({ encoding: "binary" })`, inflate each FlateDecode stream with `DecompressionStream("deflate")`, collect the `(text) Tj` operands. No PDF parser dependency needed
- Assert a sequence after a unique marker, not `includes` alone, so the test proves which record printed what
- The directory report pre-selects classifications (Unassigned people never print); `DELETE /api/family/{id}` only unlinks members, use `?deleteMembers=true` when the test created people

```bash
npx cypress run --config-file cypress/configs/docker.config.ts \
  --spec "cypress/e2e/path/to/spec.js"
```

### In UI Specs, Read APIs With the Browser Session, Not an API Key <!-- learned: 2026-09-23 -->

`cy.makePrivateAdminAPICall()` (and the other `makePrivate*APICall` helpers) send `X-API-Key`;
the server answers with a fresh PHP session cookie, which Cypress stores and which replaces the
`setupAdminSession()` login. The next `cy.visit()` then lands on the login page and every
selector times out. Inside a UI spec use `cy.request("/api/…")` (same origin, cookies sent) to
read data between visits; keep the API-key helpers for API specs.
