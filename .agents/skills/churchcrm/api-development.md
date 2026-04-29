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

- **Public/Private APIs**: `src/api/routes/` - General API endpoints (FinanceRoleAuth, etc.)
- **Admin APIs**: `src/admin/routes/api/` - Admin-only endpoints (AdminRoleAuth)
- **Finance APIs**: `src/api/routes/finance/` - Finance endpoints (FinanceRoleAuth)
- **Entry Point**: `src/api/index.php` - Main API router
- **Naming**: Prefer kebab-case endpoints (e.g., `/download-latest-release`)
- **Methods**: GET for reads, POST for actions that change state

### API Placement Rule <!-- learned: 2026-03-28 -->

Non-admin APIs must live under `/api/` (`src/api/routes/`), not under module-specific `/{module}/api/` paths.
Admin-only APIs go under `/admin/api/` or `/{module}/api/` (e.g., `/finance/api/funds` for admin fund CRUD).

| API type | Location | Middleware |
|----------|----------|-----------|
| Public/role-based read | `src/api/routes/` | Role-specific (e.g., `FinanceRoleAuthMiddleware`) |
| Admin CRUD | `src/admin/routes/api/` or `src/{module}/routes/api/` | `AdminRoleAuthMiddleware` |

### OpenAPI Documentation <!-- learned: 2026-03-28 -->

All new API endpoints must include OpenAPI 3.0 annotations:
- Path definitions with request/response schemas
- Parameter descriptions and types
- Error response codes (400, 401, 403, 404)
- Example payloads

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

### Never Throw HttpBadRequestException in Route Handlers <!-- learned: 2026-04-07 -->

API route handlers must use `SlimUtils::renderErrorJSON()` instead of throwing `HttpBadRequestException`. Thrown exceptions can expose stack traces and bypass the custom error handler. `HttpNotFoundException` is fine to throw (Slim handles it natively), but `HttpBadRequestException` should return a JSON error response directly.

```php
// ✅ CORRECT
return SlimUtils::renderErrorJSON($response, gettext('invalid event type id'), [], 400);

// ❌ WRONG — can expose stack traces
throw new HttpBadRequestException($request, gettext('invalid event type id'));
```

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

## Middleware Order (CRITICAL - Slim 4 uses LIFO) <!-- learned: 2026-04-07 -->

> **Full reference:** [`slim-4-best-practices.md` → Middleware Order](./slim-4-best-practices.md)

**TL;DR:** `addErrorMiddleware()` MUST be called AFTER `addRoutingMiddleware()`. Wrong order → raw 500 on 404s.

```php
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();
$errorMiddleware = $app->addErrorMiddleware(true, true, true);  // AFTER routing
SlimUtils::registerDefaultJsonErrorHandler($errorMiddleware);
$app->add(new CorsMiddleware());
$app->add(AuthMiddleware::class);
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

ChurchCRM uses `zircote/swagger-php` 4.x to generate OpenAPI 3.0 specs from DocBlock annotations. The generated specs power the public API reference in the Documentation.

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

## Calling External APIs: Nominatim Geocoding <!-- learned: 2026-03-08 -->

When calling external APIs like Nominatim (OpenStreetMap geocoding), use **comma-separated address format** without country code in simple queries. Structured parameters work better for precise results.

**Pattern: GeoUtils.getLatLong()**

```php
// ✅ CORRECT - Comma-separated format (no country code)
$params = [
    'q' => '13216 NE 100th St, Kirkland, WA, 98033',
    'format' => 'json',
    'limit' => 1
];
$url = 'https://nominatim.openstreetmap.org/search?' . http_build_query($params);

// ✅ PREFERRED - Structured parameters (better accuracy)
$params = [
    'street' => '13216 NE 100th St',
    'city' => 'Kirkland',
    'state' => 'WA',
    'postalcode' => '98033',
    'format' => 'json',
    'limit' => 1
];

// ❌ WRONG - Appending country code (causes matching to fail)
$params = [
    'q' => '13216 NE 100th St Kirkland WA 98033 US',  // Fails to match
    'format' => 'json'
];

