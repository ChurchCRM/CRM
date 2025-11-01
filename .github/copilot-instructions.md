# ChurchCRM AI Coding Agent Instructions

## 🎯 Quick Overview

**Stack:** PHP 8.1+ (Propel ORM) | MySQL/MariaDB | Slim Framework | React/TypeScript | Cypress | AdminLTE UI

**Key Architecture:** Legacy PHP pages + modern Slim REST APIs + React SPA components + Webpack bundling

---

## 🏗️ Architecture & Core Concepts

### 1. **Application Layers**

#### Legacy Pages (Monolithic)
- **Location:** `src/*.php` (e.g., `EventEditor.php`, `FamilyEditor.php`)
- **Pattern:** Traditional PHP page handlers with form submission
- **Database:** Propel ORM Query classes (`EventQuery::create()->findById($id)`)
- **UI Framework:** AdminLTE 3.2.0 + Bootstrap 4.6.2
- **Don't modify:** Use APIs instead for new features

#### Modern REST APIs (Slim Framework)
- **Location:** `src/api/index.php` (routes defined in `src/api/routes/`)
- **Structure:** Service → Query classes → Response DTOs
- **Services:** `PersonService`, `GroupService`, `FinancialService`, `SystemService`, `DepositService`
- **Dependency Injection:** Symfony DI container at application startup
- **Response Format:** Always JSON with `data` + `message` keys

#### React Components
- **Location:** `react/` (e.g., `calendar-event-editor.tsx`, `two-factor-enrollment.tsx`)
- **Bundled via:** Webpack (output: `src/skin/v2/`)
- **Used in:** Specific pages requiring interactive UI (calendar, two-factor auth)
- **Build:** `npm run build:webpack`

#### Slim Middleware Stack (CRITICAL ORDER)
```
✅ CORRECT (src/api/index.php shows this):
$app->addBodyParsingMiddleware();           // Parse request body first
$app->addRoutingMiddleware();               // Must determine route BEFORE auth
$app->add(VersionMiddleware::class);        // Version header
$app->add(AuthMiddleware::class);           // Now check auth
$app->add(new CorsMiddleware());            // CORS headers

❌ WRONG order = 401 becomes 404/500
```

**⚠️ Why Order Matters:**
- `addRoutingMiddleware()` MUST run BEFORE `add()` middleware calls
- Routing determines which handler processes the request
- If routing runs after auth, unauthenticated requests hit handlers before auth checks
- Wrong order: 401/403 auth responses become 500 errors instead
- All Slim apps in `src/{api,v2,kiosk,setup,external,session}/index.php` must follow this pattern

---

### 2. **Database Layer (Propel ORM Mandatory)**

#### Always Use Query Classes
```php
// ✅ CORRECT
$event = EventQuery::create()->findById((int)$eventId);
if ($event === null) {
    throw new \Exception("Event not found");
}

// ❌ WRONG - Never raw SQL
$result = RunQuery("SELECT * FROM events WHERE eventid = ?", $eventId);
```

#### Type Casting for Safety
```php
// Always cast dynamic identifiers to int
$id = (int)$_GET['eventId'];  // Not just $_GET['eventId']
$event = EventQuery::create()->findById($id);
```

#### Null vs Empty
```php
// ✅ CORRECT - Propel objects are null, not empty
if ($event === null) { /* not found */ }

// ❌ WRONG - empty() is unreliable with objects
if (empty($event)) { /* breaks with Propel objects */ }
```

#### Object Property Access (CRITICAL)
```php
// ✅ CORRECT - Propel ORM returns objects
$event = EventQuery::create()->findById($eventId);
echo $event->eventName;           // Object syntax
echo $event->eventDate ?? 'N/A';  // Null-safe access

// ❌ WRONG - ORM never returns arrays (uses object syntax)
echo $event['eventName'];         // TypeError: Cannot access offset on object
```

---

### 3. **Service Classes (Business Logic)**

Located in `src/ChurchCRM/Service/` - handles domain logic separate from HTTP concerns.

