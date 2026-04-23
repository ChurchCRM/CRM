# Skill: Cypress Testing

## Context
ChurchCRM uses Cypress for UI and API testing. This skill covers test organization, session management, API helpers, and best practices for writing reliable tests.

---

## Test File Organization

### UI Tests
**Location:** `cypress/e2e/ui/[feature]/`

```
cypress/e2e/ui/
├── users/
│   ├── create-user.cy.ts
│   ├── edit-user.cy.ts
│   └── delete-user.cy.ts
├── groups/
│   ├── manage-groups.cy.ts
│   └── group-permissions.cy.ts
└── finance/
    ├── record-donation.cy.ts
    └── generate-reports.cy.ts
```

### API Tests
**Location:** `cypress/e2e/api/private/[role]/`

```
cypress/e2e/api/private/
├── admin/
│   ├── system/config.spec.ts
│   └── user-management.spec.ts
└── standard/
    ├── profile.spec.ts
    └── family.spec.ts
```

## Session-Based Login Pattern (REQUIRED)

### Modern Pattern (Cypress 13+)

Use `cy.session()` for efficient login caching across tests:

```typescript
/**
 * ✅ CORRECT - Modern pattern (REQUIRED for all new tests)
 */
describe('User Management', () => {
    beforeEach(() => {
        // Setup admin session - credentials read from config
        cy.setupAdminSession();
        cy.visit('/UserList.php');
    });

    it('should display user list', () => {
        cy.get('table tbody tr').should('have.length.greaterThan', 0);
    });

    it('should create new user', () => {
        cy.contains('Add New User').click();
        cy.get('input[name="userName"]').type('testuser');
        cy.get('form').submit();
        cy.get('.alert-success').should('be.visible');
    });
});
```

### Old Pattern (DEPRECATED)

❌ **DO NOT USE:**
```typescript
// WRONG - Deprecated login method
describe('User Management', () => {
    it('should create user', () => {
        cy.loginAdmin('/UserList.php');  // ❌ REMOVED - Use cy.setupAdminSession()
    });
});
```

## Session Commands

### Available Session Setup Commands

```typescript
// Admin user (full access)
cy.setupAdminSession();

// Standard user (basic permissions)
cy.setupStandardSession();

// User without finance permission
cy.setupNoFinanceSession();
```

### Configuration Location

Credentials live in the Cypress config files under `cypress/configs/` that the
`npm run test*` scripts pass via `--config-file`:

- `cypress/configs/docker.config.ts` — used by `npm run test`, `test:open`,
  `test:api`, `test:ui`, and the `test-root` / `test-subdir` CI matrix
- `cypress/configs/new-system.config.ts` — used by `npm run test:new-system`
  and the `test-new-system` CI job (has its own `env` block with an admin
  account because this job boots from a fresh install)

There is **no** root `cypress.config.ts` in this repo — don't add one and
don't expect Cypress to auto-detect one. Every script passes an explicit
`--config-file`.

```typescript
env: {
    // Admin account
    'admin.username': 'admin',
    'admin.password': 'changeme',

    // Standard user
    'standard.username': 'tony.wade@example.com',
    'standard.password': 'basicjoe',

    // User without finance permission
    'nofinance.username': 'judith.matthews@example.com',
    'nofinance.password': 'noMoney$',
}
```

> ⚠️ **CRITICAL: keep shared credentials in sync across configs.** <!-- learned: 2026-04-13 -->
> If you add a new test user that the `test-new-system` job also needs, the
> credential has to be added to **both** `cypress/configs/docker.config.ts`
> and `cypress/configs/new-system.config.ts`. If you update only one,
> tests will pass in one CI job but fail in the other, and the failure
> message from `setupLoginSession` — usually
> `Admin credentials not configured in cypress config env` — does not tell
> you which config file is missing the key. Always grep both files when
> adding a new `*.username` / `*.password` entry.

**CRITICAL:**
- ❌ DO NOT hardcode credentials in test files
- ❌ DO NOT add commented-out tests or TODO comments
- ✅ Configuration-driven approach prevents secrets leaking into git
- ✅ Update `cypress/configs/docker.config.ts` AND
  `cypress/configs/new-system.config.ts` together when the credential is
  used by both jobs

### cy.request() API Calls Reset PHP Sessions (CRITICAL) <!-- learned: 2026-03-27 -->

`cy.request()` (used by `makePrivateAdminAPICall`, `makePrivateUserAPICall`) sends and
receives cookies automatically. PHP's `session_start()` runs on every API request and
sends a `Set-Cookie: PHPSESSID=xxx` response that **overwrites the browser's session cookie**.

This means: after ANY `cy.request()` / `makePrivateAdminAPICall()` call, the browser
session established by `cy.setupAdminSession()` is **invalidated**. A subsequent
`cy.visit()` will redirect to `/session/begin` (login page).

**Fix: Direct login after API calls (only needed before `cy.visit()`)**

`freshAdminLogin` is a **local helper function** — it is NOT a registered `cy.*` command
and does NOT exist in `cypress/support/`. Copy it inline into the spec that needs it:

```javascript
// Local helper — NOT a cy.* command. Copy into your spec file. Only needed for UI tests.
function freshAdminLogin() {
    cy.clearCookies();
    cy.visit("/session/begin");
    cy.get("input[name=User]").type(Cypress.env("admin.username"));
    cy.get("input[name=Password]").type(Cypress.env("admin.password") + "{enter}");
    cy.url().should("not.include", "/session/begin");
}

it("test that needs API setup then browser visit", () => {
    // API calls (these reset the PHP session)
    cy.makePrivateAdminAPICall("POST", "/api/groups/1/properties/5", {}, 200);

    // MUST re-login before cy.visit() — session was reset by cy.request()
    freshAdminLogin();
    cy.visit("/groups/view/1");
    cy.get("#some-element").should("exist");
});
```

**Pure API tests need NO login setup** — `makePrivateAdminAPICall` authenticates via
API key header (`x-api-key`), not browser cookies. If your spec has no `cy.visit()`,
omit `freshAdminLogin()` entirely.

### `allowedStatuses` must match what the API actually returns <!-- learned: 2026-03-29 -->

Only pass status codes the endpoint genuinely returns. Adding extra codes defensively masks real setup failures.

```javascript
// ✅ group addperson is idempotent — always returns 200 (member or not)
cy.makePrivateAdminAPICall("POST", `/api/groups/${groupId}/addperson/${personId}`, { RoleID: 1 }, [200]);

// ✅ add-if-not-exists endpoint returns 409 when already present
cy.makePrivateAdminAPICall("POST", "/api/groups/1/properties/5", {}, [200, 409]);

// ✅ cleanup teardown: allow 404 in case item was already deleted
cy.makePrivateAdminAPICall("DELETE", "/api/groups/1/properties/5", null, [200, 404]);

// ❌ defensive extra codes when API never returns them — hides real 422/500
cy.makePrivateAdminAPICall("POST", `/api/groups/${groupId}/addperson/${personId}`, { RoleID: 1 }, [200, 409, 422]);
```

Check the actual route implementation before choosing `allowedStatuses`.

**Rules:**
- ❌ NEVER `cy.visit()` after `cy.request()` / `makePrivateAdminAPICall()` without re-login
- ❌ `cy.setupAdminSession({ forceLogin: true })` is NOT sufficient — it still uses `cy.session()` cache
- ❌ `before()` hooks with API calls will poison cookies for ALL subsequent tests
- ✅ Use `freshAdminLogin()` (clear cookies + direct form login) before `cy.visit()`
- ✅ Each test should be self-contained — set up its own data, then re-login, then visit

### `allowedStatuses` must match what the API actually returns <!-- learned: 2026-03-29 -->

Only pass status codes the endpoint genuinely returns. Adding extra codes defensively masks real setup failures.

```javascript
// ✅ group addperson is idempotent — always returns 200 (member or not)
cy.makePrivateAdminAPICall("POST", `/api/groups/${groupId}/addperson/${personId}`, { RoleID: 1 }, [200]);

// ✅ add-if-not-exists endpoint returns 409 when already present
cy.makePrivateAdminAPICall("POST", "/api/groups/1/properties/5", {}, [200, 409]);

// ✅ cleanup teardown: allow 404 in case item was already deleted
cy.makePrivateAdminAPICall("DELETE", "/api/groups/1/properties/5", null, [200, 404]);

// ❌ defensive extra codes when API never returns them — hides real 422/500
cy.makePrivateAdminAPICall("POST", `/api/groups/${groupId}/addperson/${personId}`, { RoleID: 1 }, [200, 409, 422]);
```

Check the actual route implementation before choosing `allowedStatuses`.

### `freshAdminLogin` Is a Local Function, NOT a Cypress Command <!-- learned: 2026-03-29 -->

`freshAdminLogin()` is a **plain JS function** defined locally in individual spec files (e.g. `standard.group.properties.spec.js`). It is **not** registered via `Cypress.Commands.add`.

- ❌ `cy.freshAdminLogin()` — throws `TypeError: cy.freshAdminLogin is not a function`, fails the entire describe block
- ✅ `freshAdminLogin()` — call as a plain JS function, only inside the spec file that defines it

**API-only tests need no login at all.** `makePrivateAdminAPICall` / `makePrivateUserAPICall` authenticate via `x-api-key` header — no session or cookies needed. Never add `freshAdminLogin()` or `setupAdminSession()` to `beforeEach` in a pure API spec.

```javascript
// ✅ CORRECT — API test, no login setup needed
describe("People Without Email API", () => {
    beforeEach(() => {
        cy.makePrivateAdminAPICall("GET", "/api/persons/email/without", "", 200).as("withoutEmail");
        // no login needed — API key auth only
    });
});

// ❌ WRONG — cy.freshAdminLogin() is not a command, crashes beforeEach for every test
describe("People Without Email API", () => {
    beforeEach(() => {
        cy.makePrivateAdminAPICall("GET", "/api/persons/email/without", "", 200).as("withoutEmail");
        cy.freshAdminLogin(); // TypeError: cy.freshAdminLogin is not a function
    });
});
```

