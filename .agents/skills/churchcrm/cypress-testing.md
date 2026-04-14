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

Credentials live in **two** config files that MUST be kept in sync:
- `cypress.config.ts` — used for local `npm run test` / `npm run test:open`
- `docker/cypress.config.ts` — used by the Docker / CI runner

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

> ⚠️ **CRITICAL: keep both configs in sync.** <!-- learned: 2026-04-13 -->
> Any new test user credential has to be added to **both** `cypress.config.ts`
> and `docker/cypress.config.ts`. If you update only one, tests will pass
> locally but fail in CI (or vice-versa), and the failure message — usually
> `Admin credentials not configured in cypress.config.ts env` thrown from
> `setupLoginSession` — does not tell you which config file is missing the
> key. Always grep both files when adding a new `*.username` / `*.password`
> entry.

**CRITICAL:**
- ❌ DO NOT hardcode credentials in test files
- ❌ DO NOT add commented-out tests or TODO comments
- ✅ Configuration-driven approach prevents secrets leaking into git
- ✅ Update `cypress.config.ts` AND `docker/cypress.config.ts` together

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

### Flakiness Prevention Checklist (for every new test that saves/loads state)

Before marking a test complete, verify:

- [ ] All `cy.intercept()` calls are placed **before** `cy.visit()` or the element interaction
- [ ] Every state-mutating action (POST/PUT/DELETE) has a matching `cy.wait("@alias")`
- [ ] Tests that reset a value after the assertion also `cy.wait()` on the reset call
- [ ] `select()` tests ensure the starting value differs from the target value
- [ ] `cy.contains()` is scoped to a container, not used globally

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

**NOTE:** The root `cypress.config.ts` is auto-detected by Cypress, so `--config-file` is only required when using a non-default config (e.g., `cypress/configs/new-system.config.ts`). For standard runs, `npx cypress run --spec "..."` works without `--config-file`.

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
// cypress.config.ts
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

// cypress.config.ts - register task
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
