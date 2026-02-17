---
title: "Testing"
intent: "Guidance for Cypress, unit/integration tests, and test workflows"
tags: ["testing","cypress","ci"]
prereqs: ["cypress-testing.md"]
complexity: "intermediate"
---

# Skill: Testing with Cypress

## Context
This skill covers writing and running Cypress tests for API endpoints and UI workflows in ChurchCRM.

## Test Structure

- **API Tests**: `cypress/e2e/api/private/[feature]/[endpoint].spec.js`
- **UI Tests**: `cypress/e2e/ui/[feature]/`
- **Configuration**: `cypress.config.ts` (dev) and `docker/cypress.config.ts` (CI)

## Cypress Configuration & Logging

- **Enhanced logging**: `cypress-terminal-report` plugin captures browser console output
- **CI artifacts**: Logs uploaded to `cypress/logs/`, accessible via GitHub Actions artifacts
- **Log retention**: 30 days for debugging failed CI runs

## API Testing

### Helper Commands (NEVER use cy.request directly)

```javascript
// Use these helper commands instead of cy.request
cy.makePrivateAdminAPICall("POST", "/api/payments", payload, 200)
cy.makePrivateUserAPICall("GET", "/api/events", null, 200)
cy.apiRequest({ method: "GET", url: "/api/events", failOnStatusCode: false })
```

### Test Categories (Required for Each Endpoint)

1. **Successful operations** - Valid payload, 200 response, check data structure
2. **Validation tests** - Invalid inputs (bad dates, missing fields), 400 response
3. **Type safety** - Verify type conversions don't cause runtime errors
4. **Error handling** - 401/403 auth, 404 not found, 500 errors
5. **Edge cases** - Null values, empty arrays, boundary conditions

### Example API Test Structure

```javascript
describe('POST /api/payments', () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it('should successfully create payment with valid data', () => {
        const payload = {
            familyId: 1,
            amount: 100.00,
            method: 'CHECK',
            date: '2024-01-15'
        };
        
        cy.makePrivateAdminAPICall("POST", "/api/payments", payload, 200)
            .then((response) => {
                expect(response.body).to.have.property('success', true);
                expect(response.body.data).to.have.property('paymentId');
                expect(response.body.data.amount).to.equal(100.00);
            });
    });

    it('should return 400 for invalid date format', () => {
        const payload = {
            familyId: 1,
            amount: 100.00,
            method: 'CHECK',
            date: 'invalid-date'
        };
        
        cy.makePrivateAdminAPICall("POST", "/api/payments", payload, 400);
    });

    it('should return 401 when not authenticated', () => {
        cy.clearCookies();
        cy.apiRequest({ 
            method: "POST", 
            url: "/api/payments", 
            body: {},
            failOnStatusCode: false 
        }).then((response) => {
            expect(response.status).to.equal(401);
        });
    });
});
```

## UI Testing

### Session-Based Login Pattern (REQUIRED)

**All UI tests MUST use modern session-based login.** This pattern uses `cy.session()` for efficient login caching across tests.

**✅ CORRECT - Modern Pattern (REQUIRED for all new tests):**

```javascript
describe('Feature X', () => {
    beforeEach(() => {
        cy.setupAdminSession();  // OR cy.setupStandardSession() for standard users
        cy.visit('/path/to/page');
    });

    it('should complete workflow', () => {
        cy.get('#element-id').click();
        cy.contains('Expected text').should('exist');
    });
});
```

**❌ WRONG - Old Pattern (DO NOT USE):**

```javascript
describe('Feature X', () => {
    it('should complete workflow', () => {
        cy.loginAdmin('/path/to/page');  // ❌ DEPRECATED - removed
        cy.get('#element-id').click();
    });
});
```

### Available Session Commands

- `cy.setupAdminSession()` - Authenticates as admin (reads `admin.username`, `admin.password` from config)
- `cy.setupStandardSession()` - Authenticates as standard user (reads `standard.username`, `standard.password` from config)
- `cy.setupNoFinanceSession()` - Authenticates as user without finance permission (reads `nofinance.username`, `nofinance.password` from config)

### Credentials Configuration

Credentials are stored in `cypress.config.ts` and `docker/cypress.config.ts`:

```typescript
env: {
    'admin.username': 'admin',
    'admin.password': 'changeme',
    'standard.username': 'tony.wade@example.com',
    'standard.password': 'basicjoe',
    'nofinance.username': 'judith.matthews@example.com',
    'nofinance.password': 'noMoney$',
}
```

**DO NOT:**
- ❌ Hardcode credentials in test files
- ❌ Add commented-out tests or TODO comments - remove them
- ❌ Add new users without updating both config files

### Other Available Commands

- `cy.typeInQuill(selector, text)` - Rich text editor input

### Test Structure Requirements

- **Maintain element IDs** for test selectors (use `cy.get('#element-id')`)
- **Avoid text-based selectors** (fragile across language changes)
- **Test complete user workflows** end-to-end
- **Clear test descriptions** (avoid generic names)
- **Clean test files** (no commented code blocks)

## Debugging 500 Errors (CRITICAL)

**NEVER ignore or skip a test that returns HTTP 500.** Always investigate the root cause:

### Debugging Workflow