**Key Services:**
- `PersonService` - Person/family operations
- `GroupService` - Group management
- `FinancialService` - Payments, pledges, funds
- `DepositService` - Deposit slip handling
- `SystemService` - System-wide operations

**Example Usage:**
```php
// src/api/routes/finance/finance-payments.php
$service = $container->get('FinancialService');
$result = $service->addPayment($fam_id, $method, $amount, $date, $funds);
return $response->withJson(['data' => $result]);
```

---

### 4. **Asset Paths (SystemURLs)**

**ALWAYS use SystemURLs::getRootPath() for asset references:**

```php
// ✅ CORRECT
<link rel="stylesheet" href="<?= SystemURLs::getRootPath() ?>/skin/v2/churchcrm.min.css">
<img src="<?= SystemURLs::getRootPath() ?>/images/logo.png">

// ❌ WRONG - Relative paths break in subdirectories
<link rel="stylesheet" href="/skin/v2/churchcrm.min.css">
<link rel="stylesheet" href="./skin/v2/churchcrm.min.css">
```

---

## 🔨 Developer Workflows

### Setup & Build

**Initial Setup:**
```bash
npm ci                              # Install exact dependencies
npm run deploy                      # Build everything (PHP + frontend)
npm run docker:dev:start            # Start Docker containers
```

**Development Cycle:**
```bash
npm run build:frontend              # Rebuild JS/CSS (watches via Webpack)
npm run build:php                   # Update Composer dependencies
npm run docker:dev:logs             # View container logs
```

**Database/Docker Access:**
```bash
npm run docker:dev:login:web        # SSH into web container
npm run docker:dev:login:db         # SSH into database container
npm run docker:test:restart         # Full reset for testing
```

### Testing

**Cypress E2E Tests:**
```bash
npm run test                        # Run all tests (headless)
npm run test:ui                     # Interactive browser testing
```

**Log Management (CRITICAL for debugging):**
```bash
# BEFORE every test run: clear old logs
rm -f src/logs/$(date +%Y-%m-%d)-*.log

# AFTER test failures: review logs
cat src/logs/$(date +%Y-%m-%d)-php.log      # PHP errors, ORM errors
cat src/logs/$(date +%Y-%m-%d)-app.log      # App events
```

**Why Log Clearing Matters:**
- Old errors mask new failures
- Fresh logs make debugging faster
- Prevents false alarms from stale messages

---

## 📋 Code Patterns & Conventions

### Commit Messages

**Format:** Imperative mood, < 72 chars, no file paths
```
✅ Fix SQL injection in EditEventAttendees
✅ Replace deprecated HTML attributes with Bootstrap CSS
✅ Add missing element ID for test selector

❌ Fixed the bug in src/EventEditor.php
❌ Updated files in src/Include/
```

### HTML Modernization

**Always use Bootstrap CSS, never deprecated HTML attributes:**
```php
// ✅ CORRECT - Bootstrap classes
<div class="text-center align-top">Content</div>
<button class="btn btn-primary mt-3">Click</button>

// ❌ WRONG - Deprecated attributes
<div align="center" valign="top">Content</div>
<button style="margin-top: 12px;">Click</button>
```

### JavaScript/Webpack

- **Bundler:** Webpack (not Grunt for new JS)
- **Entry Points:** `webpack/skin-main` (main), `webpack/photo-uploader-entry`
- **CSS:** Extract via MiniCssExtractPlugin → `src/skin/v2/*.min.css`
- **jQuery:** Provided globally via webpack ProvidePlugin

### Internationalization (i18n)

**CRITICAL: Always wrap user-facing text for translation support**

**JavaScript - Use i18next.t():**
```javascript
// ✅ CORRECT - Wrap all UI strings
$.notify(i18next.t('Group name cannot be empty'), {
    type: 'danger',
    delay: 3000
});

const confirmMsg = i18next.t('Are you sure you want to delete this item?');
if (confirm(confirmMsg)) { /* ... */ }

// ❌ WRONG - Raw English strings
$.notify('Group name cannot be empty', { type: 'danger' });
```

