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

# 2. Run the failing test
npx cypress run --spec "cypress/e2e/api/path/to/test.spec.js"

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

### Cypress Config Files

**Development:** `cypress.config.ts`
```typescript
export default defineConfig({
    e2e: {
        baseUrl: 'http://localhost:8000',
        env: {
            'admin.username': 'admin',
            'admin.password': 'changeme',
        }
    }
});
```

**CI/Docker:** `docker/cypress.config.ts`
```typescript
export default defineConfig({
    e2e: {
        baseUrl: 'http://web:8080',  // Docker service name
        specPattern: 'cypress/e2e/**/*.spec.ts',
    }
});
```

### Running Tests Locally

```bash
# Interactive browser testing
npm run test:ui

# Headless testing (CI mode)
npm run test

# Run specific test file
npx cypress run --spec "cypress/e2e/ui/users/create-user.cy.ts"

# Run with debug logging
DEBUG=cypress:* npm run test
```

### Running Tests in Docker

```bash
# Start test containers
npm run docker:test:start

# Run all tests
npx cypress run

# View logs after failures
npm run docker:test:logs
```

## Test File Best Practices

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