### UI Tests Must Not Call APIs After Login <!-- learned: 2026-03-27 -->

UI tests have a strict boundary: **no `cy.request()` / `makePrivateAdminAPICall()` after the user is logged in.** The three allowed phases are:

| Phase | What's allowed |
|-------|----------------|
| **Before login** | API calls to set up data state |
| **Browser session** | `freshAdminLogin()` then pure UI interactions only |
| **After assertions** | API calls for teardown/cleanup (end of test or `afterEach`) |

```javascript
// ✅ CORRECT structure for a UI test that needs data setup
it("assigns a property and it appears in the list", () => {
    // Phase 1: API setup (before login — PHP session resets don't matter)
    cy.wrap(null).as("prop");
    cy.makePrivateAdminAPICall("GET", "/api/groups/properties", null, 200)
        .then(resp => {
            const p = resp.body[0];
            cy.wrap({ id: p.ProId, name: p.ProName }).as("prop");
        });

    // Phase 2: Login — clears cy.request() PHP session, establishes browser session
    freshAdminLogin();

    // Phase 3: UI only — no more cy.request() calls
    cy.get("@prop").then(prop => {
        cy.visit(`/groups/view/1`);
        cy.get("#group-property-select").select(String(prop.id));
        cy.get("#assign-group-property-btn").click();
        cy.contains(prop.name).should("be.visible");
    });

    // Phase 4: Cleanup at end (after all UI assertions)
    cy.makePrivateAdminAPICall("DELETE", "/api/groups/1/properties/5", null, [200, 404]);
});

// ✅ CORRECT: beforeEach clears stale state AND sets up fresh state
// (no afterEach — see "State Cleanup: prefer beforeEach" section below).
describe("Remove property tests", () => {
    beforeEach(() => {
        // 1. Clean up any stale state from a previous (possibly failed) run.
        //    Accept 404 so a fresh DB is also fine.
        cy.makePrivateAdminAPICall("DELETE", "/api/groups/1/properties/5", null, [200, 404]);

        // 2. Create the fixture this test needs. Accept 409 in case a
        //    concurrent test left a duplicate.
        cy.makePrivateAdminAPICall("POST", "/api/groups/1/properties/5", {}, [200, 409]);

        // 3. Re-establish session AFTER all API calls — cy.request resets
        //    PHP session cookies, so this has to be last.
        freshAdminLogin();
    });

    it("shows Remove button", () => {
        cy.visit("/groups/view/1"); // pure UI from here
        cy.get(".remove-group-property-btn").should("exist");
    });
});

// ❌ WRONG: API call after setupAdminSession
describe("Bad pattern", () => {
    beforeEach(() => cy.setupAdminSession());

    it("broken test", () => {
        cy.makePrivateAdminAPICall("POST", "/api/groups/1/properties/5", {}, 200); // ❌ resets PHP session
        cy.visit("/groups/view/1"); // ❌ will redirect to login page
    });
});
```

**Conditional skipping with aliases:** When API setup is conditional, set a default alias before the `.then()` chain so downstream `cy.get("@alias")` never throws:

```javascript
cy.wrap(null).as("prop");  // default — tests check for null and skip
cy.makePrivateAdminAPICall("GET", "/api/groups/properties", null, 200)
    .then(resp => {
        if (!resp.body.length) return;
        cy.wrap(resp.body[0]).as("prop"); // overwrites default if data exists
    });
freshAdminLogin();
cy.get("@prop").then(prop => {
    if (!prop) { cy.log("skipping"); return; }
    // UI interactions
});
```

**Bootbox button clicks:** Use `cy.wrap($btn).click()` not `$btn.trigger("click")` — jQuery's `.trigger()` may not dispatch real DOM events that delegated handlers (`$(document).on(...)`) will catch in Cypress. Use bootbox's own semantic classes (always present, locale-independent) rather than text matching or Bootstrap classes:

```javascript
// ✅ CORRECT — bootbox always adds these classes regardless of label text or locale
cy.get(".bootbox-accept").should("be.visible").click();  // confirm / destructive action button
cy.get(".bootbox .btn-secondary").click({ force: true }); // cancel button (use force: true)

// ❌ WRONG — text match breaks with i18n; .btn-secondary is not always unique
cy.get(".bootbox").contains("button", "Cancel").click();
$btn.trigger("click");
```

**Bootbox Cancel: Assert Side Effects, Not Dialog Visibility <!-- learned: 2026-03-27 -->**

After clicking Cancel on a bootbox dialog, **do NOT assert that the dialog is gone** (`should("not.be.visible")` or `should("not.exist")`). Bootstrap 5 modal hide is asynchronous — the CSS fade-out keeps `.show` on the backdrop during the transition, causing these assertions to fail intermittently or permanently.

Instead, assert the **side effect** (the action was not taken):

```javascript
// ✅ CORRECT — assert the meaningful outcome: property was NOT removed
it("Cancel leaves property intact", () => {
    cy.get(".remove-group-property-btn").first().then(($btn) => {
        const name = $btn.data("pro-name");
        cy.wrap($btn).click();
        cy.get(".bootbox").should("be.visible");
        cy.get(".bootbox .btn-secondary").click({ force: true });
        // Assert the side effect: button still exists (property not removed)
        cy.get(`.remove-group-property-btn[data-pro-name="${name}"]`).should("exist");
    });
});

// ✅ CORRECT — confirm: assert the dialog was visible, click accept, then assert the side effect
it("Confirm removes property", () => {
    cy.intercept("DELETE", "**/api/groups/1/properties/*").as("removeProperty");
    cy.get(".remove-group-property-btn").first().then(($btn) => {
        const name = $btn.data("pro-name");
        cy.wrap($btn).click();
        cy.get(".bootbox-accept").should("be.visible").click();
        cy.wait("@removeProperty").its("response.statusCode").should("eq", 200);
        cy.get(`.remove-group-property-btn[data-pro-name="${name}"]`).should("not.exist");
    });
});

// ❌ WRONG — Bootstrap 5 async fade-out means .bootbox may still have .show class
cy.get(".bootbox").should("not.be.visible");  // times out during CSS transition
cy.get(".bootbox").should("not.exist");        // fails while modal is fading
```

**Card title selectors:** Use `h3.card-title` not `.card-title` alone. Tabler's sidebar nav uses `card-title` in its hierarchy; `cy.contains(".card-title", text)` may resolve to a hidden sidebar nav link instead of the page card heading:

```javascript
// ✅ CORRECT — h3 elements only exist in page card headers, not sidebar nav
cy.contains("h3.card-title", "Properties").should("be.visible");

// ❌ WRONG — .card-title also matches sidebar nav structure; may find collapsed nav items
cy.contains(".card-title", "Properties").should("be.visible");
```

### State Cleanup: prefer `beforeEach`, not `afterEach` <!-- learned: 2026-04-13 -->

Current Cypress best practice (2024+) is to clean up state at the **start** of the next test, not at the end of the previous one. Reason: `afterEach` does not run if a test crashes mid-way (uncaught exception, lost connection, process kill). Cleanup that only runs in `afterEach` can therefore leave the DB in a bad state that breaks every subsequent test.

```javascript
// ✅ CORRECT — cleanup and fixture setup both live in beforeEach
describe("Group property management", () => {
    beforeEach(() => {
        // 1. Delete anything a prior run (possibly failed mid-test) left behind.
        //    Accept 404 so a truly fresh DB also works.
        cy.makePrivateAdminAPICall("DELETE", "/api/groups/1/properties/5", null, [200, 404]);

        // 2. Re-create the fixture this test needs.
        cy.makePrivateAdminAPICall("POST", "/api/groups/1/properties/5", {}, [200, 409]);

        // 3. Re-establish session last — cy.request resets PHP session cookies.
        freshAdminLogin();
    });

    it("shows Remove button", () => {
        cy.visit("/groups/view/1");
        cy.get(".remove-group-property-btn").should("exist");
    });
});

// ❌ WRONG — cleanup only runs if the previous test reached the end successfully
describe("Group property management", () => {
    beforeEach(() => {
        cy.makePrivateAdminAPICall("POST", "/api/groups/1/properties/5", {}, [200, 409]);
        freshAdminLogin();
    });

    afterEach(() => {
        // This silently skips when a test crashes, leaving stale state behind.
        cy.makePrivateAdminAPICall("DELETE", "/api/groups/1/properties/5", null, [200, 404]);
    });
});
```

`afterEach` / `after` are still fine for **non-state** housekeeping (printing debug info, collecting artifacts). Don't use them to reset application state.

### When to use `cy.setupAdminSession({ forceLogin: true })` <!-- learned: 2026-04-13 -->

`setupAdminSession()` uses `cy.session()` to cache the admin login across tests — normally you only pay the login cost once per run. The `{ forceLogin: true }` option generates a **new** unique session name (`admin-session-<timestamp>-<random>`), which bypasses the cache and forces a full re-login.

**Only use `forceLogin: true` when you have invalidated the cached session.** The canonical case is immediately after a `cy.request()` / `cy.makePrivateAdminAPICall()` call, because ChurchCRM's PHP backend rotates the session cookie on every request (see "cy.request() API Calls Reset PHP Sessions" above). If you call `cy.setupAdminSession()` without `forceLogin` at that point, the session-cache validate callback will pass (the CRM cookie *technically* exists) but point at a dead PHP session, and your next `cy.visit(...)` will silently redirect to the login page.

