---
title: "API Development"
intent: "Patterns for creating and maintaining API endpoints using Slim and service layer"
tags: ["api","slim","routes","security"]
prereqs: ["slim-4-best-practices.md","php-best-practices.md"]
complexity: "intermediate"
---

# Skill: API Development

## Context
This skill covers creating and managing REST API endpoints in ChurchCRM using Slim 4.

## API Structure

- **Public/Private APIs**: `src/api/routes/` - General API endpoints
- **Admin APIs**: `src/admin/routes/api/` - Admin-only endpoints (NEW - preferred location)
- **Entry Point**: `src/api/index.php` - Main API router
- **Naming**: Prefer kebab-case endpoints (e.g., `/download-latest-release`)
- **Methods**: GET for reads, POST for actions that change state

## Slim 4 Routes

```php
// CORRECT - Inline closure
$group->post('/path', function ($request, $response) {
    return $response->withJson($data);
});

// WRONG - String reference doesn't work
$group->post('/path', 'MyHandler::process');
```

## API Error Handling (CRITICAL)

**ALWAYS use `SlimUtils::renderErrorJSON()` for API errors** — Located in `src/ChurchCRM/Slim/SlimUtils.php`

Never throw exceptions in route handlers. Wrap operations in try/catch and return sanitized error responses.

### Pattern

```php
use ChurchCRM\Slim\SlimUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

$group->post('/endpoint', function (Request $request, Response $response, array $args): Response {
    try {
        // Your operation here
        $result = doSomething();
        return SlimUtils::renderJSON($response, ['data' => $result]);
    } catch (\Throwable $e) {
        // Determine appropriate status code
        $status = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
        // Return sanitized error response with server-side logging
        return SlimUtils::renderErrorJSON($response, gettext('User-facing error message'), [], $status, $e, $request);
    }
});
```

### renderErrorJSON Behavior

- **Server-side logs**: exception class, message, file, line, trace, request method/path/IP/user-agent
- **Client receives**: sanitized message only (no traces, file paths, or credentials)
- **Sanitizes messages automatically**: detects and masks password/token/host patterns
- **Status passed as parameter**: `int $status` parameter (NOT via `response->withStatus(...)`)

### Signature

```php
SlimUtils::renderErrorJSON(
    Response $response,                    // Original $response (unmodified)
    ?string $message = null,               // Localized user-facing message
    array $extra = [],                     // Additional data to include in response
    int $status = 500,                     // HTTP status code
    ?\Throwable $exception = null,         // Exception for server-side logging
    ?Request $request = null               // Request for context logging
): Response
```

### Examples

```php
// Simple error with custom message
return SlimUtils::renderErrorJSON($response, gettext('Database error'), [], 500);

// Error with exception logging
return SlimUtils::renderErrorJSON($response, gettext('Upload failed'), [], 400, $e, $request);

// Error with dynamic status code
$status = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
return SlimUtils::renderErrorJSON($response, gettext('Operation failed'), [], $status, $e, $request);

// Error with extra response data
return SlimUtils::renderErrorJSON($response, gettext('Validation failed'), ['errors' => $errors], 400);
```

### DO NOT

- ❌ Throw exceptions in API routes (caught by error handler, exposes details to clients)
- ❌ Use `response->withStatus(500)` with renderErrorJSON (pass status as parameter)
- ❌ Return raw exception messages (use gettext() for localization and sanitization)
- ❌ Log exceptions separately in routes (renderErrorJSON handles all logging)

## API Response Standardization

**Maintain consistent error response format across all APIs** to prevent client-side errors.

**Key Pattern:**
- **Success responses**: `{'success': true, 'data': ...}` or `{'success': true, 'message': ...}`
- **Error responses**: ALWAYS use `message` field (not `error`, `msg`, or other variations)
- **Security**: Return generic error messages to users, not specific validation details

**Example:**

```php
// CORRECT - Uses 'message' field consistently
return SlimUtils::renderErrorJSON($response, gettext('Upload failed'), [], 400, $e, $request);
// Client receives: {success: false, message: "Upload failed"}

// Client-side error handler resilience
var errorText = error.message || error.error || error.msg || i18next.t("Unknown error");
```

## Middleware Order (CRITICAL - Slim 4 uses LIFO)

```php
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();
$app->add(CorsMiddleware::class);          // Last added, runs FIRST
$app->add(AuthMiddleware::class);          // Runs SECOND
$app->add(VersionMiddleware::class);       // First added, runs LAST
```

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

## Photo Caching & HttpCache Middleware

Route-level cache, not app-level:

```php
use Slim\HttpCache\Cache;
use ChurchCRM\Photo;

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

## Email Handling in APIs

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

## Admin API Calls (JavaScript)

**Recommended Approaches for `/admin/api/` calls:**

1. **`window.CRM.AdminAPIRequest()`** - Preferred for jQuery-based code
2. **Native `fetch()`** - Acceptable for modern JavaScript code

### Option 1: AdminAPIRequest (jQuery-based)

```javascript
// Use for jQuery-heavy pages or when you need jQuery promise syntax
window.CRM.AdminAPIRequest({
    path: 'orphaned-files/delete-all',
    method: 'POST'
})
.done(function(response) {
    window.CRM.notify(i18next.t('Success'), { type: 'success' });
})
.fail(function(xhr) {
    window.CRM.notify(i18next.t('Error'), { type: 'error' });
});
```

### Option 2: Native fetch (Modern JavaScript)

```javascript
// Use for modern JavaScript code or ES6+ modules
fetch(window.CRM.root + '/admin/api/system/config/settingName', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json'
    },
    body: JSON.stringify({ value: settingValue })
})
.then(response => response.json())
.then(data => {
    window.CRM.notify(i18next.t('Settings saved'), { type: 'success' });
})
.catch(error => {
    window.CRM.notify(i18next.t('Error saving settings'), { type: 'error' });
});
```

### AdminAPIRequest Details

- Automatically prepends `/admin/api/` to the path
- Sets proper `Content-Type: application/json` and `dataType: 'json'`
- Integrates with CRM error handler for consistent error display
- Returns jQuery promise (supports `.done()`, `.fail()`, `.always()`)
- Path format: `'database/reset'` becomes `/admin/api/database/reset`

## Webpack TypeScript API Utilities

**For webpack TypeScript bundles, use `webpack/api-utils.ts`** - ensures safe API URL construction.

**Critical Issue:** Webpack bundles load **before** `window.CRM` is initialized.

```typescript
import { buildAPIUrl, buildAdminAPIUrl, fetchAPIJSON } from './api-utils';