**PHP - Use gettext():**
```php
// ✅ CORRECT - Wrap all UI strings
echo gettext('Welcome to ChurchCRM');
$message = gettext('Record saved successfully');

// ❌ WRONG - Raw English strings
echo 'Welcome to ChurchCRM';
```

**When to use:**
- All error messages shown to users
- All success notifications
- Button labels, form labels, headings
- Confirmation dialogs
- Any text visible in the UI

**When NOT to use:**
- Console.log debug messages
- Error logs (error_log)
- Database queries
- API endpoint paths
- Variable names, function names

---

### User Notifications

**MANDATORY: Use bootstrap-notify for all UI notifications (NEVER alert())**

**Bootstrap-Notify Pattern:**
```javascript
// ✅ CORRECT - bootstrap-notify with i18next
$.notify(i18next.t('Operation completed successfully'), {
    type: 'success',
    delay: 3000,
    placement: { from: 'top', align: 'right' }
});

$.notify(i18next.t('An error occurred'), {
    type: 'danger',
    delay: 5000,
    placement: { from: 'top', align: 'right' }
});

// ❌ WRONG - alert() is forbidden
alert('Operation completed');
alert(i18next.t('Error occurred'));

// ❌ WRONG - Missing i18next.t()
$.notify('Operation completed', { type: 'success' });
```

**Notification Types:**
- `type: 'success'` - Green, for successful operations
- `type: 'danger'` - Red, for errors
- `type: 'warning'` - Yellow, for warnings
- `type: 'info'` - Blue, for informational messages

**Cypress Testing:**
```javascript
// Use [data-notify='container'] selector for bootstrap-notify
cy.get('[data-notify="container"]').should('be.visible');
cy.get('[data-notify="container"]').should('contain', 'Expected message');

// Allow time for animated notifications
cy.get('[data-notify="container"]', { timeout: 10000 }).should('be.visible');
```

**Why bootstrap-notify:**
- Native browser alert() blocks execution and cannot be styled
- Bootstrap-notify integrates with Bootstrap 4 theming
- Supports internationalization (works with i18next.t())
- Non-blocking and auto-dismissing
- Consistent UX across entire application

---

## ⚠️ Critical Patterns (Lessons from Bug Fixes)

### 1. **Type Mismatches in API Parsers**

**Problem:** API body parser casts JSON to objects; code tries to access as arrays → TypeError

```php
// ❌ WRONG - Parser returns object $data, not array
$amount = $data['amount'];  // TypeError: Cannot access offset on object

// ✅ CORRECT - Use object property access
$amount = $data->amount;
```

**When this occurs:**
- API endpoints receiving JSON payloads through Slim parser
- Validation methods processing request bodies
- Always assume parser returns typed objects

---

### 2. **Namespaced Code Calling Global Functions**

**Problem:** PHP searches current namespace first; missing `\` prefix causes "undefined function" errors

```php
// ❌ WRONG - searches ChurchCRM\ namespace first
namespace ChurchCRM\Service;
class MyService {
    public function test() {
        MakeFYString($id);  // PHP Error: undefined function
    }
}

// ✅ CORRECT - explicit global namespace
namespace ChurchCRM\Service;
class MyService {
    public function test() {
        \MakeFYString($id);  // Resolves correctly
    }
}
```

**Checklist:**
- Calling global functions from namespaced classes? Add `\` prefix
- Check `src/Include/` for utility functions when needed
- Use fully-qualified names or import statements

---

### 3. **Slim 4 Route Handlers Must Be Closures**

**Problem:** Slim 4 doesn't support string callable references; handlers must be inline closures

```php
// ❌ WRONG - String reference doesn't work in Slim 4
$group->post('/path', 'MyHandler::process');

// ✅ CORRECT - Inline closure
$group->post('/path', function ($request, $response) {
    // handler code
    return $response->withJson($data);
});
```

---

### 4. **Email Failure Handling in APIs**

**Problem:** Email service unavailable crashes entire endpoint instead of gracefully degrading

```php
// ❌ WRONG - Blocks API response on email failure
if (!mail($to, $subject, $body)) {
    throw new Exception("Email failed");  // Returns 500 error
}

