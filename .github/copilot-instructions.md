# ChurchCRM AI Coding Agent Instructions

## Stack & Requirements
- PHP 8.2+ minimum (version 6.0.0+)
- Propel ORM (mandatory, never raw SQL)
- MySQL/MariaDB
- Slim Framework 4
- React/TypeScript
- Cypress testing
- AdminLTE UI with Bootstrap 4.6.2
- Webpack bundling

## Architecture Layers

### Legacy Pages
- Location: `src/*.php`
- Pattern: Traditional PHP form handlers
- Database: Propel ORM Query classes
- UI: AdminLTE + Bootstrap
- Don't modify: Use APIs for new features

### REST APIs (Slim Framework)
- Location: `src/api/routes/`
- Pattern: Service → Query classes → DTO response
- Services: PersonService, GroupService, FinancialService, SystemService, DepositService
- Response: JSON with `data` + `message` keys
- DI Container: Symfony at startup

### React Components
- Location: `react/`
- Output: Bundled via Webpack to `src/skin/v2/`
- Usage: Interactive UI (calendar, auth)

## Slim Middleware Order (CRITICAL)
```
addBodyParsingMiddleware()
addRoutingMiddleware()    // MUST be before add() calls
add(VersionMiddleware)
add(AuthMiddleware)       // After routing or 401 becomes 500
add(CorsMiddleware)
```

## Database Rules
- ALWAYS use Propel ORM Query classes
- NEVER use raw SQL or RunQuery()
- Cast dynamic IDs to (int)
- Check `=== null` not `empty()` for objects
- Access properties as objects: `$obj->prop`, never `$obj['prop']`

---

## Service Classes (Business Logic)

Located in `src/ChurchCRM/Service/` - handles domain logic separate from HTTP concerns.

Key Services:
- `PersonService` - Person/family operations
- `GroupService` - Group management
- `FinancialService` - Payments, pledges, funds
- `DepositService` - Deposit slip handling
- `SystemService` - System-wide operations

Example Usage:
```php
$service = $container->get('FinancialService');
$result = $service->addPayment($fam_id, $method, $amount, $date, $funds);
return $response->withJson(['data' => $result]);
```

---

## Asset Paths (SystemURLs)

ALWAYS use SystemURLs::getRootPath() for asset references:

```php
// CORRECT
<link rel="stylesheet" href="<?= SystemURLs::getRootPath() ?>/skin/v2/churchcrm.min.css">
<img src="<?= SystemURLs::getRootPath() ?>/images/logo.png">

// WRONG - Relative paths break in subdirectories
<link rel="stylesheet" href="/skin/v2/churchcrm.min.css">
```

---

## PHP 8.2+ Requirements

MANDATORY: All code must be compatible with PHP 8.2+ and avoid deprecated patterns.

Key Standards:
- Explicit nullable parameters: `?int $param = null` not `int $param = null`
- Dynamic properties need attribute: `#[\AllowDynamicProperties]`
- Use IntlDateFormatter instead of strftime
- Explicit global namespace: `\MakeFYString($id)` in namespaced code
- Version checks: `version_compare(phpversion(), '8.2.0', '<')`
- Public constants for shared values: `public const PHOTO_WIDTH = 200;`

---

## Code Standards

### Database Access
```php
// CORRECT - Propel ORM
$event = EventQuery::create()->findById((int)$eventId);
if ($event === null) { /* not found */ }

// WRONG
$result = RunQuery("SELECT * FROM events WHERE eventid = ?", $eventId);
$event['eventName'];  // TypeError: Cannot access offset on object
```

### Global Functions from Namespaced Code
```php
// CORRECT
namespace ChurchCRM\Service;
class MyService {
    public function test() {
        \MakeFYString($id);  // Backslash prefix
    }
}

// WRONG
MakeFYString($id);  // PHP Error: undefined function
```

### Slim 4 Routes
```php
// CORRECT - Inline closure
$group->post('/path', function ($request, $response) {
    return $response->withJson($data);
});

// WRONG - String reference doesn't work
$group->post('/path', 'MyHandler::process');
```

### Email Handling in APIs
```php
// CORRECT - Log but don't crash
if (!mail($to, $subject, $body)) {
    error_log("Email failed: " . $to);
}
return $response->withJson(['data' => $result]);

// WRONG
if (!mail($to, $subject, $body)) {
    throw new Exception("Email failed");  // Returns 500
}
```

### Null Safety
```php
// CORRECT
echo $notification?->title ?? 'No Title';

// WRONG
echo $notification->title;  // TypeError if null
```

---

## HTTP Headers (RFC 7230)

Use FILEINFO_MIME_TYPE, not FILEINFO_MIME:

```php
// CORRECT
$finfo = new \finfo(FILEINFO_MIME_TYPE);
$contentType = $finfo->file($photoPath);  // "image/png"
$response = $response->withHeader('Content-Type', trim($contentType));

// WRONG
$finfo = new \finfo(FILEINFO_MIME);  // Returns "image/png; charset=binary"
$response = $response->withHeader('Content-Type', $contentType);  // ERROR!
```

Always validate and trim header values:
```php
if ($contentType && is_string($contentType)) {
    $response = $response->withHeader('Content-Type', trim($contentType));
} else {
    $response = $response->withHeader('Content-Type', 'application/octet-stream');
}
```