```javascript
// ✅ CORRECT — re-establish session after API mutation
it("creates a user via the form", () => {
    cy.makePrivateAdminAPICall("DELETE", "/admin/api/user/25", null, [200, 404]);
    cy.setupAdminSession({ forceLogin: true });  // required — API call above invalidated the session
    cy.visit("UserEditor.php?NewPersonID=25");
    // ...
});

// ❌ WRONG — forceLogin with no preceding API call wastes ~3–5 seconds per test
describe("User listing", () => {
    beforeEach(() => {
        cy.setupAdminSession({ forceLogin: true });  // ❌ unnecessary; cache was valid
    });
});
```

**Performance note:** each `forceLogin: true` adds ~3–5 seconds of wall time because it runs the full UI login (`cy.visit('/login')` + type username/password). Don't sprinkle it defensively — the skill doc's "UI Tests Must Not Call APIs After Login" pattern above is the cheaper alternative: do all your API setup **before** the first `setupAdminSession()`, not after.

**`cy.contains()` Must Be Scoped to a Container <!-- learned: 2026-03-27 -->**

`cy.contains(text)` without a container scope searches the **entire DOM** — including collapsed Tabler sidebar nav links (`<a.nav-link.flex-fill>` inside `div.collapse`). Even a specific selector like `cy.contains("h3.card-title", text)` can still resolve to a hidden sidebar element.

The safe pattern is to scope to a stable container ID:

```javascript
// ✅ CORRECT — scoped to the specific card
cy.get("#group-properties-card").contains(prop.name).should("be.visible");

// ❌ WRONG — may match a sidebar nav link with the same text (even if selector is specific)
cy.contains(prop.name).should("be.visible");
cy.contains("h3.card-title", "Properties").should("be.visible"); // may still match nav
```

**Adding IDs to PHP templates for testability:** When an element lacks a stable selector, add an `id` attribute to the PHP template rather than using fragile CSS paths:

```php
<!-- src/groups/views/group-view.php -->
<!-- ✅ Add id to the specific card so tests can scope to it -->
<div class="card mb-3" id="group-properties-card">
```

**Dropdown Toggle Must Be Scoped to Its Container <!-- learned: 2026-03-27 -->**

`[data-bs-toggle="dropdown"]` is too generic — it matches ALL dropdown triggers on the page, including navbar items (e.g., `#upgradeMenu`). Always scope it to the relevant dropdown container:

```javascript
// ✅ CORRECT — scoped to the table row's dropdown
cy.get(".delete-property-btn").first()
    .closest(".dropdown")
    .find("[data-bs-toggle='dropdown']")
    .click();

// ❌ WRONG — matches the first dropdown on page (often a navbar item, not the table row)
cy.get("[data-bs-toggle='dropdown']").first().click();
cy.get(".delete-property-btn").first().siblings("[data-bs-toggle='dropdown']").click();
```

### Subdirectory-Aware `cy.intercept()` Patterns (CRITICAL) <!-- learned: 2026-03-27 -->

ChurchCRM runs both at root (`/`) and in a subdirectory (`/churchcrm/`). CI tests run **both configurations**. `cy.intercept()` URL patterns **must** use `**` glob prefixes so they match regardless of the installation path.

```javascript
// ✅ CORRECT — matches both /api/... and /churchcrm/api/...
cy.intercept("DELETE", `**/api/groups/${groupID}/properties/*`).as("removeProperty");
cy.intercept("GET", "**/api/people/properties/definition/*").as("getDef");
cy.intercept("POST", "**/api/groups/*/members").as("addMember");

// ❌ WRONG — only matches root installation, fails on /churchcrm/ subdirectory
cy.intercept("DELETE", `/api/groups/${groupID}/properties/*`).as("removeProperty");
cy.intercept("GET", "/api/people/properties/definition/*").as("getDef");
```

**Why this matters:** In subdirectory mode, the browser sends requests to `/churchcrm/api/groups/1/properties/5`, but an intercept pattern of `/api/groups/1/properties/*` won't match — the intercept never fires and `cy.wait("@alias")` times out.

**Rule:** Always prefix `cy.intercept()` URL patterns with `**/` when matching API paths. This applies to ALL HTTP methods (GET, POST, PUT, DELETE, PATCH).

**Note:** This does NOT apply to `cy.visit()` or `cy.request()` — those use `baseUrl` from config and handle the prefix automatically. Only `cy.intercept()` needs the `**` glob because it matches against the full request URL.

## Editable Table Cells: Names Render in Input Values <!-- learned: 2026-04-12 -->

When a table renders option/option names in `<input>` elements (like the OptionManager and many Tabler tables with inline editing), `cy.contains("Member")` does NOT find the value — `cy.contains` searches text content, not input value attributes.

```javascript
// ❌ WRONG — searches text content, won't find value attributes
cy.get("#optionsTable tbody").should("contain", "Member");

// ✅ CORRECT — query the input by its value attribute
cy.get('#optionsTable tbody input.option-name-input[value="Member"]').should("exist");
```

This applies to any test that asserts data in a table where cells use `<input value="...">` for inline editing.

## Preventing Flaky Tests (Timing & State) <!-- learned: 2026-04-07 -->

Flaky tests almost always come from one of four root causes. Each has a mandatory fix pattern.

---

### 1. `cy.intercept()` Must Be Registered BEFORE the Triggering Action

`cy.intercept()` only captures requests that fire **after** it is registered. If you register it after the action (even a line after), the network call may already be in flight and the alias never resolves — `cy.wait()` hangs until timeout.

**Rule:** Place all `cy.intercept()` calls **before `cy.visit()`**, or at minimum before the element interaction that sends the request.

```javascript
// ✅ CORRECT — intercept registered before the interaction
it("Can change table page length", () => {
    cy.intercept("POST", "**/api/user/*/setting/ui.table.size").as("saveTableSize");
    cy.visit("/v2/user/3");
    cy.get('#settingsNav a[href="#tab-appearance"]').click();
    cy.get("#tablePageLength").select("50");
    cy.wait("@saveTableSize");
});

// ❌ WRONG — intercept registered after click; request may have already fired
it("Can change table page length", () => {
    cy.visit("/v2/user/3");
    cy.get('#settingsNav a[href="#tab-appearance"]').click();
    cy.intercept("POST", "**/api/user/*/setting/ui.table.size").as("saveTableSize"); // too late
    cy.get("#tablePageLength").select("50");
    cy.wait("@saveTableSize"); // TIMEOUT — alias was never matched
});
```

---

### 2. Always `cy.wait()` for API Calls That Mutate Server State

Any test that changes server state (POST, PUT, PATCH, DELETE) **must** wait for the call to complete before ending. If it doesn't, the mutation may be in-flight when the next test starts, corrupting shared state in the test DB.

This applies even when the test doesn't verify persistence — waiting prevents cross-test state pollution.

```javascript
// ✅ CORRECT — wait ensures state is committed before test ends
it("Can toggle dark mode", () => {
    cy.intercept("POST", "**/api/user/*/setting/ui.style").as("saveStyle");
    cy.visit("/v2/user/3");
    cy.get('#settingsNav a[href="#tab-appearance"]').click();

    cy.get("#themeModeDark").check({ force: true });
    cy.wait("@saveStyle");

    // Reset — also wait so the next test starts with clean state
    cy.intercept("POST", "**/api/user/*/setting/ui.style").as("resetStyle");
    cy.get("#themeModeLight").check({ force: true });
    cy.wait("@resetStyle");
});

// ❌ WRONG — test ends with an in-flight POST; next test may see stale or dirty state
it("Can toggle dark mode", () => {
    cy.visit("/v2/user/3");
    cy.get('#settingsNav a[href="#tab-appearance"]').click();
    cy.get("#themeModeDark").check({ force: true }); // fires POST, never awaited
    cy.get("#themeModeLight").check({ force: true }); // fires POST, never awaited
});
```

---

### 3. Selecting the Current Value Does Not Fire a `change` Event

`<select>` elements only emit `change` when the selected option actually changes. If a previous failed run left the element at `"50"` and the test selects `"50"` again, no event fires — so no API call is made — and `cy.wait("@alias")` times out.

**Rule:** Before selecting a new value, always force a known starting value that differs from the target.

```javascript
// ✅ CORRECT — always select a different baseline first (or reset via API beforehand)
it("Can change table page length", () => {
    // Reset to known baseline (10) before the test selects 50
    cy.intercept("POST", "**/api/user/*/setting/ui.table.size").as("resetSize");
    cy.visit("/v2/user/3");
    cy.get('#settingsNav a[href="#tab-appearance"]').click();
    cy.get("#tablePageLength").then(($sel) => {
        if ($sel.val() !== "10") {
            cy.wrap($sel).select("10");
            cy.wait("@resetSize");
        }
    });

    // Now safely select 50 — guaranteed to be a change
    cy.intercept("POST", "**/api/user/*/setting/ui.table.size").as("saveSize");
    cy.wrap($sel).select("50");   // ← or re-query #tablePageLength
    cy.wait("@saveSize");
});

// ❌ WRONG — if DB already holds "50" from a previous failed run, no change event fires
it("Can change table page length", () => {
    cy.intercept("POST", "**/api/user/*/setting/ui.table.size").as("saveTableSize");
    cy.visit("/v2/user/3");
    cy.get('#settingsNav a[href="#tab-appearance"]').click();
    cy.get("#tablePageLength").select("50"); // no-op if already "50"
    cy.wait("@saveTableSize"); // TIMEOUT
});
```

---

### 4. `cy.contains()` Without a Scope Matches Sidebar Nav Text

`cy.contains("Settings")` searches the entire DOM, including Tabler's collapsed sidebar nav links. It resolves on the first match — which may be a hidden nav item, not the page heading — making the assertion unreliable.

**Rule:** Always scope `cy.contains()` to a stable container ID, or use a specific element tag.

```javascript
// ✅ CORRECT — scoped to the page heading element
cy.get("h2.page-title").contains("Settings");