// ✅ CORRECT - Log warning but continue
if (!mail($to, $subject, $body)) {
    error_log("Email send failed for: " . $to);  // Log but don't crash
}
return $response->withJson(['data' => $result]);  // Still returns 200
```

**Decision:**
- **Throw exception:** For critical emails (password reset, verification)
- **Log warning + continue:** For notifications, confirmations, newsletters

---

### 5. **Null Safety in Property Access**

**Problem:** Accessing properties on null objects causes TypeError → 500 errors

```php
// ❌ WRONG - Crashes if $notification is null
echo $notification->title;

// ✅ CORRECT - Null coalescing operator
echo $notification?->title ?? 'No Title';
```

---

## 🧪 Testing Requirements

### API Tests (Mandatory for API Changes)

**Location:** `cypress/e2e/api/private/[feature]/[endpoint].spec.js`

**Helper Commands (NEVER use cy.request directly):**
```javascript
cy.makePrivateAdminAPICall("POST", "/api/payments", payload, 200)
cy.makePrivateUserAPICall("GET", "/api/events", null, 200)
cy.apiRequest({ method: "GET", url: "/api/events", failOnStatusCode: false })
```

**Test Categories Required:**
1. **Successful Operations** - Valid payload, 200 response, check data structure
2. **Validation Tests** - Invalid inputs (bad dates, missing fields), 400 response
3. **Type Safety** - Verify type conversions don't cause runtime errors
4. **Error Handling** - 401/403 auth, 404 not found, 500 errors
5. **Edge Cases** - Null values, empty arrays, boundary conditions

**Type Safety Testing (Critical):**
```javascript
describe("Type Safety for API Payloads", () => {
    it("Handles object property access correctly", () => {
        // Ensures parser returns objects, not arrays
        cy.makePrivateAdminAPICall("POST", "/api/payments", 
            { iMethod: "CASH", amount: 100 }, 
            200
        ).then((resp) => {
            // Should NOT contain property access errors
            expect(JSON.stringify(resp)).to.not.include("Cannot access offset on object");
        });
    });
    
    it("Handles null properties gracefully", () => {
        // Tests null coalescing in response objects
        cy.makePrivateAdminAPICall("GET", "/api/notifications", null, 200)
            .then((resp) => {
                if (resp.body.data.length > 0) {
                    const notif = resp.body.data[0];
                    // Should have title or default
                    expect(notif.title || 'Untitled').to.exist;
                }
            });
    });
});
```

**Example Structure:**
```javascript
describe("POST /api/payments", () => {
    it("Accepts valid payment", () => {
        cy.makePrivateAdminAPICall("POST", "/api/payments", validPayload, 200)
            .then((resp) => {
                expect(resp.body.data).to.have.property("id");
            });
    });
    
    it("Rejects invalid check method", () => {
        cy.makePrivateAdminAPICall("POST", "/api/payments", 
            { iMethod: "CHECK" }, 
            [400, 422]  // Expect validation error
        );
    });
});
```

### UI Tests

**Location:** `cypress/e2e/ui/[feature]/`

**Guidelines:**
- Maintain element IDs for test selectors
- Use `cy.get()` for queries, avoid text-based selectors when possible
- Test complete user workflows end-to-end
- Reference: `cypress/e2e/ui/events/standard.events.spec.js`

---

## � PR Organization Strategy

**Philosophy:** Keep PRs focused on single concerns for easier review and merging.

### Branch Planning
- **Split large changes** into logical feature branches
- **Each PR addresses** one specific bug or feature
- **Related but separate concerns** get separate branches
- **Test each branch** independently before creating PR

### Multiple Concerns Pattern

**When you recognize multiple separate concerns:**
```
❌ WRONG: Fix bug A + implement feature B + refactor C in one PR

