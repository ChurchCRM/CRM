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
- Helpers: `cypress/support/` — use `cy.setupAdminSession()` (or the current support helper) instead of inventing login. After `cy.request()`, the PHP session is dead: call `cy.freshAdminFormLogin(options)` from `cypress/support/test-env.js`. Pass the options directly instead of wrapping them.

## Must

- New endpoint or user-visible flow gets a test in the same PR (`maintainer-review-gates.md`)
- Clear `src/logs/$(date +%Y-%m-%d)-*.log` before a local run; read the log even if the spec passes
- Before push, run the specs the change touches locally: `npm run docker:test:reset:db`, then `cypress run` with a comma-separated `--spec` list (new specs plus the existing ones for pages you changed). The reset replaces the test database with the seed, so aim runs only at the test stack. Seed rows that specs approve or delete are gone until the next reset. Specs that need a record should create their own, not consume a seed row
- Stable selectors: `id`, `data-cy`, `name`, href/text — not visual utility classes
- Optional demo values go in `src/admin/demo/config.json`, which keeps the Cypress seed stable
- Commit specs without `.only` / `.skip`
- `allowCypressEnv` is false. Secrets go through `cy.readEnv(key)` or `cy.rememberTestEnv(keys)` / `Cypress.testEnv(key)` in `cypress/support/test-env.js`. Public run flags (`rowCountGuard`, `LOCALE_TIER`) use `Cypress.expose()`; select the locale tier with `--expose LOCALE_TIER=full`
- The server counts days in `sTimeZone`; CI's browser is on UTC, a day ahead every evening. A spec that builds dates from the browser clock calls `cy.useChurchTimeZone()` in a root `before()` and `cy.useHostTimeZone()` in a root `after()` (`cypress/support/church-time-zone.js`). Check it locally with `TZ=UTC npx cypress run ...` between 8 pm and midnight Eastern
- Comment only a CI or Cypress trap, in one line.

## Database access

A behavior spec creates, changes, reads and deletes records through the API or the UI, the way a user or a client would. Take ids from the create response, assert on API reads or the page, clean up with the API (`cy.cleanupEvents()`, deactivate and `DELETE /api/ministries/ministries/{id}`). Never write a row the app would not write: a ministry inserted by SQL has no team, pool group or calendar.

`cy.dbQuery()` is for two things only:

- a dedicated schema spec: columns, UNIQUE keys, foreign keys and their ON DELETE rules, enum domains, migrations (`private.volunteer.setup.spec.js` for Volunteer v2). A constraint test goes in such a spec, not in a behavior spec
- a deterministic fixture or reset utility that does not stand in for the rule under test

```js
// Not: POST, then SELECT event_id FROM events_event WHERE event_title = ?
cy.makePrivateAdminAPICall("POST", "/api/events", event, 200).then(({ body }) => {
    eventId = body.eventId;
});
```

`npm run lint` runs `scripts/validate-cypress-db-access.js` over `cypress/`. A file may use SQL only when `cypress/db-access.json` approves it (`kind` `schema` or `fixture`, and a `reason`) or its baseline allows that many uses. The baseline only goes down: after moving a spec to the API, run `node scripts/validate-cypress-db-access.js --update`. `--report` prints the counts by category. If an endpoint lacks what a real client needs (an id, a field), fix the endpoint; do not add one only for tests.

## PDF reports

- Assert report text with `pdfText()` from `cypress/support/pdf-text.js`: `cy.request({ encoding: "binary" })`, inflate each FlateDecode stream with `DecompressionStream("deflate")`, collect the `(text) Tj` operands. No PDF parser dependency needed
- Assert a sequence after a unique marker, not `includes` alone, so the test proves which record printed what
- The directory report pre-selects classifications (Unassigned people never print); `DELETE /api/family/{id}` only unlinks members, use `?deleteMembers=true` when the test created people

```bash
npx cypress run --config-file cypress/configs/docker.config.ts \
  --spec "cypress/e2e/path/to/spec.js"
```

### In UI Specs, Read APIs With the Browser Session

Inside a UI spec, read data between visits with `cy.request("/api/…")` (same origin, cookies sent). The `makePrivate*APICall` helpers send `X-API-Key`, and the fresh PHP session cookie in the response replaces the `setupAdminSession()` login, so the next `cy.visit()` lands on the login page. Use the API-key helpers in API specs.