// ✅ CORRECT — scoped to a known container
cy.get("#page-content").contains("Settings");

// ❌ WRONG — may match a sidebar nav link before the heading is rendered
cy.contains("Settings");
```

---

### 5. Scoped `uncaught:exception` Filter for Cross-Test Noise <!-- learned: 2026-04-21 -->

Unhandled promise rejections from **app JS or third-party libs** — ones we can't grep the source of — can fail *any* test that happens to visit the offending page. Symptom seen in `04-system-reset.spec.js` and observed to fail unrelated `.github/`-only PRs:

```
An unknown error has occurred: [object Object]
```

The `[object Object]` tail is the tell that an `Error`-like object was stringified into a template literal somewhere. Cypress treats the rejection as a test failure by default.

**Rule:** when the root cause isn't grep-able in `src/**` or `webpack/**`, add a **tight, signature-matching** filter in `cypress/support/e2e.js` — not a blanket `return false`. Real test failures must still surface.

```javascript
// cypress/support/e2e.js
Cypress.on('uncaught:exception', (err) => {
    if (err && /An unknown error has occurred:\s*\[object Object\]/.test(err.message || '')) {
        return false;   // filter only this exact signature
    }
    // any other uncaught exception still fails the test
});
```

**Do NOT** add a project-wide `Cypress.on('uncaught:exception', () => false)` — that masks real regressions. Every filter needs a regex narrow enough that a different error would still fail.

When adding such a filter, include a code-comment with a TODO + PR link so the root cause gets chased later.

See PR [#8738](https://github.com/ChurchCRM/CRM/pull/8738) for the reference implementation.

---

### 6. API-Only Tests Should Skip UI Login <!-- learned: 2026-04-21 -->

If a test *only* asserts against `cy.request()` responses, do **not** also drive the UI login form — doing so exposes the test to any JS error on the login / forced-password-change / church-info flow (which has caught out `04-system-reset.spec.js` more than once).

```javascript
// ✅ CORRECT — no browser JS executed, no page-init promise rejections
const apiLogin = () => {
    cy.clearCookies();
    cy.request({
        method: 'POST',
        url: '/session/begin',
        form: true,
        body: { User: 'admin', Password: 'changeme' },
        followRedirect: false,
    });
};

it('should reset the database via API', () => {
    apiLogin();  // session cookie only
    cy.request({ method: 'DELETE', url: '/admin/api/database/reset' }).then(/* ... */);
});

// ❌ WRONG — full UI login just to get a cookie for a pure API call
it('should reset the database via API', () => {
    manualLogin();  // visits /login → maybe /changepassword → maybe /admin/system/church-info
    cy.request({ method: 'DELETE', url: '/admin/api/database/reset' });
});
```

UI login is only required when the test actually asserts against page content. For pure API tests, establish the session via API and skip the browser entirely.

---

### 7. Avoid Tautological `cy.url().should('include', ...)` After Form Submit <!-- learned: 2026-04-21 -->

After submitting a form on page `/admin/system/church-info`, asserting `cy.url().should('include', 'church-info')` passes whether the submit succeeded, failed with validation errors, or never navigated at all — the URL substring is already present. The assertion is a no-op and, worse, is not a synchronization barrier, so the next command races any in-flight XHR.

```javascript
// ❌ WRONG — tautology; passes even if form submission failed silently
cy.get('#church-info-form').submit();
cy.url({ timeout: 10000 }).should('include', 'church-info');

// ✅ CORRECT — wait for the actual save to complete via intercept
cy.intercept('POST', '/admin/system/church-info').as('saveChurchInfo');
cy.get('#church-info-form').submit();
cy.wait('@saveChurchInfo').its('response.statusCode').should('be.oneOf', [200, 302]);

// ✅ CORRECT — assert a visible success signal
cy.get('#church-info-form').submit();
cy.contains('.alert-success', /saved|updated/i, { timeout: 10000 }).should('be.visible');
```

Rule of thumb: if the URL substring you are asserting was **already** in the URL before the action, the assertion is not verifying anything.

---

### Flakiness Prevention Checklist (for every new test that saves/loads state)

Before marking a test complete, verify:

- [ ] All `cy.intercept()` calls are placed **before** `cy.visit()` or the element interaction
- [ ] Every state-mutating action (POST/PUT/DELETE) has a matching `cy.wait("@alias")`
- [ ] Tests that reset a value after the assertion also `cy.wait()` on the reset call
- [ ] `select()` tests ensure the starting value differs from the target value
- [ ] `cy.contains()` is scoped to a container, not used globally
- [ ] API-only tests (those that only assert against `cy.request()`) establish the session via `POST /session/begin` — not UI login
- [ ] No `cy.url().should('include', ...)` assertions where the substring is already present pre-action (tautology)

---

## UI Test Best Practices

### Using Element IDs for Test Selectors

```html
<!-- Always add id attributes for testing -->
<button id="btn-add-user" class="btn btn-success">Add User</button>
<input id="input-user-name" type="text" name="userName">
<form id="form-user-edit">...</form>
```

```typescript
// Use IDs in tests (stable, reliable)
cy.get('#btn-add-user').click();
cy.get('#input-user-name').type('John Doe');
cy.get('#form-user-edit').submit();
```

**Why IDs over CSS selectors:**
- IDs don't change when CSS changes
- Text-based selectors break with translations
- Team members know test IDs won't affect styling

#### Note on `data-cy` / `data-test` attributes <!-- learned: 2026-04-13 -->

Cypress's own official best-practices guide recommends `data-cy="..."` (or `data-test="..."`) attributes as the primary testing selector. **ChurchCRM intentionally uses element IDs instead.** The reasoning is:

- ChurchCRM already has stable `id` attributes on every interactive element from its legacy PHP templating, so there is no "no-selector-at-all" problem that `data-cy` exists to solve
- Adding a second parallel attribute (`id` + `data-cy`) doubles the maintenance cost
- `id` has the additional win of being usable from ordinary JS (event handlers, URL fragments) — `data-cy` is test-only

New ChurchCRM code should continue to use `id` attributes for test selectors. If you touch a component that lacks an `id` and would otherwise need a brittle CSS selector, add an `id` — not a `data-cy`. This keeps the test-selector story consistent across the project.

### Complete Workflow Test

```typescript
/**
 * Test: Complete user creation and editing workflow
 */
describe('User Management - Complete Workflow', () => {
    const newUserEmail = `test-${Date.now()}@example.com`;

    beforeEach(() => {
        cy.setupAdminSession();
        cy.visit('/UserList.php');
    });

    it('should create, edit, and verify user', () => {
        // Step 1: Create user
        cy.get('#btn-add-new-user').click();
        cy.get('#input-email').type(newUserEmail);
        cy.get('#input-first-name').type('Test');
        cy.get('#input-last-name').type('User');
        cy.get('#form-user-create').submit();

        cy.get('.alert-success').should('contain', 'User created');

        // Step 2: Verify user appears in list
        cy.get('table tbody').should('contain', newUserEmail);

        // Step 3: Edit user
        cy.contains(newUserEmail).parent().find('#btn-edit').click();
        cy.get('#input-first-name').clear().type('Updated');
        cy.get('#form-user-edit').submit();

        cy.get('.alert-success').should('contain', 'User updated');

        // Step 4: Verify changes
        cy.get('table tbody').should('contain', 'Updated');
    });
});
```

## API Testing Patterns

### Helper Commands

```typescript
/**
 * Make authenticated admin API call
 * Usage: cy.makeAdminAPICall("POST", "/api/users", payload, 200)
 */
cy.makeAdminAPICall(method: string, path: string, body?: any, expectedStatus?: number)

/**
 * Make authenticated user API call
 * Usage: cy.makeUserAPICall("GET", "/api/profile", null, 200)
 */
cy.makeUserAPICall(method: string, path: string, body?: any, expectedStatus?: number)

/**
 * Generic API request with options
 * Usage: cy.apiRequest({ method: "GET", url: "/api/events", failOnStatusCode: false })
 */
cy.apiRequest(options: RequestOptions)
```

### API Test Examples

```typescript
/**
 * Test: API error handling and validation
 */
describe('API - User Creation', () => {
    it('should create user with valid data', () => {
        cy.makeAdminAPICall('POST', '/api/users', {
            email: 'new@example.com',
            firstName: 'John',
            lastName: 'Doe',
            role: 'user'
        }, 201);

        cy.get('@response').then(response => {
            expect(response.body.data.id).to.exist;
            expect(response.body.data.email).to.equal('new@example.com');
        });
    });

    it('should reject invalid email', () => {
        cy.makeAdminAPICall('POST', '/api/users', {
            email: 'invalid-email',  // Invalid format
            firstName: 'John',
            lastName: 'Doe'
        }, 400);

        cy.get('@response').then(response => {
            expect(response.body.message).to.contain('Invalid email');
        });
    });

    it('should return 401 for unauthenticated user', () => {
        cy.apiRequest({
            method: 'POST',
            url: '/api/users',
            body: { email: 'test@example.com' },
            failOnStatusCode: false
        }).then(response => {
            expect(response.status).to.equal(401);
        });
    });
});
```

### Required Test Categories for Each Endpoint

1. **Success Case** - Valid payload, correct status, expected data structure
2. **Validation Tests** - Invalid inputs (bad dates, missing fields), 400 response
3. **Type Safety** - Verify type conversions don't cause runtime errors
4. **Error Handling** - 401/403 auth, 404 not found, 500 errors
5. **Edge Cases** - Null values, empty arrays, boundary conditions

## Debugging 500 Errors (CRITICAL)

**NEVER ignore a test that returns HTTP 500.** Always investigate:

```bash
# 1. Clear logs before reproducing
rm -f src/logs/$(date +%Y-%m-%d)-*.log

