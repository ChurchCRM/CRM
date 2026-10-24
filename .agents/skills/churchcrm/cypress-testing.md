---
title: Cypress Testing
intent: How to write, run and keep ChurchCRM Cypress specs, including permission (persona gate) tests. Load this for any spec work.
---

# Cypress Testing

Layout and how-to: `cypress/README.md`.
CI, shards, the test database, the test stack and the cleanup guard: `ci-test-infrastructure.md`.
Scripts: `package.json` (`test`, `test:api`, `test:ui`).
Config: `cypress/configs/docker.config.ts`.
App under test: `DEVELOPING.md` (`npm run docker:test:start`).

## Where tests go

Every new test is a Cypress E2E spec. Server logic is tested through its route with an API spec. The test stack has no PHPUnit and takes no new PHP test scripts; a PR that adds either is asked for a Cypress spec instead.

- API: `cypress/e2e/api/` (admin-only APIs in `api/private/admin/` named `private.admin.*`; the rest in `api/private/standard/` and its siblings)
- UI: `cypress/e2e/ui/` (admin-only pages in `cypress/e2e/ui-admin/` named `admin.*`)
- A spec holds one audience. An admin-only route or page is tested in an admin folder, never inside a standard spec
- Helpers: `cypress/support/` — use `cy.setupAdminSession()` (or the current support helper) instead of inventing login. After `cy.request()`, the PHP session is dead: call `cy.freshAdminFormLogin(options)` from `cypress/support/test-env.js`. Pass the options directly instead of wrapping them.

## Must

- New endpoint or user-visible flow gets a test in the same PR (`maintainer-review-gates.md`)
- Clear `src/logs/$(date +%Y-%m-%d)-*.log` before a local run; read the log even if the spec passes
- Before push, run the specs the change touches locally: `npm run docker:test:reset:db`, then `cypress run` with a comma-separated `--spec` list (new specs plus the existing ones for pages you changed). The reset replaces the test database with the seed, so aim runs only at the test stack. Seed rows that specs approve or delete are gone until the next reset. Specs that need a record should create their own, not consume a seed row
- Test the workflow a church person does, not CRUD. Name each UI spec for a role and a goal ("treasurer closes a deposit", "secretary merges two families") and walk the steps they take on the page: set up their data through the API, act in the UI, then assert what they see after a reload and what the API now holds. A feature or migration PR covers every flow the page offers: save, delete with its confirm, filter, report or export, the next page in the flow, and the user the role check turns away. An API spec proves the endpoint contract; it does not stand in for the page flow
- Test behavior, not presence. A test asserts what a user gets, saves, or is denied. Do not add tests that only check a page renders, a link navigates, a button fills inputs, or an element `not.exist` on a page that never had it. Negative tests are for permission and validation rules that the server enforces; one per rule. A role wrapper applied once to a route group gets one denial test; a wrapper added route by route gets one per route. Denial tests use the zero-permission user (`makePrivateNoPermAPICall`). Fold several checks of one page into one test
- A test never branches on missing data and passes by logging "skipping". Create the record in a `before` hook through the API, or drop the test
- Stable selectors: `id`, `data-cy`, `name`, href/text — not visual utility classes
- Optional demo values go in `src/admin/demo/config.json`, which keeps the Cypress seed stable
- Delete what a spec creates, in `after`, through the API. The row-count guard fails a spec file that leaves rows behind; a deposit you closed must be reopened before it can be deleted (`ci-test-infrastructure.md`)
- Permission checks are persona gate rows, never a flag-named helper in a spec (Permission tests, below)
- Commit specs without `.only` / `.skip`
- `allowCypressEnv` is false. Secrets go through `cy.readEnv(key)` or `cy.rememberTestEnv(keys)` / `Cypress.testEnv(key)` in `cypress/support/test-env.js`. Public run flags (`rowCountGuard`, `LOCALE_TIER`) use `Cypress.expose()`; select the locale tier with `--expose LOCALE_TIER=full`
- Mailpit lives at `Cypress.mailpitUrl()` for `cy.request()`; use the `mail:*` tasks where the stack may have none. The config resolves it from `MAILPIT_URL` or `MAILSERVER_GUI_PORT`, so a run on an isolated stack reaches that stack's Mailpit, not the developer's on 8025
- The server counts days in `sTimeZone`; CI's browser is on UTC, a day ahead every evening. A spec that builds dates from the browser clock calls `cy.useChurchTimeZone()` in a root `before()` and `cy.useHostTimeZone()` in a root `after()` (`cypress/support/church-time-zone.js`). Check it locally with `TZ=UTC npx cypress run ...` between 8 pm and midnight Eastern
- Comment only a CI or Cypress trap, in one line.