1. **Clear logs before reproducing**: `rm -f src/logs/$(date +%Y-%m-%d)-*.log`
2. **Run the failing test** to reproduce the error
3. **Check PHP logs**: `cat src/logs/$(date +%Y-%m-%d)-php.log`
4. **Check app logs**: `cat src/logs/$(date +%Y-%m-%d)-app.log`

**Common 500 error causes:**
- `HttpNotFoundException: Not found` - Wrong route path (e.g., `/api/family/` vs `/api/families/`)
- `PropelException` - ORM query issues, missing columns, type mismatches
- `TypeError` - Null value passed where object expected
- Missing middleware or incorrect middleware order

**Example fix workflow:**

```bash
# 1. Clear logs
rm -f src/logs/$(date +%Y-%m-%d)-*.log

# 2. Run failing test
npx cypress run --spec "cypress/e2e/api/path/to/test.spec.js"

# 3. Check logs for error
cat src/logs/$(date +%Y-%m-%d)-php.log | tail -50
```

## Running Tests

### Local Development

```bash
# Run all tests (headless)
npm run test

# Run specific test file
npx cypress run --spec "cypress/e2e/ui/path/to/test.spec.js"

# Interactive browser testing
npm run test:ui

# CRITICAL: Clear logs before every test run
rm -f src/logs/$(date +%Y-%m-%d)-*.log

# CRITICAL: Review logs after tests (even if they pass)
cat src/logs/$(date +%Y-%m-%d)-php.log      # PHP errors, ORM errors
cat src/logs/$(date +%Y-%m-%d)-app.log      # App events
```

### CRITICAL Testing Workflow for Agents

**BEFORE running any test:**
1. Clear logs: `rm -f src/logs/$(date +%Y-%m-%d)-*.log`

**AFTER test completion (pass OR fail):**
2. Review PHP log: `cat src/logs/$(date +%Y-%m-%d)-php.log`
3. Review App log: `cat src/logs/$(date +%Y-%m-%d)-app.log`

**Even if tests pass**: Verify no 500 errors or exceptions were logged silently.

### CI/CD Testing (GitHub Actions)

- Docker profiles: `dev`, `test`, `ci` in `docker-compose.yaml`
- CI uses `npm run docker:ci:start` with optimized containers
- Artifacts uploaded: `cypress-artifacts-{run_id}` contains logs, screenshots, videos
- Access via Actions → Workflow run → Artifacts section
- Debugging: Download `cypress-reports-{branch}` for detailed failure analysis

## Docker Test Management

```bash
# Development
npm run docker:dev:start     # Start dev containers
npm run docker:dev:stop      # Stop containers
npm run docker:dev:logs      # View logs

# Testing
npm run docker:test:start       # Start test containers
npm run docker:test:restart     # Restart all containers
npm run docker:test:restart:db  # Restart database only (refresh schema)
npm run docker:test:rebuild     # Full rebuild with new images
npm run docker:test:down        # Remove containers and volumes
```

## Test Requirements Before Committing

**ALWAYS add API tests when creating new API endpoints:**

- **Test location**: `cypress/e2e/api/private/standard/` for standard user endpoints
- **Test location**: `cypress/e2e/api/private/admin/` for admin-only endpoints
- **Required test cases**:
  1. Success case - Returns expected status code and data structure
  2. Data validation - Response contains expected properties and types
  3. Authentication - Returns 401 when not authenticated

**Run tests before committing:**

```bash
# Clear logs
rm -f src/logs/$(date +%Y-%m-%d)-*.log

# Run relevant tests
npx cypress run --e2e --spec "path/to/test.spec.js"

# Review logs (even if tests pass)
cat src/logs/$(date +%Y-%m-%d)-php.log
cat src/logs/$(date +%Y-%m-%d)-app.log
```

**Only proceed to commit after:**
- Tests pass successfully
- Logs show no hidden errors

## Pre-Commit Testing

### Always Test Before Committing

- **Run Relevant Tests**: Before committing any changes, ensure all Cypress tests related to the modified files are executed.
- **Clear Logs**: Clear old logs before running tests to avoid confusion:
  ```bash
  rm -f src/logs/$(date +%Y-%m-%d)-*.log
  ```
- **Verify Results**: Ensure all tests pass successfully. Address any failures before proceeding.

### Best Practices for Cypress Tests

- **Test Isolation**: Ensure tests are independent and do not rely on the state left by other tests.
- **Use Selectors**: Prefer element IDs or data attributes for selectors (e.g., `cy.get('#element-id')` or `cy.get('[data-cy=selector]')`). Avoid text-based selectors.
- **Avoid Hardcoding**: Use configuration-driven credentials and dynamic data where possible.
- **Clean State**: Reset application state between tests to ensure consistency.
- **Descriptive Names**: Use clear and descriptive test names to indicate the purpose of each test.

### Running Tests

- Use the following command to run specific tests:
  ```bash
  npx cypress run --spec "path/to/test.spec.js"
  ```
- For interactive testing, use:
  ```bash
  npx cypress open
  ```

## Files

**API Tests:** `cypress/e2e/api/`
**UI Tests:** `cypress/e2e/ui/`
**Config:** `cypress.config.ts`, `docker/cypress.config.ts`
**Support:** `cypress/support/commands.js`
**Logs:** `src/logs/`
