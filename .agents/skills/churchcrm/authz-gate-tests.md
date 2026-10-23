---
title: Authorization gate tests
intent: How permission tests are written as persona gate rows, and how to add a gated route, page or persona. Load this when a route gains or changes a role middleware.
---

# Authorization gate tests

Design and rules: `docs/design/authz-test-strategy.md`. Spec hygiene: `cypress-testing.md`. Roles and middleware: `authorization-security.md`.

A permission test says which persona can or cannot reach a surface. It names no flag and no middleware, so the RBAC redesign (ADR 0002) changes persona data, not specs.

## Pieces

| File | Holds |
|---|---|
| `cypress/support/personas-data.js` | Each persona: seed user, login or API key, the flags it documents |
| `cypress/support/gates/<surface>.js` | Rows for one surface; `gates/index.js` joins them |
| `cypress/support/gates/personas.js` | Persona groups (`STAFF`, `except(...)`) for `allow` and `deny` lists |
| `cypress/support/gate-runner.js` | `runApiGates(persona)` and `runPageGates(persona)` turn rows into one `it()` each |
| `cypress/e2e/{api,ui}/personas/<persona>/` | One spec per persona that calls the runners |
| `cypress/support/gates/coverage.js` | Every PHP file under `src/` with a role middleware, and how its gate is tested |
| `cypress/e2e/api/private/security/gate-coverage.spec.js` | Fails on an unlisted gated file, a stale entry, or more pending entries than `MAX_PENDING` |

## Add a gated route or page

1. Add a row to `gates/<surface>.js`: `id`, `audience`, `classification`, `api: { method, url }` and/or `page`, `allow`, `deny`. `deny` includes the nearest neighbor (the persona with the adjacent grants but not this one) and `zero-permission-staff`. `allow` lists at least one persona that must succeed.
2. Probe a safe read. A route that mutates is `denyOnly: true`: the runner sends refused personas only, never an allowed one that would change data.
3. One row per enforcement boundary. A middleware added once to a route group gets one row; a middleware added route by route gets a row per route.
4. List the file in `COVERAGE` (`gates/coverage.js`) with `rows: [...]`, an existing `spec`, or a `pending` reason. A `pending` entry counts against `MAX_PENDING`; give a gate a row before raising the cap.
5. Run the persona specs and `gate-coverage.spec.js` on the test stack.

A denial is a 403 JSON body with `success: false` for the API, or a redirect to `/v2/access-denied` for a page. Only the access-denied page's own spec asserts the role text.

## Add a persona

1. Add the seed user to `cypress/data/seed.sql` (named columns, password `changeme`) and its API key to the Cypress configs; `npm run lint` (`cypress api keys`) checks every `*.api.key` against the seed.
2. Add the persona to `personas-data.js` with a stated intent and its `flags`.
3. Add it to the lists in the rows it belongs to, and add `cypress/e2e/{api,ui}/personas/<persona>/` specs that call the runners.

A persona that is a portal member signs in by session and has no API key.

## Change a gate

Change `allow` or `deny` in the row. If the middleware moved to another flag, the row stays and only `flags` in `personas-data.js` changes.
