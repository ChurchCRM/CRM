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
