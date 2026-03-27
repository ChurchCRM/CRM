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

Credentials stored in `cypress.config.ts` and `docker/cypress.config.ts`:

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

**CRITICAL:**
- ❌ DO NOT hardcode credentials in test files
- ❌ DO NOT add commented-out tests or TODO comments
- ✅ Configuration-driven approach prevents secrets leaking into git

### cy.request() API Calls Reset PHP Sessions (CRITICAL) <!-- learned: 2026-03-27 -->

`cy.request()` (used by `makePrivateAdminAPICall`, `makePrivateUserAPICall`) sends and
receives cookies automatically. PHP's `session_start()` runs on every API request and
sends a `Set-Cookie: PHPSESSID=xxx` response that **overwrites the browser's session cookie**.

This means: after ANY `cy.request()` / `makePrivateAdminAPICall()` call, the browser
session established by `cy.setupAdminSession()` is **invalidated**. A subsequent
`cy.visit()` will redirect to `/session/begin` (login page).

**Fix: Direct login after API calls**

```javascript
// Helper — bypasses cy.session() cache, guarantees fresh PHP session
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

**Rules:**
- ❌ NEVER `cy.visit()` after `cy.request()` / `makePrivateAdminAPICall()` without re-login
- ❌ `cy.setupAdminSession({ forceLogin: true })` is NOT sufficient — it still uses `cy.session()` cache
- ❌ `before()` hooks with API calls will poison cookies for ALL subsequent tests
- ✅ Use `freshAdminLogin()` (clear cookies + direct form login) before `cy.visit()`
- ✅ Each test should be self-contained — set up its own data, then re-login, then visit

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

# ⚠️ DO NOT use direct npx cypress run — always use the npm scripts above
# The npm scripts handle config and environment setup correctly
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

# 3. Single spec
npx cypress run --config-file cypress/configs/docker.config.ts --spec "cypress/e2e/ui/user/standard.user.password.spec.js"

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