✅ CORRECT: 
  - Branch 1: fix/bug-a
  - Branch 2: feature/new-b
  - Branch 3: refactor/cleanup-c
```

**Benefits:**
- Cleaner git history
- Easier to revert if needed
- Simpler code review
- Easier to isolate regressions

---

## 📌 Quick Checklist Before Commit

- ✅ PHP syntax validation passed (`npm run build:php`)
- ✅ Propel ORM used for all database operations (no raw SQL)
- ✅ Asset paths use `SystemURLs::getRootPath()`
- ✅ Service classes used for business logic
- ✅ Type casting applied to dynamic values
- ✅ Deprecated HTML attributes replaced with CSS
- ✅ Bootstrap CSS classes applied correctly
- ✅ **All UI text wrapped with i18next.t() (JavaScript) or gettext() (PHP)**
- ✅ **No alert() calls - use bootstrap-notify instead**
- ✅ Tests pass (if available)
- ✅ Commit message follows imperative mood (< 72 chars)
- ✅ Branch name follows kebab-case format
- ✅ Logs cleared before testing: `rm -f src/logs/$(date +%Y-%m-%d)-*.log`

---

## 🔗 Additional References

- **Full standards:** See `.github/ai-preferences/preferences.yml`
- **Setup guide:** See `.github/ai-preferences/setup.md`
- **Contributing:** See `CONTRIBUTING.md`
- **Documentation:** See `README.md` and GitHub Wiki

---

*Last updated: November 1, 2025*

| Path | Purpose |
|------|---------|
| `src/ChurchCRM/Service/` | Business logic layer |
| `src/ChurchCRM/model/ChurchCRM/` | Propel ORM generated classes (don't edit) |
| `src/api/` | REST API entry point + routes |
| `src/Include/` | Utility functions, helpers, Config.php |
| `src/locale/` | i18n/translation strings |
| `src/skin/v2/` | Compiled CSS/JS from Webpack |
| `react/` | React TSX components |
| `webpack/` | Webpack entry points |
| `cypress/e2e/api/` | API test suites |
| `cypress/e2e/ui/` | UI test suites |
| `docker/` | Docker Compose configs |

---

## 🚀 AI Agent Practices

### Before Writing Code

1. **Understand the existing pattern** - Read similar functionality first
   - New API? Check `src/api/routes/` for existing endpoints
   - New Service method? Check what other methods in that Service do
   
2. **Check if the solution exists** - Use grep/search before implementing
   - Query classes generated? Use them (don't recreate)
   - Service method exists? Extend rather than duplicate

3. **Validate architectural choice** - Is this a legacy page or API?
   - New feature = API first, optional legacy page
   - Modifying legacy page = consider refactoring to API

### When Committing

✅ **Do:**
- Run `npm run build` before committing
- Clear logs: `rm -f src/logs/*.log`
- Test locally: `npm run test` (or specific suite)
- Use imperative commit messages < 72 chars
- Use `git mv` for renaming/moving tracked files
- Use `git rm` for deleting tracked files

❌ **Don't:**
- Commit with PHP syntax errors (prevents others from running)
- Use raw SQL anywhere (ORM only)
- Skip type casting on dynamic values
- Commit with stale test logs
- Use `mv` or `rm` directly on tracked files (breaks git history)

### Styling & Frontend

**SCSS Organization:**
- **Never use inline `<style>` tags** in PHP files
- **Always use SCSS files** in `src/skin/scss/` directory
- **Group by feature, not by page**: Name files by feature set (e.g., `_groups.scss` for all group-related pages)
- **Import in main file**: Add to `src/skin/churchcrm.scss` using `@include meta.load-css("scss/filename");`
- **Build after changes**: Run `npm run build` to compile SCSS to CSS

---

## 🔗 Additional References

- **Full standards:** See `.github/ai-preferences/preferences.yml`
- **Setup guide:** See `.github/ai-preferences/setup.md`
- **Contributing:** See `CONTRIBUTING.md`
- **Documentation:** See `README.md` and GitHub Wiki

---

*Last updated: November 1, 2025*