# 2. Run the failing test (always include --config-file)
npx cypress run --config-file cypress/configs/docker.config.ts --spec "cypress/e2e/api/path/to/test.spec.js"

# 3. Check PHP logs for error
cat src/logs/$(date +%Y-%m-%d)-php.log | tail -50

# 4. Check app logs
cat src/logs/$(date +%Y-%m-%d)-app.log
```

### Common 500 Error Causes

| Error | Meaning | Solution |
|-------|---------|----------|
| `HttpNotFoundException: Not found` | Wrong route path | Check route def: `/api/family/` vs `/api/families/` |
| `PropelException` | ORM query issues | Check column names, use TableMap constants |
| `TypeError` | Null passed where object expected | Add null checks in service |
| Missing middleware | Auth/CORS not configured | Check middleware order in Slim app |

## Configuration

### Cypress Config Files <!-- learned: 2026-03-07 -->

Config files live in `cypress/configs/` (NOT `docker/`):

- `cypress/configs/docker.config.ts` — standard CI/dev config (uses Docker container at `http://localhost`)
- `cypress/configs/new-system.config.ts` — setup wizard / fresh install tests
- `cypress/configs/base.config.ts` + `_shared.ts` — shared base configuration

**NOTE:** There is **no** root `cypress.config.ts` in this repo. Cypress can still run without `--config-file`, but it will fall back to defaults and will **not** pick up ChurchCRM's required `env` / `baseUrl` / `specPattern` settings. The `npm run test*` scripts already pass `--config-file cypress/configs/docker.config.ts` (or `new-system.config.ts`) — see `package.json`. If you invoke ChurchCRM's Cypress suite yourself, always pass the appropriate config file, e.g. `npx cypress run --config-file cypress/configs/docker.config.ts --spec "..."`.

**CRITICAL: Always install Cypress via `npm install`** <!-- learned: 2026-03-07 -->
- Never use `npx cypress install` — it can produce a corrupt binary with wrong permissions.
- If Cypress binary is broken or missing, fix with: `npx cypress cache clear && npm install`
- The config points at a Docker container. Start the stack (`npm run docker:test`) before running tests.

### Running Tests <!-- learned: 2026-03-26 -->

```bash
# Full suite headless (standard)
npm run test

# Interactive browser runner
npm run test:open

# API tests only
npm run test:api

# UI tests only
npm run test:ui

# Single UI spec file (PREFERRED — use npm script)
npm run test:ui -- --spec "cypress/e2e/ui/people/filter-by-dropdown-choice.spec.js"

# Single API spec file (PREFERRED — use npm script)
npm run test:api -- --spec "cypress/e2e/api/private/admin/private.admin.system.config.spec.js"

# Setup wizard tests
npm run test:new-system

# Direct single-spec run (headless, default config)
npx cypress run --spec "cypress/e2e/ui/events/standard.calendar.spec.js" --headless

# ℹ️ Prefer this direct style for quick single-spec validation.
# The npm scripts (test:ui, test:api) are wrappers that set config/env —
# for single-spec runs, direct npx with --headless is simpler and equivalent.
```

### Running Tests in Docker (Required Workflow)

Tests cannot run locally without Docker — the app server lives in a container.

```bash
# 0. Ensure Node 24 is active (project requires >=24 <25)
node --version  # must be v24.x

# 1. Start test containers
npm run docker:test

# 2. Run tests
npm run test                          # full suite
npm run test:ui                       # UI tests only
npm run test:api                      # API tests only

# 3. Single spec (headless)
npx cypress run --spec "cypress/e2e/ui/user/standard.user.password.spec.js" --headless

# 4. View logs after failures
npm run docker:test:logs

# 5. Teardown
npm run docker:test:down
```

## CRITICAL: Keep Tests in Sync with Code Changes <!-- learned: 2026-03-14 -->

### Tests Are Part of Every Feature

When you modify code, update the corresponding tests **in the same commit**. Tests are not optional follow-up work.

### Common Test Updates Required

#### 1. Form Field Changes
**Situation:** You add a required field to a form.
**Test update needed:** Add assertion that field has `required` attribute and that form validation fails without it.

```typescript
// BEFORE: Test checks only Name and Email are required
it("should have required fields marked", () => {
    cy.get("#sChurchName").should("have.attr", "required");
    cy.get("#sChurchEmail").should("have.attr", "required");
});

// AFTER: Add City as required field
it("should have required fields marked", () => {
    cy.get("#sChurchName").should("have.attr", "required");
    cy.get("#sChurchCity").should("have.attr", "required");  // NEW
    cy.get("#sChurchEmail").should("have.attr", "required");
});
```

#### 2. API Response Schema Changes
**Situation:** API endpoint adds or removes a field.
**Test update needed:** Update assertion to check for new field.

```typescript
// BEFORE: Check response has 'name' and 'email'
cy.get('@response').then(res => {
    expect(res.body).to.have.property('name');
    expect(res.body).to.have.property('email');
});

// AFTER: Add check for new 'status' field
cy.get('@response').then(res => {
    expect(res.body).to.have.property('name');
    expect(res.body).to.have.property('status');  // NEW
    expect(res.body).to.have.property('email');
});
```

#### 3. Element Selectors Change
**Situation:** Form layout changes (fields move to different tab, container, or ID changes).
**Test update needed:** Update selectors to find elements in new location.

```typescript
// BEFORE: City field was on Basic tab
cy.get("#sChurchCity").should("exist");

// AFTER: City field moved to Location tab
cy.get("#location-tab").click();
cy.get("#sChurchCity").should("exist");  // Updated selector path
```

#### 4. Dropdown Data Source Changed
**Situation:** You change from hardcoded options to API-driven.
**Test update needed:** Update how dropdown is tested (wait for API call, check for async data).

```typescript
// BEFORE: Hardcoded state options
cy.get("#sChurchState").find("option").should("have.length", 51);

// AFTER: API-driven states (need timeout for fetch)
cy.get("#sChurchCountry").select("US");
cy.get("#sChurchStateContainer")
    .find("select", { timeout: 5000 })  // Wait for API
    .should("exist");
cy.get("#sChurchState").find("option").should("have.length.greaterThan", 50);
```

### Commit Checklist for Code Changes

- [ ] Code change is complete
- [ ] Run tests: `npm run test:ui -- --spec "path/to/test.spec.js"`
- [ ] Did tests fail? Update them to match new behavior
- [ ] Run tests again — all pass?
- [ ] Review **both** code AND test changes in git diff
- [ ] Commit together: code + test updates

### Page Title Renames: Grep ALL spec Files, Not Just the Obvious One <!-- learned: 2026-04-21 -->

When you rename a user-visible string (page title, card header, button
label), **grep the entire `cypress/e2e` tree** for the old string before
committing. Sibling spec files that happen to exercise the same route often
assert the same title and will silently break in CI.

Real example: the admin-debug refactor renamed `/admin/system/debug/email`'s
page title from "Debug Email Connection" → "Email Debug".
`admin.email.spec.js` was updated in the same PR, but `admin.debug.spec.js`
had an `it("View email debug")` that still asserted the old string. The PR
looked clean; CI failed on the first run.

```bash
# BEFORE renaming a title / label / card header, always run:
rg -l "Debug Email Connection" cypress/e2e

# Any spec that matches needs to be updated in the same commit.
```

**Rule of thumb for pages with variable state:** on pages that render
different cards depending on environment (config error / success /
failure), assert on **structural elements present in every state** — e.g.
the new title plus a card header like "SMTP Configuration" — rather than a
state-specific message. See `cypress/e2e/ui/admin/admin.email.spec.js` for
the pattern.

## Test File Best Practices

### Avoid Complex Async Operations in Session Tests <!-- learned: 2026-03-14 -->

When using `cy.setupStandardSession()` or similar session-based setup, **do not make API calls or complex async operations before or within test blocks**. These can interfere with Cypress session caching and cause login timeouts.

**❌ WRONG — API call in test causes login to hang:**
```typescript
describe('Standard Sunday School', () => {
    beforeEach(() => cy.setupStandardSession());

    it('View class and verify students', () => {
        // This API call interferes with session setup
        cy.makePrivateAdminAPICall('GET', '/api/groups/42', null, 200);

        cy.visit('sundayschool/SundaySchoolClassView.php?groupid=42');
        cy.get('table tbody tr').should('have.length.greaterThan', 0);
    });
});
```

**✅ CORRECT — Direct UI verification without API calls:**
```typescript
describe('Standard Sunday School', () => {
    beforeEach(() => cy.setupStandardSession());

    it('View class and verify students display', () => {
        // Just visit and assert UI state
        cy.visit('sundayschool/SundaySchoolClassView.php?groupid=42');
        cy.get('table tbody tr').should('have.length.greaterThan', 0);
        cy.contains('Student').should('be.visible');
    });
});
```

**Why:** `cy.session()` maintains login state across tests. Adding API calls or async operations in `beforeEach()` or test blocks can break session caching and cause the login phase to hang. Let the session setup handle authentication; tests verify UI.

### Clean Test Files

❌ **WRONG:**
```typescript
describe('Users', () => {
    // TODO: Add more tests here
    // Commented out test - fix this later
    // it('should do something', () => { ... });
    
    it('should work', () => {
        // ...
    });
});
```

✅ **CORRECT:**
```typescript
describe('Users', () => {
    it('should create new user', () => {
        // ...
    });

    it('should edit existing user', () => {
        // ...
    });
});
```

**Rules:**
- Remove commented-out tests (use git history if needed)
- No TODO comments (track in GitHub issues)
- One concern per test file
- Clear, descriptive test names

### Selectors