// ❌ WRONG - Space-separated without commas
$params = [
    'q' => implode(' ', [$street, $city, $state, $zip, $country]),
    'format' => 'json'
];
```

**Key Points:**
- Always use comma-separated format: `street, city, state, zip`
- Omit country code from simple queries (causes matching failures)
- Prefer structured parameters (street, city, state, postalcode) when components available
- Include required Nominatim headers: `User-Agent: ChurchCRM/7.0 (+https://churchcrm.io)`
- Log API calls and responses for debugging geocoding issues

## CSV Exports from API Endpoints <!-- learned: 2026-03-29 -->

Use `CsvExporter` (`src/ChurchCRM/utils/CsvExporter.php`) for all CSV downloads from Slim routes. Return via PSR-7 response — never `header()` + `exit`.

```php
$exporter = new CsvExporter();
$exporter->insertHeaders(['Col1', 'Col2']);

// ✅ CORRECT — insert rows incrementally inside the loop (avoids buffering full result in memory)
foreach ($source as $item) {
    $exporter->insertRow([$item['a'], $item['b']]);
}

// ❌ WRONG — building $rows[] array then calling insertRows() buffers everything in RAM
$rows = [];
foreach ($source as $item) { $rows[] = [...]; }
$exporter->insertRows($rows);

$response = $response
    ->withHeader('Content-Type', 'text/csv; charset=UTF-8')
    ->withHeader('Content-Disposition', 'attachment; filename="export.csv"');
$response->getBody()->write($exporter->getContent());
return $response;
```

**Feature-flag middleware on export routes** — Only add `SundaySchoolEnabledMiddleware` (or similar) to routes whose content is *exclusively* about that feature. General exports that merely enrich data with SS info should NOT be gated — block only the SS-specific logic inside the handler when the feature is off.

### Route All Check-in/Check-out Through the Event Model <!-- learned: 2026-04-08 -->

When migrating legacy pages, refactor **all** code paths to use the same model method
instead of copy-pasting the persistence logic. For event check-in, the canonical entry
points are `Event::checkInPerson()` and `Event::checkOutPerson()` — they set consistent
timestamps, create the timeline Note (`type='event'`), and fire hooks.

Even cart-to-event bulk check-in — which historically created `EventAttend` rows directly —
should call the model method so every path produces identical side effects.

```php
// ❌ Direct ORM bypasses model logic (no timeline note, inconsistent timestamps)
$ea = new EventAttend();
$ea->setEventId($eventId)->setPersonId($personId)->save();

// ✅ Use model method — sets timestamps, writes timeline note, fires hooks
$event = EventQuery::create()->findPk($eventId);
if ($event !== null) {
    $event->checkInPerson($personId);
}
```

Same rule applies anywhere a "canonical" mutator method exists on a model — prefer the
model method over hand-rolling the same sequence of ORM calls.

### Note Privacy: nte_Private Stores personId, Not a Boolean <!-- learned: 2026-03-29 -->

`nte_Private` is **not** a boolean flag. It stores either `0` (public) or the author's `personId` (private). `Note::isVisible($personId)` checks `getPrivate() === $personId`, so storing `1` instead of a real personId means only person with ID=1 can see the note.

```php
// ✅ CORRECT — store the author's personId for private notes
$private = !empty($input['private']) ? (int) $currentUser->getPersonId() : 0;

// For PUT, preserve the original author's visibility (not the editor's):
$private = !empty($input['private']) ? (int) $note->getEnteredBy() : 0;

// ❌ WRONG — stores 1, which only makes it visible to person with id=1
$private = !empty($input['private']) ? 1 : 0;
```

## DELETE Endpoints: Block-If-In-Use over Cascade <!-- learned: 2026-04-15 -->

**Project convention for every new DELETE API endpoint:** return **409 Conflict**
when the resource is still referenced by dependent data, rather than cascading
the delete or silently orphaning the children. This is strictly safer than the
legacy `*Delete.php` pages, which tend to cascade.

### Pattern

```php
use ChurchCRM\model\ChurchCRM\<Child>Query;

$group->delete('/{id:[0-9]+}', function (Request $request, Response $response, array $args): Response {
    try {
        $id = (int) $args['id'];
        $parent = <Parent>Query::create()->findPk($id);
        if ($parent === null) {
            return SlimUtils::renderErrorJSON($response, gettext('<Parent> not found'), [], 404);
        }

        // Block deletion when any children still reference the parent.
        // Callers must clear the references first — we do NOT cascade.
        $childCount = <Child>Query::create()->filterBy<ParentFkColumn>($id)->count();
        if ($childCount > 0) {
            return SlimUtils::renderErrorJSON(
                $response,
                sprintf(
                    gettext('Cannot delete <parent>: %d <child-name> still reference it. Remove them first.'),
                    $childCount
                ),
                [],
                409
            );
        }

        $parent->delete();
        return SlimUtils::renderSuccessJSON($response);
    } catch (\Throwable $e) {
        return SlimUtils::renderErrorJSON($response, gettext('Failed to delete <parent>'), [], 500, $e, $request);
    }
});
```

### OpenAPI annotation

Every such DELETE route must add a `409` response:

```php
 * @OA\Delete(
 *     ...
 *     @OA\Response(response=200, description="Deleted"),
 *     @OA\Response(response=404, description="Not found"),
 *     @OA\Response(response=409, description="<Parent> is in use by <child-name>"),
 *     ...
 * )
```

### Delegate to a service if one already blocks+renumbers

Some resources have a service that already encapsulates the block-then-renumber
behavior. **Prefer delegation over inlining the check**, so all callers get the
same semantics. Canonical example:

```php
use ChurchCRM\Service\DonationFundService;

$group->delete('/{id:[0-9]+}', function (Request $request, Response $response, array $args): Response {
    try {
        // DonationFundService::deleteFund:
        //   throws InvalidArgumentException if the fund is missing (→ 404)
        //   throws RuntimeException if pledge_plg rows reference the fund (→ 409)
        //   renumbers fun_Order on success
        (new DonationFundService())->deleteFund((int) $args['id']);
        return SlimUtils::renderSuccessJSON($response);
    } catch (\InvalidArgumentException $e) {
        return SlimUtils::renderErrorJSON($response, gettext('Donation fund not found'), [], 404);
    } catch (\RuntimeException $e) {
        return SlimUtils::renderErrorJSON(
            $response,
            gettext('Cannot delete donation fund: it is still referenced by one or more pledges.'),
            [],
            409,
            $e,
            $request
        );
    } catch (\Throwable $e) {
        return SlimUtils::renderErrorJSON($response, gettext('Failed to delete donation fund'), [], 500, $e, $request);
    }
});
```

**Do not** leak the service's raw exception message to the client — localize via
`gettext()` and log the exception via `renderErrorJSON`'s 5th/6th arguments.
`SlimUtils::renderErrorJSON` sanitizes messages matching
`/(password|credential|secret|api[_-]?key|token|username|user|host|localhost|127\.0\.0|\d{1,3}\.\d{1,3})/i`
back to a default, and the service message `"Cannot delete fund 'X': it has N associated pledge(s)."`
will be sanitized if the fund name contains `user` / `host` / etc.

### Reference implementations in-tree

| Resource | Route file | Pattern | Dependent table + FK |
|---|---|---|---|
| DonationFund | `src/api/routes/finance/finance-donation-funds.php` | Service-delegated | `pledge_plg.plg_fundID` |
| FundRaiser | `src/api/routes/finance/finance-fundraisers.php` | Inline count | `donateditem_di.di_fr_ID` |
| PropertyType | `src/api/routes/system/property-types.php` | Inline count | `property_pro.pro_prt_ID` |
| VolunteerOpportunity | `src/api/routes/system/volunteer-opportunities.php` | Inline count | `person2volunteeropp_p2vo.p2vo_vol_ID` |
| Event Type | `src/event/routes/types.php` | Block + cascade count-names | `events_event.event_type` |

### When cascade IS the right answer

Block-if-in-use is the default, but some FK relationships represent
owner-owned composition where cascade is semantically correct. Known-good
cascades in this codebase:

- **Deposit → Pledges** — `Deposit::preDelete()` (`src/ChurchCRM/model/ChurchCRM/Deposit.php`)
  calls `$this->getPledges()->delete()`. A deposit is a batch container; the
  pledges inside it have no independent existence, so cascading is correct.
- **Family → Notes + non-Payment pledges** — `src/api/routes/people/people-family.php`
  deletes notes and non-`Payment` pledges but blocks the delete for
  non-finance users if `Payment` pledges exist. This is a hybrid:
  finance-protected data is blocked, the rest cascades.
- **Property (definition) → RecordProperty assignments** —
  `src/api/routes/people/people-properties.php` deletes the assignments
  before deleting the definition. A property definition owns its assignments.

When in doubt, **block**. Cascading is a one-way door and the legacy `*Delete.php`
pages have a history of orphaning rows that the API shouldn't inherit.

## Files

**API Routes:** `src/api/routes/`, `src/admin/routes/api/`
**Utilities:** `src/ChurchCRM/Slim/SlimUtils.php`
**Middleware:** `src/ChurchCRM/Slim/Middleware/`
**OpenAPI info:** `src/api/openapi/openapi-public-info.php`, `src/api/openapi/openapi-private-info.php`
**Generated specs:** `CRM/openapi/public-api.yaml`, `CRM/openapi/private-api.yaml`
**Documentation site:** `docs.churchcrm.io/openapi/`, `docs.churchcrm.io/docs/public-api/`, `docs.churchcrm.io/docs/private-api/`

### HTML + JSON Content Negotiation in Middleware <!-- learned: 2026-04-22 -->

Middleware that serves both a browser-facing HTML route and a JSON API
route under the same auth/validation logic (e.g. `PublicCalendarMiddleware`
gating both `/external/calendars/{token}` and `/api/public/calendar/{token}/...`)
should centralise error-response rendering and pick the format by
**path prefix first, Accept header as fallback** — never return a bare
`$response->withStatus(403)` with no body, which produces a blank white
screen for the HTML consumer.

```php
private function renderError(
    ServerRequestInterface $request,
    ResponseInterface $response,
    int $status,
    string $title,
    string $message,
    string $icon = 'ti-calendar-off',
): ResponseInterface {
    if ($this->prefersJson($request)) {
        return SlimUtils::renderJSON($response, [
            'error' => $title, 'message' => $message,
        ], $status);
    }
    $renderer = new PhpRenderer(SystemURLs::getDocumentRoot() . '/external/templates/calendar/');
    return $renderer->render($response->withStatus($status), 'error.php', [
        'title' => $title, 'message' => $message, 'icon' => $icon,
    ]);
}

private function prefersJson(ServerRequestInterface $request): bool
{
    $path = $request->getUri()->getPath();
    if (str_contains($path, '/api/')) return true;
    $accept = strtolower($request->getHeaderLine('Accept'));
    return str_contains($accept, 'application/json') && !str_contains($accept, 'text/html');
}
```

The HTML branch renders a branded template wrapped in `HeaderNotLoggedIn`
/ `FooterNotLoggedIn` using `ChurchMetaData::getChurchLogoURL()` so the
recipient of a shared link sees the church's identity, not a raw 4xx.

### AttendanceCounts Round-trip Security <!-- learned: 2026-04-22 -->

API endpoints that accept nested `AttendanceCounts` arrays (e.g.
`POST /api/events`, `POST /api/events/:id`) MUST NOT trust client-supplied
`id`, `name`, or `notes`. The attack surface:

1. `id` — writing counts for arbitrary category IDs not belonging to the
   event's type, or overwriting unrelated rows.
2. `name` — tampering with the displayed category label (potential
   stored XSS if re-rendered unsanitized).
3. `notes` — injecting raw HTML that renders in reports or dashboards.

Defenses, applied in a shared helper (`applyEventExtendedFields()`):

```php
// Whitelist countId by EventCountName rows for the event's type.
$typeId = (int) $event->getType();
$allowedNames = [];
foreach (EventCountNameQuery::create()->filterByTypeId($typeId)->find() as $cn) {
    $allowedNames[(int) $cn->getId()] = (string) $cn->getName();
}

foreach ($input['AttendanceCounts'] as $row) {
    $countId = (int) ($row['id'] ?? 0);
    if ($countId <= 0 || !array_key_exists($countId, $allowedNames)) {
        continue; // silently drop foreign ids
    }
    $count = EventCountsQuery::create()->findPk([$eventId, $countId])
        ?? (new EventCounts())->setEvtcntEventid($eventId)->setEvtcntCountid($countId);
    // Canonical name comes from the DB, not the payload.
    $count->setEvtcntCountname($allowedNames[$countId]);
    $count->setEvtcntCountcount((int) ($row['count'] ?? 0));
    // Notes are plain text — strip tags server-side.
    $count->setEvtcntNotes(InputUtils::sanitizeText((string) ($row['notes'] ?? '')));
    $count->save();
}
```

Apply the same pattern for any other nested "rows belonging to a parent
entity" API shape.

### API-canonical Field Names, Shared `applyExtendedFields` Helper <!-- learned: 2026-04-22 -->

When unifying legacy form POST handlers into a single API endpoint, keep
the API's field names canonical (`Title`, `Type`, `Start`, `End`) and
extract the "extended fields" (InActive / LinkedGroupId / AttendanceCounts
for events) into a helper that `newXxx()` and `updateXxx()` both call
**after** saving the main entity. This keeps the two write paths
idempotent and impossible to drift:

```php
function newEvent(...) {
    // ... create, setTitle, setEventType, setCalendars, save
    applyEventExtendedFields($event, $input);
    return SlimUtils::renderSuccessJSON($response);
}

function updateEvent(...) {
    $Event->fromArray($input); // copies Title/Desc/Start/End/InActive automatically
    // ... setCalendars, save
    applyEventExtendedFields($Event, $input);
    return SlimUtils::renderSuccessJSON($response);
}
```

Enrich the `GET /{id}` response to include the extended fields so the UI
doesn't need extra round-trips — fall back to type defaults when the
entity has no rows yet (e.g. for Attendance Counts, return the event
type's `EventCountName` categories with `count: 0`).

### League\Csv `SyntaxError` — Catch for Clean 400 on Duplicate Column Headers <!-- learned: 2026-04-21 -->

`League\Csv\Reader::getHeader()` throws `League\Csv\SyntaxError` when the CSV has duplicate column names. Without a catch this surfaces as an unhandled 500. Always wrap the upload endpoint's header read in a try/catch and return a 400 with a human-readable message:

```php
use League\Csv\Reader;
use League\Csv\SyntaxError;

try {
    $csv = Reader::createFromPath($tmpPath, 'r');
    $csv->setHeaderOffset(0);
    $headers = $csv->getHeader(); // throws SyntaxError on duplicates
} catch (SyntaxError $e) {
    return SlimUtils::renderErrorJSON($response, 'Your CSV has duplicate column names. Each column header must be unique.', 400);
}
```

This is the only checked exception `League\Csv` throws for malformed headers; other parse errors surface as standard `Exception`.

### CSV Template Column Uniqueness — Category-Suffix Pattern <!-- learned: 2026-04-21 -->

When generating a CSV template that includes custom fields and properties from multiple extension buckets (PersonCustom, FamilyCustom, PersonProperty, FamilyProperty), column name collisions are inevitable. Use a `"{name} ({category})"` suffix to keep headers unique and visible in Excel, and fall back to a `(2)`, `(3)` counter for residual duplicates within the same category:

```php
$seen = [];
foreach ($fields as &$field) {
    $candidate = $field['name'] . ' (' . $field['category'] . ')';
    if (isset($seen[$candidate])) {
        $seen[$candidate]++;
        $candidate .= ' (' . $seen[$candidate] . ')';
    } else {
        $seen[$candidate] = 1;
    }
    $field['header'] = $candidate; // stored in English — NOT gettext()
}
```

Store the header in **English only** (never wrapped in `gettext()`). The header must be locale-stable: the same CSV downloaded in French and re-uploaded under English locale must still auto-map correctly. The category suffix (`Person Custom Fields`, `Family Properties`, etc.) lives in the PHP constant, not the translation layer.
