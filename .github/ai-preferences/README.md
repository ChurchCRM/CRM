# AI Preferences & Setup

## Files

| File | Purpose |
|------|---------|
| `preferences.yml` | Core AI agent configuration in YAML format |
| `setup.md` | Tool integration and validation guide |
| `README.md` | This file - Overview |

---

## Quick Start

### GitHub Copilot
1. VS Code Settings: `Cmd/Ctrl + ,`
2. Search: "GitHub › Copilot: Custom Instructions"
3. Set: `.github/ai-preferences/preferences.yml`
4. Reload: `Cmd/Ctrl + Shift + P` > "Reload Window"

### Claude/Cursor
1. Add `.github/ai-preferences/preferences.yml` as context file
2. Reference: "Follow the ChurchCRM AI preferences"

---

## Core Standards at a Glance

### Database (MANDATORY)
```php
// CORRECT - Always Propel ORM
$event = EventQuery::create()->findById((int)$eventId);
if ($event === null) { /* not found */ }

// WRONG - Never raw SQL
$result = RunQuery("SELECT * FROM events WHERE eventid = ?", $eventId);
```

### API Response Format
```php
// CORRECT
return $response->withJson(['data' => $result, 'message' => 'Success']);

// WRONG
return $response->withJson($result);
```

### HTTP Headers (RFC 7230)
```php
// CORRECT
$finfo = new \finfo(FILEINFO_MIME_TYPE);
$contentType = $finfo->file($photoPath);
$response = $response->withHeader('Content-Type', trim($contentType));

// WRONG
$finfo = new \finfo(FILEINFO_MIME);  // Includes charset metadata
$response = $response->withHeader('Content-Type', $contentType);
```

### HTML/CSS
```php
// CORRECT - Bootstrap classes
<div class="text-center align-top">Content</div>
<button class="btn btn-primary mt-3">Click</button>

// WRONG - Deprecated attributes
<div align="center" valign="top">Content</div>
<button style="margin-top: 12px;">Click</button>
```

### Internationalization
```javascript
// CORRECT
window.CRM.notify(i18next.t('Operation completed'), { type: 'success' });

// WRONG
alert('Operation completed');
window.CRM.notify('Operation completed', { type: 'success' });
```

### Slim Middleware Order
```
addBodyParsingMiddleware()
addRoutingMiddleware()    // MUST be before add() calls
add(VersionMiddleware)
add(AuthMiddleware)       // After routing or 401 becomes 500
add(CorsMiddleware)
```

### PHP 8.2+ Requirements
```php
// CORRECT - Explicit nullable
function test(?int $param = null): void { }

// CORRECT - Dynamic properties
#[\AllowDynamicProperties]
class MyClass { }

// CORRECT - Global functions in namespaced code
namespace ChurchCRM\Service;
\MakeFYString($id);  // Backslash prefix

// WRONG - Implicit nullable (deprecated)
function test(int $param = null): void { }
```

---

## Pre-commit Checklist

- ✅ PHP syntax validation (php -l)
- ✅ Propel ORM only (no raw SQL)
- ✅ SystemURLs::getRootPath() for assets
- ✅ Service classes for business logic
- ✅ Type casting for dynamic values
- ✅ Bootstrap CSS (no deprecated attributes)
- ✅ i18next.t() for all UI text
- ✅ window.CRM.notify() (no alert())
- ✅ Tests pass
- ✅ Commit message: imperative, < 72 chars
- ✅ Branch name: kebab-case

---

## Testing

API tests: `cypress/e2e/api/private/[feature]/[endpoint].spec.js`

Use helpers (never cy.request directly):
```javascript
cy.makePrivateAdminAPICall("POST", "/api/payments", payload, 200)
cy.makePrivateUserAPICall("GET", "/api/events", null, 200)
```

Test categories: successful operations, validation, type safety, error handling, edge cases

---

Last updated: November 2, 2025