```typescript
// ✅ CORRECT - Use element IDs
cy.get('#btn-save').click();
cy.get('#input-email').type('test@example.com');
cy.get('table tbody #row-user-123').should('exist');

// ❌ WRONG - Fragile text selectors (break with translations)
cy.contains('Save').click();  // Breaks if text changes
cy.contains('Email').type('test@example.com');  // Wrong element

// ❌ WRONG - Deep CSS selectors (break with style changes)
cy.get('div.container div.row div.col-md-6 form input[type="email"]');
```

### Modal Testing Patterns <!-- learned: 2026-04-06 -->

For dynamically loaded modals (content swapped after API fetch), use specific ID
selectors and `not.exist` (not `not.be.visible`) since cleanup removes the element:

```javascript
// ✅ CORRECT — wait for dynamic content, use element IDs
cy.get("#event-title-input").should("be.visible").type("My Event");
cy.get("#eventCancelBtn").click();
cy.get("#eventEditorModal").should("not.exist");  // cleanup removes element

// ❌ WRONG — fragile selector, wrong close assertion
cy.get(".modal-header input").type("My Event");          // matches ANY input
cy.get(".modal-footer .btn-secondary").click();           // matches settings panel too
cy.get("#eventEditorModal").should("not.be.visible");     // element is removed, not hidden
```

### Tabler Form-Selectgroup (Radio/Checkbox Pills) <!-- learned: 2026-04-06 -->

Tabler hides the actual `<input>` inside `form-selectgroup-item`. Click the parent
`<label>`, not the input:

```javascript
// ✅ CORRECT — click the visible label wrapper
cy.get('input[name="eventDayType"][value="allday"]').parent("label").click();

// ❌ WRONG — input is hidden, check() doesn't reliably fire change event
cy.get('input[name="eventDayType"][value="allday"]').check({ force: true });
```

### Don't Assume Initial Form State from FullCalendar <!-- learned: 2026-04-06 -->

FullCalendar's `select` callback may provide non-midnight times depending on the
view mode. Don't assume the initial all-day/timed state — explicitly set it first:

```javascript
// ✅ CORRECT — set known state, then verify toggle
cy.get('input[name="eventDayType"][value="allday"]').parent("label").click();
cy.get("#eventStartDate").should("have.attr", "type", "date");

// ❌ WRONG — assumes day-click always gives midnight (all-day)
cy.get("#eventStartDate").should("have.attr", "type", "date"); // may be datetime-local
```

## Cross-Spec Environment Variable Persistence <!-- learned: 2026-03-15 -->

**GOTCHA:** `Cypress.env()` mutations in one spec file do NOT reliably persist to other spec files because each spec runs in a fresh browser context.

### ❌ WRONG - Relying on cross-spec env mutation
```typescript
// spec-01-setup-wizard.spec.js
it('should change password', () => {
    const newPassword = 'AdminP@ss1234!';
    // ... change password ...
    Cypress.env('newSystemAdminPassword', newPassword);  // Persists to spec file scope only!
});

// spec-02-demo-import.spec.js
const password = Cypress.env('newSystemAdminPassword');  // ❌ NULL/undefined - not persisted!
// Falls back to default password which may not match current state
```

### ✅ CORRECT - Use stable config source
```typescript
// cypress/configs/docker.config.ts
env: {
    'admin.password': 'changeme',
    'admin.new.password': 'AdminP@ss1234!',  // Define stable password upfront
}

// spec-01-setup-wizard.spec.js
const newAdminPassword = Cypress.env('admin.new.password');
// Use and test with this password

// spec-02-demo-import.spec.js
const password = Cypress.env('admin.new.password');  // Same source - always consistent
```

### Alternative: Use cy.task() for Cross-Spec State
```typescript
// For dynamic values that MUST persist across specs, use cy.task()
it('should store password', () => {
    cy.task('setPassword', newPassword);
});

// cypress/configs/docker.config.ts - register task
on('task', {
    setPassword: (pwd) => {
        require('fs').writeFileSync('.temp/test-pwd', pwd);
        return null;
    },
    getPassword: () => {
        return require('fs').readFileSync('.temp/test-pwd', 'utf8');
    }
});
```

## Related Knowledge
- **Session Management**: Cypress documentation on `cy.session()`
- **Test Organization**: BDD/Cucumber patterns
- **API Testing**: REST API best practices
- **Debugging**: Cypress Inspector and Chrome DevTools

## Test Data: Fixtures and Configuration

### Fixture Files
**Location:** `cypress/fixtures/` — Use for static test data (CSV, JSON, etc.)

```typescript
// Load fixture as file path (CSV uploads, etc.)
cy.get("#CSVFileChooser").selectFile("cypress/fixtures/test_import.csv");

// Load fixture as JSON object
cy.fixture('users.json').then((users) => {
  cy.request('POST', '/api/admin/users', users[0]);
});
```

### Using Fixtures with `cy.intercept()` for API Response Mocking <!-- learned: 2026-04-13 -->

For UI tests where the **UI's response handling** is what's under test — not the backend — stub the API call with a fixture instead of hitting the real server. The test becomes deterministic, faster, and no longer depends on DB state.

```javascript
// ✅ Deterministic: test that the people list renders rows from a fixture
it("renders empty-state when people list is empty", () => {
    cy.intercept("GET", "**/api/persons*", { fixture: "people/empty.json" }).as("getPeople");
    cy.setupAdminSession();
    cy.visit("/people/list");
    cy.wait("@getPeople");
    cy.contains("No people to display").should("be.visible");
});

// ✅ Error path: test that the UI handles a 500 gracefully
it("shows a friendly error when the people API errors", () => {
    cy.intercept("GET", "**/api/persons*", { statusCode: 500, body: { error: "boom" } }).as("getPeople");
    cy.setupAdminSession();
    cy.visit("/people/list");
    cy.wait("@getPeople");
    cy.get(".alert-danger").should("contain", "Unable to load");
});
```

**Fixture organization:** store fixture files under `cypress/fixtures/<feature>/<scenario>.json` so each file captures one well-named scenario (e.g. `people/empty.json`, `people/ten-rows.json`, `finance/deposit-with-warnings.json`).

**When to reach for a fixture instead of the real API:**
- Error paths (4xx, 5xx, malformed payloads) — real failures are hard to reproduce
- Empty states / boundary data — no need to wipe the DB
- Large-result-set rendering — avoid seeding thousands of rows
- Response-shape regression tests — changes to the JSON shape should fail the test

**When to NOT use a fixture:**
- Integration tests that verify a real end-to-end workflow (create → save → reload)
- Anything that asserts on business logic computed server-side
- Contract tests that validate the API's actual response shape against code

Keep fixture files in sync with the real API. If a migration changes the response shape, the fixture and the real response both need updating.

### Environment & Config
**Config files:** `cypress/configs/docker.config.ts` (CI/dev standard), `new-system.config.ts` (setup wizard)  
**Baseurl override:** Use `CYPRESS_BASE_URL` env var to override baseUrl for any variant or installation path:
```bash
CYPRESS_BASE_URL=http://localhost:8080/churchcrm/ npm run test
```
**Local:** Create `cypress.env.json` (gitignored) for test credentials.

### npm Scripts (Learn These)
- `npm run test` — Run full e2e suite headless  
- `npm run test:open` — Open interactive runner  
- `npm run test:api` — API tests only  
- `npm run test:ui` — UI tests only  
- `npm run test:new-system` — Setup wizard tests

Migration note: Move static test data from `cypress/data/` → `cypress/fixtures/`; keep `cypress/data/seed.sql` (Docker mounts it).

---

## v2 Profile Page Test Patterns <!-- learned: 2026-03-26 -->

Family and Person profile pages (v2) have specific selectors and interaction patterns that differ from legacy pages.

### Key Selectors

| Element | Selector | Notes |
|---------|----------|-------|
| Action toolbar Edit button | `a.btn-ghost-primary` containing "Edit" | NOT `.fab-edit` (FABs removed) |
| Actions overflow dropdown | `#family-actions-dropdown` / `#person-actions-dropdown` | Click to open, then find `.dropdown-item` |
| Verify Info (family) | Open Actions dropdown → `cy.contains(".dropdown-item", "Verify Info")` | Was standalone button, now in dropdown |
| Verify modal | `#confirm-verify` | Same as before |
| Add to Cart | `#AddFamilyToCart` / `#AddPersonToCart` | Uses `.AddToCart` class + `data-cart-type` |
| Pledge/Payment pills | `.pledge-type-pill` (type), `.pledge-fy-pill` (fiscal year) | Client-side DataTable filters |
| Pledge table | `#pledge-payment-v2-table` | DataTable with AJAX loading |
| Family navigation | `#lastFamily`, `#nextFamily` | In right sidebar column |
| Family members table | `.card-table` rows with `avatar-sm` | Grouped by role sections |
| Photo upload trigger | `#uploadImageTrigger` (photo click), `#uploadImageButton` (Actions menu) | Both trigger same uploader |

### Testing Dropdown Menu Items

Actions that moved into the "Actions" overflow dropdown require two clicks:

```javascript
// ❌ WRONG — element is inside a closed dropdown, not visible
cy.contains('a', 'Verify').click();

// ✅ CORRECT — open dropdown first, then click item
cy.get('#family-actions-dropdown').click();
cy.contains('.dropdown-item', 'Verify Info').click();
```

### Testing Pill Filters (no page reload)

Pill filters are client-side DataTable column searches. Test that clicking changes the active state and filters the table:

```javascript
// Click "Pledges" pill filter
cy.get('.pledge-type-pill[data-filter="Pledge"]').click();
cy.get('.pledge-type-pill.active').should('contain', 'Pledges');

// Click "All Time" FY filter
cy.get('.pledge-fy-pill[data-fy=""]').click();
cy.get('.pledge-fy-pill.active').should('contain', 'All Time');
```

### Page Title Assertions