## Database access

A behavior spec creates, changes, reads and deletes records through the API or the UI, the way a user or a client would. Take ids from the create response, assert on API reads or the page, clean up with the API (`cy.cleanupEvents()`, deactivate and `DELETE /api/ministries/ministries/{id}`). Never write a row the app would not write: a ministry inserted by SQL has no team, pool group or calendar.

`cy.dbQuery()` is for two things only:

- a dedicated schema spec: columns, UNIQUE keys, foreign keys and their ON DELETE rules, enum domains, migrations (`private.volunteer.setup.spec.js` for Volunteer v2). A constraint test goes in such a spec, not in a behavior spec
- a deterministic fixture or reset utility that does not stand in for the rule under test: a named function in `cypress/support/` for a state no route writes, or for time passing (`member-sign-in-fixtures.js`: a passkey, a grant's last day, an expired link). It passes its own file, `cy.dbQuery(sql, params, { fixture: "cypress/support/<file>.js" })`

Settings go through `cy.setSystemConfig()` (the admin API checks them); capture with `cy.getSystemConfig()` and put back with `cy.restoreSystemConfig()`. An x-api-key request answers with its own session cookie, so a step between a sign-in and the pages it opens runs in `inOwnCookieJar()` (`cypress/support/cookie-jar.js`).

```js
// Not: POST, then SELECT event_id FROM events_event WHERE event_title = ?
cy.makePrivateAdminAPICall("POST", "/api/events", event, 200).then(({ body }) => {
    eventId = body.eventId;
});
```

`npm run lint` runs `scripts/validate-cypress-db-access.js` over `cypress/`. A file may use SQL only when `cypress/db-access.json` approves it (`kind` `schema` or `fixture`, and a `reason`) or its baseline allows that many uses. Approval is for schema specs and support utilities; a behavior spec sits on the baseline. The baseline only goes down: after moving a spec to the API, run `node scripts/validate-cypress-db-access.js --update`. `--report` prints the counts by category. The `db:query` and `db:upgradeBlock` tasks enforce the same list at run time: they refuse a spec the list does not name, and only an approved schema spec changes the schema or runs an upgrade block. If an endpoint lacks what a real client needs (an id, a field), fix the endpoint; do not add one only for tests.

## PDF reports

- Assert report text with `pdfText()` from `cypress/support/pdf-text.js`: `cy.request({ encoding: "binary" })`, inflate each FlateDecode stream with `DecompressionStream("deflate")`, collect the `(text) Tj` operands. No PDF parser dependency needed
- Assert a sequence after a unique marker, not `includes` alone, so the test proves which record printed what
- The directory report pre-selects classifications (Unassigned people never print); `DELETE /api/family/{id}` only unlinks members, use `?deleteMembers=true` when the test created people

## Read APIs in UI specs

Inside a UI spec, read data between visits with `cy.request("/api/…")` (same origin, cookies sent). The `makePrivate*APICall` helpers send `X-API-Key`, and the fresh PHP session cookie in the response replaces the `setupAdminSession()` login, so the next `cy.visit()` lands on the login page. Use the API-key helpers in API specs.

## Run a spec

```bash
npx cypress run --config-file cypress/configs/docker.config.ts --spec "cypress/e2e/ui/path/to/test.spec.js"
```

Stack, reset and scripts: `ci-test-infrastructure.md`.

## Layout and helpers

- API specs: `cypress/e2e/api/private/{standard,admin,...}/`
- UI specs: `cypress/e2e/ui/[feature]/`
- Configs: `cypress/configs/docker.config.ts` (every CI job and `npm run test*`) and `cypress/configs/new-system.config.ts` (setup wizard, `npm run test:new-system`). Every script passes `--config-file`; credentials live in these configs
- Support: `cypress/support/ui-commands.js`, `cypress/support/api-commands.js` (imported from `cypress/support/e2e.js`)
- CI logs: `cypress-terminal-report` captures browser console output into `cypress/logs/`, uploaded as GitHub Actions artifacts

## API Specs

Call endpoints through the helper commands in `cypress/support/api-commands.js` (`cy.makePrivateAdminAPICall`, `cy.makePrivateUserAPICall`, the role-specific `makePrivate*APICall` variants, and `cy.apiRequest` for non-failing requests):

```javascript
cy.makePrivateAdminAPICall("POST", "/api/payments", payload, 200)
cy.makePrivateUserAPICall("GET", "/api/events", null, 200)
cy.apiRequest({ method: "GET", url: "/api/events", failOnStatusCode: false })
```

Cover each endpoint with:

1. Success: valid payload, 200, expected data structure and property types
2. Validation: invalid input (bad dates, missing fields), 400
3. Auth: 401 unauthenticated, 403 insufficient role
4. Not found: 404
5. Edge cases: null values, empty arrays, boundaries

```javascript
describe('POST /api/payments', () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it('creates a payment with valid data', () => {
        const payload = { familyId: 1, amount: 100.00, method: 'CHECK', date: '2024-01-15' };

        cy.makePrivateAdminAPICall("POST", "/api/payments", payload, 200)
            .then((response) => {
                expect(response.body).to.have.property('success', true);
                expect(response.body.data).to.have.property('paymentId');
            });
    });

    it('returns 400 for an invalid date', () => {
        cy.makePrivateAdminAPICall("POST", "/api/payments",
            { familyId: 1, amount: 100.00, method: 'CHECK', date: 'invalid-date' }, 400);
    });

    it('returns 401 when not authenticated', () => {
        cy.clearCookies();
        cy.apiRequest({ method: "POST", url: "/api/payments", body: {}, failOnStatusCode: false })
            .then((response) => {
                expect(response.status).to.equal(401);
            });
    });
});
```

## UI Specs

Log in with `cy.session()`-based commands in `beforeEach`:

```javascript
describe('Feature X', () => {
    beforeEach(() => {
        cy.setupAdminSession();
        cy.visit('/path/to/page');
    });

    it('completes the workflow', () => {
        cy.get('#element-id').click();
        cy.get('#result').should('exist');
    });
});
```

Session commands (credentials come from the config's `env`):

- `cy.setupAdminSession()` (`admin.*`)
- `cy.setupStandardSession()` (`standard.*`)
- `cy.setupNoFinanceSession()` (`nofinance.*`)
- Other role sessions: `cypress/support/ui-commands.js`

Add a credential needed by both runners to both `cypress/configs/docker.config.ts` and `cypress/configs/new-system.config.ts`. Credentials stay in config, never in spec files.

Other helpers: `cy.typeInQuill(selector, text)`, `cy.tomSelect*`, `cy.waitForNotification()`.

Spec structure:

- Select by element id, `data-cy` or `name`; keep those ids stable in templates
- Test complete user workflows end to end
- Name tests for the behavior they check
- Keep each test independent of state left by another
- Delete commented-out code and TODOs

## Debugging 500 Errors

Find the root cause of every HTTP 500 in a spec.

1. Clear logs: `rm -f src/logs/$(date +%Y-%m-%d)-*.log`
2. Run the failing spec
3. Read `src/logs/$(date +%Y-%m-%d)-php.log` and `-app.log`

Common causes: `HttpNotFoundException` (wrong route path, e.g. `/api/family/` vs `/api/families/`), `PropelException` (ORM query, missing column, type mismatch), `TypeError` (null where an object is expected), missing middleware or wrong middleware order.

Review both logs after every run, including passing runs, to catch silently logged exceptions.

## Keep Tests in Sync with Code

Update the specs in the same commit as the code change:

- Required field added or removed: update validation specs
- API response schema changed: update response assertions
- Form layout or labels moved: update element existence and navigation steps
- Dropdown options changed: update selector assertions

Workflow: change the code, run the existing specs, update them to the new behavior with the same intent, rerun, review code and spec diffs together, commit both. Fix or rewrite failing specs; commit without `skip()` / `.only()` and without commented-out tests.

New API endpoints ship with specs in `cypress/e2e/api/private/standard/` (standard-user endpoints) or `cypress/e2e/api/private/admin/` (admin-only), covering success, response shape and 401.

Before committing: clear logs, run the touched specs, read both logs, commit once specs pass and logs are clean.

## Timing-Based Flakiness

| Pattern | Rule |
|---------|------|
| `cy.intercept()` placement | Register before `cy.visit()` or the triggering action |
| Mutations | `cy.wait("@alias")` on every POST/PUT/DELETE, including reset/cleanup calls |
| `select()` | Start from a different baseline value; selecting the current value fires no `change` event |
| `cy.contains()` | Scope to a container id; a bare `cy.contains("text")` can match sidebar nav |

Checklist for a spec that saves or loads state:

- [ ] `cy.intercept()` registered before `cy.visit()` or the interaction
- [ ] Every mutation has a `cy.wait("@alias")`, reset calls included
- [ ] `select()` tests start from a known different value
- [ ] `cy.contains()` scoped to a container

## Permission tests

Design and rules: `docs/design/authz-test-strategy.md`. Roles and middleware: `authorization-security.md`.

A permission test says which persona can or cannot reach a surface. It names no flag and no middleware, so the RBAC redesign (ADR 0002) changes persona data, not specs.

### Pieces

| File | Holds |
|---|---|
| `cypress/support/personas-data.js` | Each persona: seed user, login or API key, the flags it documents |
| `cypress/support/gates/<surface>.js` | Rows for one surface; `gates/index.js` joins them |
| `cypress/support/gates/personas.js` | Persona groups (`STAFF`, `except(...)`) for `allow` and `deny` lists |
| `cypress/support/gate-runner.js` | `runApiGates(persona)` and `runPageGates(persona)` turn rows into one `it()` each |
| `cypress/e2e/{api,ui}/personas/<persona>/` | One spec per persona that calls the runners |
| `cypress/support/gates/coverage.js` | Every PHP file under `src/` with a role middleware, and how its gate is tested |
| `cypress/e2e/api/private/security/gate-coverage.spec.js` | Fails on an unlisted gated file, a stale entry, or more pending entries than `MAX_PENDING` |

### Add a gated route or page

1. Add a row to `gates/<surface>.js`: `id`, `audience`, `classification`, `api: { method, url }` and/or `page`, `allow`, `deny`. `deny` includes the nearest neighbor (the persona with the adjacent grants but not this one) and `zero-permission-staff`. `allow` lists at least one persona that must succeed.
2. Probe a safe read. A route that mutates is `denyOnly: true`: the runner sends refused personas only, never an allowed one that would change data.
3. One row per enforcement boundary. A middleware added once to a route group gets one row; a middleware added route by route gets a row per route.
4. List the file in `COVERAGE` (`gates/coverage.js`) with `rows: [...]`, an existing `spec`, or a `pending` reason. A `pending` entry counts against `MAX_PENDING`; give a gate a row before raising the cap.
5. Run the persona specs and `gate-coverage.spec.js` on the test stack.

A denial is a 403 JSON body with `success: false` for the API, or a redirect to `/v2/access-denied` for a page. Only the access-denied page's own spec asserts the role text.

### Add a persona

1. Add the seed user to `cypress/data/seed.sql` (named columns, password `changeme`) and its API key to the Cypress configs; `npm run lint` (`cypress api keys`) checks every `*.api.key` against the seed.
2. Add the persona to `personas-data.js` with a stated intent and its `flags`.
3. Add it to the lists in the rows it belongs to, and add `cypress/e2e/{api,ui}/personas/<persona>/` specs that call the runners.

A persona that is a portal member signs in by session and has no API key.

### Change a gate

Change `allow` or `deny` in the row. If the middleware moved to another flag, the row stays and only `flags` in `personas-data.js` changes.