---

## Photo Caching & HttpCache Middleware

Route-level cache, not app-level:

```php
// CORRECT - Route-level
$group->get('/photo', function ($request, $response, $args) {
    $photo = new Photo('Person', $args['personId']);
    return SlimUtils::renderPhoto($response, $photo);
})->add(new Cache('public', Photo::CACHE_DURATION_SECONDS));

// In Photo.php
class Photo {
    public const CACHE_DURATION_SECONDS = 7200;
}

// WRONG - App-level applies to all routes
$app->add(new Cache('public', 3600));
```

---

## HTML & CSS

Always use Bootstrap CSS, never deprecated HTML attributes:

```php
// CORRECT - Bootstrap classes
<div class="text-center align-top">Content</div>
<button class="btn btn-primary mt-3">Click</button>

// WRONG - Deprecated attributes
<div align="center" valign="top">Content</div>
<button style="margin-top: 12px;">Click</button>
```

---

## Internationalization (i18n)

CRITICAL: Always wrap user-facing text for translation.

JavaScript:
```javascript
$.notify(i18next.t('Operation completed'), {
    type: 'success',
    delay: 3000
});
```

PHP:
```php
echo gettext('Welcome to ChurchCRM');
```

NEVER use alert() - only use bootstrap-notify:
```javascript
// WRONG
alert('Operation completed');

// CORRECT
$.notify(i18next.t('Operation completed'), {
    type: 'success',
    delay: 3000,
    placement: { from: 'top', align: 'right' }
});
```

---

## Testing

API tests location: `cypress/e2e/api/private/[feature]/[endpoint].spec.js`

Helper commands (NEVER use cy.request directly):
```javascript
cy.makePrivateAdminAPICall("POST", "/api/payments", payload, 200)
cy.makePrivateUserAPICall("GET", "/api/events", null, 200)
cy.apiRequest({ method: "GET", url: "/api/events", failOnStatusCode: false })
```

Test categories required:
1. Successful operations - Valid payload, 200 response, check data structure
2. Validation tests - Invalid inputs (bad dates, missing fields), 400 response
3. Type safety - Verify type conversions don't cause runtime errors
4. Error handling - 401/403 auth, 404 not found, 500 errors
5. Edge cases - Null values, empty arrays, boundary conditions

UI tests: `cypress/e2e/ui/[feature]/`
- Maintain element IDs for test selectors
- Use cy.get() for queries, avoid text-based selectors
- Test complete user workflows end-to-end

---

## Development Workflows

Setup & Build:
```bash
npm ci                    # Install exact dependencies
npm run deploy            # Build everything (PHP + frontend)
npm run docker:dev:start  # Start Docker containers
```

Development Cycle:
```bash
npm run build:frontend    # Rebuild JS/CSS (watches via Webpack)
npm run build:php         # Update Composer dependencies
npm run docker:dev:logs   # View container logs
```

Testing:
```bash
npm run test              # Run all tests (headless)
npm run test:ui           # Interactive browser testing

# BEFORE every test run: clear old logs
rm -f src/logs/$(date +%Y-%m-%d)-*.log

# AFTER failures: review logs
cat src/logs/$(date +%Y-%m-%d)-php.log      # PHP errors, ORM errors
cat src/logs/$(date +%Y-%m-%d)-app.log      # App events
```

---

## Commit & PR Standards

Commit messages:
- Format: Imperative mood, < 72 chars, no file paths
- Examples: "Fix SQL injection in EditEventAttendees", "Replace deprecated HTML attributes with Bootstrap CSS", "Add missing element ID for test selector"
- Wrong: "Fixed the bug in src/EventEditor.php"

PR organization:
- Split large changes into logical feature branches
- Each PR addresses one specific bug or feature
- Related but separate concerns get separate branches
- Test each branch independently before creating PR

---

## Pre-commit Checklist

- PHP syntax validation passed (npm run build:php)
- Propel ORM used for all database operations (no raw SQL)
- Asset paths use SystemURLs::getRootPath()
- Service classes used for business logic
- Type casting applied to dynamic values
- Deprecated HTML attributes replaced with CSS
- Bootstrap CSS classes applied correctly
- All UI text wrapped with i18next.t() (JavaScript) or gettext() (PHP)
- No alert() calls - use bootstrap-notify instead
- Tests pass (if available)
- Commit message follows imperative mood (< 72 chars)
- Branch name follows kebab-case format
- Logs cleared before testing: rm -f src/logs/$(date +%Y-%m-%d)-*.log

---

## File Locations

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

## Agent Behavior Guidelines

### Documentation Files
- **DO NOT create** unnecessary `.md` review/planning documents unless explicitly requested
- **DO NOT create** analysis or audit documents for the user to review
- Make code changes directly without documentation overhead
- Only create documentation when the user specifically asks for it

### Git Commits
- **DO NOT auto-commit** changes without explicit user request
- **DO NOT run git commit** commands unless the user asks
- **DO ask permission** before creating commits: "Ready to commit? [describe changes]"
- Leave commits for the user to handle via their own workflow

### Code Changes
- Make all requested changes directly to files
- Use exact tool calls (replace_string_in_file, create_file, etc.)
- Keep explanations brief and focused on what was changed
- Verify changes were applied correctly but don't over-communicate

---

Last updated: November 2, 2025