v2 profile pages use `$sPageTitle` (family name only) + `$sPageSubtitle` (with ID):

```javascript
// ❌ WRONG — old format
cy.contains("Smith - Family");

// ✅ CORRECT — new format
cy.contains("Smith");           // page title
cy.contains("Family Profile");  // subtitle
```

### cy.contains() Can Match Page Title, Not the Target Element <!-- learned: 2026-03-27 -->

`cy.contains("System Settings")` will match the page `<h2>` title **before** looking for a sidebar tab. Tests using bare `cy.contains()` for nav items can pass even when the tab/category is completely missing.

```javascript
// ❌ WRONG — matches page <h2> title, not the sidebar tab
cy.contains("System Settings");

// ✅ CORRECT — scoped to the nav element
cy.get('#settings-nav').contains("System Settings");
cy.get('.nav-pills').contains("System Settings").should('be.visible');
```

Always scope `cy.contains()` to the smallest meaningful container when asserting the existence of navigation items or tabs.

### Uppy Dashboard Modal: Use `not.be.visible` for Close Assertions <!-- learned: 2026-03-30 -->

Uppy v5 keeps the Dashboard modal DOM element after closing (with animation class `uppy-Dashboard--animateOpenClose`). Using `should("not.exist")` will time out because the element stays in the DOM.

```javascript
// ❌ WRONG — element persists in DOM after close animation
cy.get(".uppy-Dashboard-close").click();
cy.get(".uppy-Dashboard--modal").should("not.exist");

// ✅ CORRECT — element stays but becomes hidden
cy.get(".uppy-Dashboard-close").click();
cy.get(".uppy-Dashboard--modal").should("not.be.visible");
```

### Wait for avatar-loader Before Asserting Click Classes <!-- learned: 2026-03-30 -->

`avatar-loader.ts` adds `.view-person-photo` / `.view-family-photo` **asynchronously** after the photo loads. Tests must wait for the `loaded` class before asserting on click classes.

```javascript
// ❌ WRONG — click class may not be added yet
cy.get("img[data-image-entity-type]").should("have.class", "view-person-photo");

// ✅ CORRECT — wait for avatar-loader to finish processing
cy.get("img.loaded[data-person-id]", { timeout: 10000 }).should("exist");
cy.get("img.loaded").filter(".view-person-photo").should("have.length.at.least", 1);
```

For profile photos inside `#uploadImageButton` / `#uploadImageTrigger`, avatar-loader **skips** adding click classes. Assert they are absent:

```javascript
cy.get("#uploadImageButton img.loaded", { timeout: 10000 }).should("exist");
cy.get("#uploadImageButton img").should("not.have.class", "view-person-photo");
```

### Wait for Uppy Uploader Initialization <!-- learned: 2026-03-30 -->

The photo uploader initializes on `window.addEventListener('load', ...)`. Before clicking the upload trigger, wait for `window.CRM.photoUploader` to exist:

```javascript
cy.get("#uploadImageButton", { timeout: 10000 }).should("exist");
cy.window().its("CRM.photoUploader", { timeout: 10000 }).should("exist");
cy.get("#uploadImageButton").click();
cy.get(".uppy-Dashboard--modal", { timeout: 10000 }).should("be.visible");
```

### Dashboard DataTable Tabs Lazy-Load Content <!-- learned: 2026-03-30 -->

Dashboard DataTable tabs (Latest Families, Latest People, etc.) lazy-load when activated. After clicking a tab, wait for both the tab pane transition **and** the table rows:

```javascript
cy.get("#latest-ppl-tab").click();
cy.get("#latest-ppl-pane").should("have.class", "show");
cy.get("#latestPersonDashboardItem tbody tr", { timeout: 15000 })
  .should("have.length.at.least", 1);
```

Guard for tables where no rows may have photos (depends on seeded data):

```javascript
cy.get("#latestPersonDashboardItem").then(($table) => {
  if ($table.find(".view-person-photo").length > 0) {
    cy.get(".view-person-photo").first().click();
    cy.get("#photo-lightbox").should("be.visible");
  }
});
```

### Cypress Cannot Be Run From the Agent's Bash Tool <!-- learned: 2026-04-09 -->

**Don't try.** Cypress fails immediately with `MODULE_NOT_FOUND` and only the trailing two lines of the error stack survive into the captured output (`code: 'MODULE_NOT_FOUND'` / `requireStack: []`). The full electron stack and the actual missing-module path are stripped before reaching stdout.

The root cause: the Bash sandbox corrupts the path the cypress CLI hands to electron. With `DEBUG=cypress:* node node_modules/cypress/bin/cypress run ...` you can see the full spawn args and the entry point becomes:

```
/Users/.../Cypress.app/Contents/MacOS/Contents/Resources/app/index.js
                                  ^^^^^^^^^^^^^^^^^^^^^^^^^^
```

Doubled-up `Contents/`. Same command in the user's interactive terminal works fine because the sandbox env (PATH/PWD/argv resolution) differs.

**What to do**: ask the user to run cypress for you, e.g.:

```bash
npx cypress run --config-file cypress/configs/docker.config.ts \
  --spec "cypress/e2e/ui/admin/event-types.spec.js" \
  > src/event-tests-output.txt 2>&1
```

Then read `src/event-tests-output.txt` for the full result. Do **not** run `npx cypress install` / `cache clear` to try to "fix" it — the project rules forbid touching the binary cache and the issue isn't in the cache anyway.

### Filtering DataTables 2.x via JS API, not Selectors <!-- learned: 2026-04-09 -->

When a test creates a row and then asserts it appears in a DataTable, default 10-row pagination drops the new row off page 1 once enough rows accumulate. `cy.contains()` then can't find it (DataTables removes off-page rows from the DOM in client-side mode).

**Don't** use the legacy `#tableId_filter input[type=search]` selector — DataTables 2.x with the CRM `layout` config (`topStart: 'search'`) doesn't render that element; the search input lives under `div.dt-search`.

**Do** drive the DataTable JS API directly via `cy.window()`. It's selector-independent and works with any layout/version:

```js
function filterEventTypesTable(query) {
  cy.window().then((win) => {
    win.$('#eventTypesTable').DataTable().search(query).draw();
  });
}

// usage
cy.get('#eventTypesTable', { timeout: 10000 }).should('exist'); // wait for init
filterEventTypesTable(uniqueName);
cy.get('#eventTypesTable tbody tr').should('have.length', 1).and('contain', uniqueName);
```

### Never Mix Success and Error Status Codes <!-- learned: 2026-04-07 -->

Each test must assert a **single** specific expected status code (or a tightly scoped set when
genuine system-state variance exists). Bundling 2xx with 4xx/5xx masks real failures:

```js
// ❌ WRONG — hides whether route works or fails
cy.makePrivateAdminAPICall("POST", "/api/payments/", body, [200, 400, 422, 500]);

// ✅ CORRECT — 500 because validateFund throws TypeError (FundSplit is a string)
cy.makePrivateAdminAPICall("POST", "/api/payments/", body, 500);

// ✅ CORRECT — genuinely ambiguous system-state (kiosk window open vs closed)
expect(response.status).to.be.oneOf([302, 401]);
```

### Trailing Slash Required for Slim Group Root Routes <!-- learned: 2026-04-07 -->

`$group->post('/', ...)` inside `$app->group('/payments', ...)` registers `POST /payments/`
(with trailing slash). Tests calling `/api/payments` (no slash) return **404** now that
middleware ordering is correctly fixed. Use the trailing slash in test URLs:

```js
// ❌ WRONG — 404 with correct LIFO middleware order
cy.makePrivateAdminAPICall("POST", "/api/payments", body, 200);

// ✅ CORRECT
cy.makePrivateAdminAPICall("POST", "/api/payments/", body, 200);
```

This also affected GET `/api/deposits` in `FindDepositSlip.js` — check any route defined as
`$group->get('/', ...)` and ensure callers use the trailing slash.

### Stubbing the Browser Timezone for UI Tests <!-- learned: 2026-04-18 -->

UI code that compares the server-configured `sTimeZone` against the browser's
resolved zone (e.g. the calendar timezone indicator) needs tests that can force
a mismatch. Stub `Intl.DateTimeFormat` inside `cy.visit({ onBeforeLoad })` so the
override is in place **before** any inline script runs — late stubs miss the
first resolve call.

**Gotcha — pass through when caller supplies an explicit `timeZone`:** if the
production code canonicalizes the configured zone via
`new Intl.DateTimeFormat(undefined, { timeZone: configured })` (to treat
`US/Eastern` and `America/New_York` as equal), a blanket override makes
*both* sides of the comparison equal to the fake zone and the mismatch
branch never fires. Only override `resolvedOptions()` when the caller
did NOT supply an explicit `timeZone`.

```js
cy.visit("event/calendars", {
    onBeforeLoad(win) {
        const original = win.Intl.DateTimeFormat;
        function Stub(...args) {
            const inst = new original(...args);
            const explicitTz = (args[1] || {}).timeZone;
            if (!explicitTz) {
                // Only patch the "what zone is the browser in?" call.
                const origResolved = inst.resolvedOptions.bind(inst);
                inst.resolvedOptions = () => ({ ...origResolved(), timeZone: "Pacific/Kiritimati" });
            }
            return inst;
        }
        Stub.supportedLocalesOf = original.supportedLocalesOf.bind(original);
        win.Intl.DateTimeFormat = Stub;
    },
});
```

Wall-clock **round-trip** assertions (write `10:00` → read `10:00`) are the
cheapest way to catch the #8712 class of bugs without setting up a non-UTC CI
environment. See `cypress/e2e/api/private/standard/private.calendar.timezone.spec.js`
for the pattern — extract HH:MM:SS with a regex so the check works for both
Propel's `Y-m-d H:i:s` output and FullCalendar's ISO 8601 feed.

