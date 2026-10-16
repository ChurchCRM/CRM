---
title: "Testing"
intent: "Guidance for Cypress E2E tests and test workflows"
tags: ["testing","cypress","ci"]
prereqs: ["[[cypress-testing]]"]
complexity: "intermediate"
---

# Skill: Testing with Cypress

Rules for where specs go, seeded data, secrets and time zones: `cypress-testing.md`. This card covers spec patterns, log review and the test stack.

## Structure

- API specs: `cypress/e2e/api/private/{standard,admin,...}/`
- UI specs: `cypress/e2e/ui/[feature]/`
- Configs: `cypress/configs/docker.config.ts` (standard runner for `npm run test`, `test:open`, `test:api`, `test:ui` and the root/subdir CI jobs) and `cypress/configs/new-system.config.ts` (setup-wizard / fresh-install runner for `npm run test:new-system`). Every script passes `--config-file`; credentials live in these configs
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

## Running Tests

```bash
npm run test                                          # all Cypress specs, headless
npx cypress run --config-file cypress/configs/docker.config.ts --spec "cypress/e2e/ui/path/to/test.spec.js"
npm run test:open                                     # interactive
npm run test:php                                      # PHP regression tests, no Docker or database needed
```

Test stack:

```bash
npm run docker:test:start       # start test containers
npm run docker:test:stop        # stop, keep volumes
npm run docker:test:rebuild     # rebuild images
npm run docker:test:down        # remove containers and volumes
npm run docker:test:reset:db    # reload the seeded test database
```

Dev stack: `npm run docker:dev:start`, `docker:dev:stop`, `docker:dev:logs`.

## CI

- Push/PR CI validates what needs a clean-room, integrated, matrix, packaging or GitHub environment. A deterministic check that can run locally belongs in the commit/pre-push checkpoint first
- Exhaustive suites too expensive for every push (e.g. the full locale matrix) run in `.github/workflows/build-test-nightly.yml`
- Docker profiles: `test` and `ci` in `docker/docker-compose.yaml`; `docker/docker-compose.dev.yaml` is the dev stack; `ci-root` / `ci-subdir` / `ci-new-system` profiles in `docker/docker-compose.parallel.yaml`
- CI runs the `docker:ci:*` scripts from `package.json`
- Artifacts: `cypress-artifacts-{run_id}` (logs, screenshots, videos) and `cypress-reports-{branch}`, under Actions, workflow run, Artifacts

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