// URL construction (safe - evaluated at runtime)
const url = buildAPIUrl('person/123/avatar');           // → '/api/person/123/avatar'
const adminUrl = buildAdminAPIUrl('system/config/key'); // → '/admin/api/system/config/key'

// Fetch with automatic error handling
const data = await fetchAPIJSON<AvatarInfo>('person/123/avatar');
```

**Available Functions:**
- `getRootPath()` - Get `window.CRM.root` dynamically
- `buildAPIUrl(path)` - Build `/api/` endpoint URL
- `buildAdminAPIUrl(path)` - Build `/admin/api/` endpoint URL
- `fetchAPI(path, options)` - Fetch with error logging
- `fetchAPIJSON<T>(path, options)` - Fetch and parse JSON (recommended)
- `fetchAdminAPI(path, options)` - Admin API fetch variant
- `fetchAdminAPIJSON<T>(path, options)` - Admin API JSON variant

## Creating API Endpoints

**Only create API endpoints when:**
- Needed by external clients
- Required for AJAX operations
- Shared across multiple pages

**Do NOT create API endpoints** if a service method is only called from a legacy page - call the service directly instead.

## OpenAPI Documentation (REQUIRED for all API changes)

ChurchCRM uses `zircote/swagger-php` 4.x to generate OpenAPI 3.0 specs from DocBlock annotations. The generated specs power the public API reference at `docs.churchcrm.io`.

**When adding or updating any API endpoint, you MUST add/update the `@OA\*` annotation.**

### Annotation placement rules

**Named functions** — DocBlock immediately above the `function` definition:
```php
/**
 * @OA\Get(
 *     path="/events",
 *     operationId="getAllEvents",
 *     summary="List all events",
 *     tags={"Calendar"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(response=200, description="Event list", @OA\JsonContent(...)),
 *     @OA\Response(response=401, description="Unauthorized")
 * )
 */
function getAllEvents(Request $request, Response $response, array $args): Response {
```

**Closures / arrow functions** — standalone DocBlock immediately above the `$group->get(...)` call:
```php
/**
 * @OA\Get(
 *     path="/persons/search/{query}",
 *     operationId="searchPersons",
 *     summary="Search persons by name or email",
 *     tags={"People"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="query", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\Response(response=200, description="Matching persons (max 15)")
 * )
 */
$group->get('/search/{query}', function (Request $request, Response $response, array $args): Response {
```

### Auth security by middleware

| Middleware | OpenAPI security | Extra responses |
|---|---|---|
| None (`/public/*`) | omit `security` key | — |
| `AuthMiddleware` (global) | `security={{"ApiKeyAuth":{}}}` | 401 |
| Role middleware (Finance, Admin, etc.) | `security={{"ApiKeyAuth":{}}}` | 401 + 403 |

### Tags

Public spec: `Utility`, `Auth`, `Registration`, `Calendar`, `Lookups`

Private spec: `Calendar`, `People`, `Families`, `Groups`, `Properties`, `Finance`, `Users`, `2FA`, `System`, `Admin`, `Cart`, `Search`, `Map`

### Regenerating the spec

After annotating, run from `CRM/src/`:
```bash
composer run openapi-public   # → CRM/openapi/public-api.yaml
composer run openapi-private  # → CRM/openapi/private-api.yaml
```

Commit the updated YAML files to the CRM repo. The rest is automated:

- **On PR/branch push**: `validate-openapi.yml` generates both specs and uploads them as artifacts for review.
- **On merge to master**: `publish-openapi.yml` regenerates specs, commits any changes to `CRM/openapi/`, then dispatches a `repository_dispatch` event to `docs.churchcrm.io`, which pulls the latest YAMLs and regenerates MDX automatically.

To manually sync the docs site (e.g., during local dev):
```bash
cp CRM/openapi/public-api.yaml docs.churchcrm.io/openapi/
cp CRM/openapi/private-api.yaml docs.churchcrm.io/openapi/
cd docs.churchcrm.io && npm run regen
```

### Global annotations / tags

Defined in `src/api/openapi/openapi-public-info.php` and `src/api/openapi/openapi-private-info.php`. If you add a new tag, add it there first.

## Files

**API Routes:** `src/api/routes/`, `src/admin/routes/api/`
**Utilities:** `src/ChurchCRM/Slim/SlimUtils.php`
**Middleware:** `src/ChurchCRM/Slim/Middleware/`
**OpenAPI info:** `src/api/openapi/openapi-public-info.php`, `src/api/openapi/openapi-private-info.php`
**Generated specs:** `CRM/openapi/public-api.yaml`, `CRM/openapi/private-api.yaml`
**Docs site:** `docs.churchcrm.io/openapi/`, `docs.churchcrm.io/docs/public-api/`, `docs.churchcrm.io/docs/private-api/`