### Also Assert the ISO-8601 Offset for Calendar-Feed Endpoints <!-- learned: 2026-04-22 -->

Wall-clock-only assertions miss a specific flavor of the #8712 regression:
the feed emits the **correct HH:MM:SS** but stamps it with the **server's
default offset** (e.g. `+00:00`) instead of the configured `sTimeZone` offset.
FullCalendar then reinterprets the time against the browser zone and renders
it shifted by hours — wall-clock looks right, UI is wrong.

For any route that returns ISO 8601 strings (`/api/calendars/{id}/fullcalendar`,
iCal exports, JSON-LD, etc.), assert both wall-clock AND that an offset is
present and well-formed:

```js
const offsetOf = (dt) => {
    if (typeof dt !== "string") return null;
    const m = dt.match(/(Z|[+-]\d{2}:\d{2})$/);
    return m ? m[1] : null;
};

// wall-clock preserved
expect(timeOf(mine.start)).to.equal("11:15:00");

// AND offset is present (not a naive datetime)
expect(
    offsetOf(mine.start),
    "start must carry an ISO-8601 offset (not a naive datetime)",
).to.match(/^(Z|[+-]\d{2}:\d{2})$/);
```

Don't pin to a specific offset value (e.g. `"+02:00"`) unless the test
fixture also pins `sTimeZone` — CI may run in any zone. "Offset exists and is
well-formed" is enough to catch the regression.

### Admin Config Toggle in Tests — path is `/admin/api/system/config/{name}` <!-- learned: 2026-04-22 -->

The `POST /api/system/config/{configName}` route is mounted under the **admin**
Slim app (`MvcAppFactory::create('/admin', ...)` in `src/admin/index.php`),
so the full path is `/admin/api/system/config/{configName}` — NOT
`/api/system/config/...`. Using the wrong path returns 404 and every test
that depends on the toggle breaks silently.

```js
function setExternalCalendarApi(enabled) {
    cy.setupAdminSession();
    cy.request({
        method: "POST",
        url: "/admin/api/system/config/bEnableExternalCalendarAPI",
        body: { value: enabled ? "1" : "0" },
        headers: { "Content-Type": "application/json" },
    });
}
```

Always reset the config back to its seed default in an `after()` hook so
later specs aren't affected. Pattern used in
`cypress/e2e/ui/events/external.calendar.spec.js`.

### Tabler `.form-selectgroup-input` Visibility — target the label, not the input <!-- learned: 2026-04-22 -->

Tabler pill-style `form-selectgroup` widgets hide the underlying radio
input with `opacity: 0` and render the visible `.form-selectgroup-label`
as the clickable surface. `cy.get('input[name="..."]').should("be.visible")`
fails because the input itself is invisible by design. Assert visibility
on the wrapping `<label>`, and click the label to change the value.

```js
// ❌ fails with "opacity: 0"
cy.get('input[name="eventInActive"][value="1"]').should("be.visible");

// ✅ assert + click the wrapping label
cy.get('input[name="eventInActive"][value="1"]').parent("label").should("be.visible");
cy.get('input[name="eventInActive"][value="1"]').parent("label").click();
```

Same fix applies to `.btn-check` radios — the input has `display: none` and
the `<label class="btn">` IS the visible widget.

### Save-path Tests on Standard Calendar Need Admin Session <!-- learned: 2026-04-22 -->

`POST /api/events` is gated by `AddEventsRoleAuthMiddleware`. Tests that
use `cy.setupStandardSession()` in a top-level `beforeEach` get `403`
when they click the Save button on the event modal. Move the save-path
tests into their own nested `describe` block with
`cy.setupAdminSession()`:

```js
describe("Standard Calendar — save (admin-session)", () => {
    beforeEach(() => cy.setupAdminSession());

    it("New event saves successfully with default Event Type", () => {
        // ... fill form, click Save, assert 200
    });
});
```

Keep the view-only rendering tests under the standard session.

### Don't `cy.request("POST", "/api/calendars")` without a Body — Returns 400 <!-- learned: 2026-04-22 -->

`POST /api/calendars` requires `Name`, `ForegroundColor`, `BackgroundColor`
in the body. Calling it with no body returns 400 and the test fails.
For fixtures that just need "any calendar exists", use `GET /api/events`
or `GET /api/calendars` to read existing seed data instead.

```js
// ❌ 400 Bad Request
cy.request("POST", "/api/calendars");

// ✅ read seed data
cy.request("/api/events").then((r) => {
    const events = r.body.Events || r.body;
    const arr = Array.isArray(events) ? events : Object.values(events);
    if (arr.length > 0) cy.visit(`/event/editor/${arr[0].Id}`);
});
```

### Driving Modals via Global Functions Beats `.fc-event` Clicks <!-- learned: 2026-04-22 -->

FullCalendar events render at unpredictable positions depending on month/week.
Clicking `.fc-event` is flaky. The calendar modal exposes
`window.showEventForm({ id })` and `window.showNewEventForm(info)` globals
used by FullCalendar's own click handlers — drive the modal directly via
those globals so tests aren't coupled to which month the calendar is rendering.

```js
cy.visit("event/calendars");
cy.window().should("have.property", "showEventForm");
cy.window().then((win) => win.showEventForm({ id: eventId }));
cy.get("#eventEditorModal").should("be.visible");
```

### Asserting CSRF Protection on Legacy PHP Delete Pages <!-- learned: 2026-04-21 -->

When a legacy page is hardened with `CSRFUtils`, add three assertions per page:

1. Confirmation page renders a 64-hex-char `csrf_token` hidden input.
2. The page does **not** delete on GET (URL still shows the confirm page).
3. POST with an invalid token returns **HTTP 403**.

```js
it("renders confirmation form with a CSRF token", () => {
    cy.visit("FooDelete.php?FooID=1&linkBack=bar");
    cy.contains("Confirm Delete");
    cy.get('input[name="csrf_token"]').should("have.attr", "value").and("match", /^[a-f0-9]{64}$/);
});

it("rejects POST without a valid CSRF token", () => {
    cy.request({
        method: "POST",
        url: "FooDelete.php",
        form: true,
        body: { FooID: "1", Delete: "Delete", csrf_token: "bogus" },
        failOnStatusCode: false,
    }).its("status").should("eq", 403);
});
```

The 403 assertion exercises the exact code path a CSRF attacker would hit, so it's the regression test that actually proves the fix. Use `cy.setupStandardSession()` (or `cy.setupAdminSession()`) in `beforeEach` so the session cookie is real; the CSRF check runs AFTER the role/auth check.

### In-Memory CSV Files via `Cypress.Buffer` — No Fixture File Needed <!-- learned: 2026-04-21 -->

When testing CSV upload error paths (duplicate headers, malformed content, etc.) you don't need a fixture file — build the CSV string in the test body and pass it via `Cypress.Buffer`:

```js
it("rejects a CSV with duplicate column headers", () => {
    const csv = "FirstName,LastName,FirstName\nAlice,Smith,Alice\n";
    cy.get("#csvFile").selectFile(
        { contents: Cypress.Buffer.from(csv), fileName: "dup.csv", mimeType: "text/csv" },
        { force: true },
    );
    cy.get("#csv-import-form").submit();
    cy.contains("duplicate column names", { matchCase: false });
});
```

`Cypress.Buffer.from(string)` returns a `Buffer` the same way `Buffer.from()` does in Node — `selectFile` accepts it directly as the `contents` property. This is the right tool for synthetic error-path files; use `cypress/fixtures/` for real data files that are shared across tests.

### `cy.request()` for Redirect Tests — 30x Faster Than `cy.visit()` <!-- learned: 2026-04-22 -->

When a test only needs to assert the final URL after a redirect (e.g. `/setup` →
`session/begin`), use `cy.request()` with `.its("redirectedToUrl")` instead of
`cy.visit()` + `cy.location()`. `cy.request()` follows redirects without loading
JS/CSS/images, dropping ~600ms to ~20ms per test.

```js
// ❌ slow (~600ms) — loads full page
it("Redirects to session/begin", () => {
    cy.visit("/setup");
    cy.location("pathname").should("include", "session/begin");
});

// ✅ fast (~20ms) — HTTP only, no browser rendering
it("Redirects to session/begin", () => {
    cy.request("/setup").its("redirectedToUrl").should("include", "session/begin");
});
```

Use this pattern wherever a test's only assertion is on the destination URL.
Do NOT use it when the test needs to assert page content, DOM elements, or JS state.

### Spec File Startup Overhead — Consolidate Single-Test Files <!-- learned: 2026-04-22 -->

Each Cypress spec file carries ~200–500ms startup overhead (browser context
creation, `beforeEach` session setup, `cy.visit()` page load). In a CI matrix
where jobs run specs serially, 11 single-test files add ~3–5 seconds per job.

**Rule:** Avoid creating a new spec file for a single `it()` block.
Merge it into a related existing spec that shares the same page and session type.
Good consolidation targets:

- Same `cy.visit()` URL → same spec file
- Same `beforeEach` session type (`setupStandardSession` / `setupAdminSession`) → same or nearby `describe` block
- Same admin area (e.g., `/admin/system/debug/*`) → same spec file, separate `it()` blocks

When merging a spec with a different `beforeEach`, add it as a separate `describe`
block with its own `beforeEach` inside the same file — don't force a common setup.

```js
// ✅ Two describe blocks in one file — each has its own beforeEach
describe("Family basic", () => {
    beforeEach(() => cy.setupStandardSession());
    it("...", () => { /* ... */ });
});

describe("Family Activation", () => {
    beforeEach(() => {
        cy.intercept(...);
        cy.makePrivateUserAPICall(...);
        cy.setupStandardSession({ forceLogin: true });
    });
    it("...", () => { /* ... */ });
});
```
