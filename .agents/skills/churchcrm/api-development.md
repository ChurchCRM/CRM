---
title: "API Development"
intent: "Patterns for creating and maintaining API endpoints using Slim and service layer"
tags: ["api","slim","routes","security"]
prereqs: ["[[slim-4-best-practices]]","[[php-best-practices]]"]
complexity: "intermediate"
---

# API Development

REST endpoints in ChurchCRM use Slim 4 route files and the service layer ([`service-layer.md`](./service-layer.md)). Setup and middleware: [`slim-4-best-practices.md`](./slim-4-best-practices.md).

## Structure and placement

- Entry point: `src/api/index.php`
- Non-admin APIs live under `/api/` in `src/api/routes/<domain>/` (finance routes use `FinanceRoleAuthMiddleware`)
- Admin-only APIs live under `/admin/api/` (`src/admin/routes/api/`) behind `AdminRoleAuthMiddleware`. Every other API lives under `/api/` and carries its own role middleware (Edit Records, Manage Groups, Finance, …); a module gets no `/{module}/api/` of its own
- Endpoints are kebab-case (`/download-latest-release`); GET reads, POST/PUT/DELETE change state
- Create an endpoint for external clients, AJAX operations, or logic shared across pages. A page that needs a service method calls the service directly

| API type | Location | Middleware |
|----------|----------|-----------|
| Role-based read/write | `src/api/routes/` | Role-specific (e.g. `FinanceRoleAuthMiddleware`) |
| Admin CRUD | `src/admin/routes/api/` | `AdminRoleAuthMiddleware` |

Use inline closures for handlers: `$group->post('/path', function (Request $request, Response $response): Response { ... });`

## Error handling

Return errors with `SlimUtils::renderErrorJSON()` (`src/ChurchCRM/Slim/SlimUtils.php`). Wrap operations in try/catch and return the sanitized response; `renderErrorJSON` owns all error logging.

```php
$group->post('/endpoint', function (Request $request, Response $response, array $args): Response {
    try {
        $result = doSomething();
        return SlimUtils::renderJSON($response, ['data' => $result]);
    } catch (\Throwable $e) {
        $status = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
        return SlimUtils::renderErrorJSON($response, gettext('User-facing error message'), [], $status, $e, $request);
    }
});
```

Signature: `renderErrorJSON(Response $response, ?string $message = null, array $extra = [], int $status = 500, ?\Throwable $exception = null, ?Request $request = null)`. Pass the status as the `$status` argument and the message through `gettext()`.

For a bad request, return `SlimUtils::renderErrorJSON($response, gettext('invalid event type id'), [], 400)`. `HttpNotFoundException` may be thrown; Slim handles it natively.

Extra response data: `renderErrorJSON($response, gettext('Validation failed'), ['errors' => $errors], 400)`.

### Canonical API error shape

Every `/api` error response is built by `SlimUtils::buildErrorPayload()` and emitted by `renderErrorJSON()`, the role and auth middleware, and the Slim error handlers:

```json
{
  "success": false,
  "message": "User not found",
  "error":   "User not found",
  "code":    404
}
```

- `message` is canonical; read it in client code
- `error` is an alias of `message` for existing `responseJSON.error` consumers and always carries the same value
- `code` mirrors the HTTP status
- Extra keys merge on top: the Slim error handlers add `request: {method, path}`; a route passes `['errors' => [...]]` through `$extra`

Middleware that builds its own JSON error uses the same builder:

```php
$response->getBody()->write(json_encode(SlimUtils::buildErrorPayload(gettext('Invalid API key'), 401)));
```

Success responses are `{'success': true, 'data': ...}` or `{'success': true, 'message': ...}`. Client code tolerates both shapes: `error.message || error.error || error.msg || i18next.t("Unknown error")`.

### Redaction

`renderErrorJSON` logs exception class, message, file, line, trace and request context server-side. The client receives a sanitized message only.

`SlimUtils::containsSensitiveValue()` replaces a message with the generic "An error occurred..." only when it matches credential-shaped context: `name=value` assignments for credential names, DSN fragments (`host=`, `dbname=`, `user=`), credentials in a URL, PEM key material, JWTs, opaque runs of 40+ characters, and complete IPv4 addresses. Bare words such as "user" or "host" pass through, so `User not found` stays readable.

`scripts/test-error-redaction.php` (`npm run test:php`, also in CI) covers both directions. Add a case there whenever `SENSITIVE_VALUE_PATTERNS` changes.

`sanitizeErrorMessage()` collapses ORM/PDO failures to "A database error occurred...". Detect them by exception class (`PropelException`, `PDOException`); the generated models live under `src/ChurchCRM/model/`, so a file-path check never matches.

## Middleware order

Slim 4 is LIFO; `addErrorMiddleware()` follows `addRoutingMiddleware()`. Reference: [`slim-4-best-practices.md`](./slim-4-best-practices.md) -> "Middleware Order".

## HTTP headers

Detect MIME type with `FILEINFO_MIME_TYPE` and trim it:

```php
$finfo = new \finfo(FILEINFO_MIME_TYPE);
$contentType = $finfo->file($photoPath);  // "image/png"
$contentType = is_string($contentType) && $contentType !== '' ? trim($contentType) : 'application/octet-stream';
$response = $response->withHeader('Content-Type', $contentType);
```

## Photo caching

Attach the HttpCache middleware to the route:

```php
use Slim\HttpCache\Cache;

$group->get('/photo', function ($request, $response, $args) {
    $photo = new Photo('Person', $args['personId']);
    return SlimUtils::renderPhoto($response, $photo);
})->add(new Cache('public', Photo::CACHE_DURATION_SECONDS));
```

`Photo::CACHE_DURATION_SECONDS` is in `src/ChurchCRM/dto/Photo.php`.

## Email composer send

`POST /api/email/send` (`src/api/routes/email.php`, `EmailComposerService`) accepts `personIds[]` / `familyIds[]` plus `subject` / `body`, and answers a `recipients` array of raw addresses with 400. The service resolves each id to its current address, applies the do-not-email property, skips deceased people, inactive families, ids without an address and duplicate addresses, and returns `{sent[], skipped[{name, reason}], failed[{name, error}], counts}`. One `ComposerEmail` is sent per recipient, so every send traces to a record and the church SMTP relay mails only known addresses.

List endpoints the composer reads (`/cart/emails`, `/people/emails`, `/groups/{id}/emails`, `/groups/{id}/sundayschool/emails`) return a `recipients` array (`{personId, familyId, name, email}`) next to `emails`, built with `PersonService::mailingRecipient($person, $email)`.

A one-record send button carries `data-email-composer data-email-person-id="N" data-email-address="..." data-email-name="..."` (or `data-email-family-id`) and the page loads `/skin/v2/email-composer.min.js`. Gate both on `AuthenticationManager::getCurrentUser()->isEmailEnabled() && SystemConfig::isEmailEnabled()`.

Email failures in any API log and continue:

```php
if (!mail($to, $subject, $body)) {
    error_log('Email failed: ' . $to);
}
return SlimUtils::renderJSON($response, ['data' => $result]);
```

## Admin API calls (JavaScript)

Use `window.CRM.AdminAPIRequest()` for jQuery code; it prepends `/admin/api/`, sets JSON content type, integrates with the CRM error handler, and returns a jQuery promise.

```javascript
window.CRM.AdminAPIRequest({ path: 'orphaned-files/delete-all', method: 'POST' })
    .done(() => window.CRM.notify(i18next.t('Success'), { type: 'success' }))
    .fail(() => window.CRM.notify(i18next.t('Error'), { type: 'error' }));
```

Modern modules use `fetch()`:

```javascript
fetch(window.CRM.root + '/admin/api/system/config/settingName', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ value: settingValue }),
})
    .then((response) => response.json())
    .then(() => window.CRM.notify(i18next.t('Settings saved'), { type: 'success' }));
```

## Webpack TypeScript API utilities

Webpack bundles load before `window.CRM` is initialized. Build API URLs with `webpack/api-utils.ts`, which reads `window.CRM.root` at call time:

```typescript
import { buildAPIUrl, buildAdminAPIUrl, fetchAPIJSON } from './api-utils';

const url = buildAPIUrl('person/123/avatar');           // '/api/person/123/avatar'
const adminUrl = buildAdminAPIUrl('system/config/key'); // '/admin/api/system/config/key'
const data = await fetchAPIJSON<AvatarInfo>('person/123/avatar');
```

The module also exports `getRootPath`, `fetchAPI`, `fetchAdminAPI`, `fetchAdminAPIJSON`.

## Cypress API spec (every new endpoint)

Every new endpoint ships with a Cypress API spec in the same PR, run locally before the push. Seed real data so the happy path exercises actual model hydration. Template: `cypress/e2e/api/private/people/people.attendance.spec.js`.

- Happy path with seeded data: seed through existing APIs in `before()`, clean up in `after()`, assert status 200 and response shape and values
- Empty path, e.g. a person with no records
- 401 with no API key or session
- 403 for a role-restricted caller (`cy.makePrivateNoPermAPICall()`, every flag off, when seeded users are too privileged)
- 404 for a nonexistent entity id

Run: `npx cypress run --config-file cypress/configs/docker.config.ts --spec "<path>"`.

## OpenAPI documentation

`zircote/swagger-php` generates OpenAPI 3.0 specs from `@OA\*` DocBlock annotations; the specs power the public API reference. Add or update the annotation for every API endpoint. `.github/workflows/docs.yml` regenerates and validates the spec on API-touching PRs. Required content: path, request/response schemas, parameter descriptions and types, error codes (400, 401, 403, 404), example payloads.

Named functions take the DocBlock immediately above the `function`:

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

Closures take a standalone DocBlock immediately above the `$group->get(...)` call:

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

| Middleware | OpenAPI security | Extra responses |
|---|---|---|
| None (`/public/*`) | omit `security` | none |
| `AuthMiddleware` (global) | `security={{"ApiKeyAuth":{}}}` | 401 |
| Role middleware (Finance, Admin, ...) | `security={{"ApiKeyAuth":{}}}` | 401 + 403 |

Tags are declared in `docs/openapi/openapi-public-info.php` and `docs/openapi/openapi-private-info.php`; add a new tag there first.

### Regenerating the spec

`zircote/swagger-php` is a `require-dev` dependency and `npm run build` installs with `--no-dev`, so install dev dependencies first:

```bash
cd src
composer install
composer run openapi:public   # docs/openapi/generated/public-api.yaml
composer run openapi:private  # docs/openapi/generated/private-api.yaml
```

Each command prints `OpenAPI spec written to: <path> (...)` on stderr. When the whole spec scrolls past on stdout, no file was written; check the arguments. `docs/openapi/generate.php` accepts `--output=FILE`, `--output FILE` or `-o FILE`, `--format`, and `--exclude` anywhere, and a missing info file, scan path or exclude path is a hard error.

The master workflow commits `docs/openapi/generated/*.yaml`; a feature branch does not. The docs site (`docs.churchcrm.io`) reads those files from the tag named in `crm-release.json`. A published CRM release dispatches `crm-released`, and the docs workflow moves the pin after the release exists and merges the pin PR once CI and CodeRabbit pass.

## Nominatim geocoding

`GeoUtils.getLatLong()` calls Nominatim with comma-separated addresses and no country code, or with structured parameters when the components are available (more accurate):

```php
$params = ['q' => '13216 NE 100th St, Kirkland, WA, 98033', 'format' => 'json', 'limit' => 1];

$params = [
    'street' => '13216 NE 100th St',
    'city' => 'Kirkland',
    'state' => 'WA',
    'postalcode' => '98033',
    'format' => 'json',
    'limit' => 1,
];
```

Send `User-Agent: ChurchCRM/7.0 (+https://churchcrm.io)` and log calls and responses for geocoding diagnosis.

## CSV exports

Use `CsvExporter` (`src/ChurchCRM/Utils/CsvExporter.php`) for CSV downloads from Slim routes and return a PSR-7 response. Insert rows incrementally inside the loop so the result is never buffered in memory.

```php
$exporter = new CsvExporter();
$exporter->insertHeaders(['Col1', 'Col2']);
foreach ($source as $item) {
    $exporter->insertRow([$item['a'], $item['b']]);
}

$response = $response
    ->withHeader('Content-Type', 'text/csv; charset=UTF-8')
    ->withHeader('Content-Disposition', 'attachment; filename="export.csv"');
$response->getBody()->write($exporter->getContent());
return $response;
```

Add a feature-flag middleware such as `SundaySchoolEnabledMiddleware` only to routes whose content is exclusively about that feature. A general export that enriches rows with Sunday School data gates only that logic inside the handler.

## Use the model's canonical mutator

When a model exposes a canonical mutator, every code path calls it. For event check-in, `Event::checkInPerson()` and `Event::checkOutPerson()` set consistent timestamps, create the timeline Note (`type='event'`), and fire hooks, including cart-to-event bulk check-in.

```php
$event = EventQuery::create()->findPk($eventId);
if ($event !== null) {
    $event->checkInPerson($personId);
}
```

## Notes REST API

`src/api/routes/people/notes.php`; every route requires `NotesRoleAuthMiddleware`.

```
GET    /person/{personId}/notes   list type=note for person (visibility-filtered)
POST   /person/{personId}/note    create note for person
GET    /family/{familyId}/notes   list type=note for family (visibility-filtered)
POST   /family/{familyId}/note    create note for family
GET    /note/{noteId}             fetch single note (visibility check)
PUT    /note/{noteId}             update text/private (author or admin only)
DELETE /note/{noteId}             delete + write type=delete-note audit entry
```

Permission model:

- `isAdmin()` always yields `isNotesEnabled()` true, so admins pass the middleware. A user without the Notes permission is blocked at the middleware
- Notes=1 (non-admin) sees public notes and own private notes; another author's private note returns 404 so its existence stays hidden
- `canReadPrivateNotes()` is `isAdmin()`; the TimelineService still renders `[Private Note]` placeholders
- DELETE: `isAdmin() OR note.getEnteredBy() === currentUser.getId()`
- PUT: `isAdmin() OR isAuthor`

Delete audit trail: capture the author name before deleting, then write a `type='delete-note'` Note.

```php
$authorPerson = PersonQuery::create()->findPk($note->getEnteredBy());
$authorName   = $authorPerson?->getFullName() ?? gettext('Unknown');
$note->delete();
$audit = new Note();
if ($perId > 0) { $audit->setPerId($perId); }
if ($famId > 0) { $audit->setFamId($famId); }
$audit->setType('delete-note');
$audit->setText(sprintf(gettext('Note by %s deleted'), $authorName));
$audit->setEntered($currentUser->getId());
$audit->save();
```

### Note privacy

`nte_Private` is a boolean flag: store `1` (private) or `0` (public). `Note::isPrivate()` checks `getPrivate() !== 0`. Visibility comes from `Note::isVisible($userId)`, which compares `getEnteredBy()` with the user id. Admins get no API access to other users' private notes.

```php
$private = !empty($input['private']) ? 1 : 0;
```

### utf8mb4 for user text

`nte_Text` holds emoji, so the table is `utf8mb4` (`SQLSTATE[22007]` on `utf8mb3`). Declare `utf8mb4_unicode_ci` on any new table that stores user text.

## DELETE endpoints: block if in use

Every DELETE endpoint returns 409 Conflict when dependent data still references the resource. Callers clear the references first.

```php
$group->delete('/{id:[0-9]+}', function (Request $request, Response $response, array $args): Response {
    try {
        $id = (int) $args['id'];
        $parent = <Parent>Query::create()->findPk($id);
        if ($parent === null) {
            return SlimUtils::renderErrorJSON($response, gettext('<Parent> not found'), [], 404);
        }

        $childCount = <Child>Query::create()->filterBy<ParentFkColumn>($id)->count();
        if ($childCount > 0) {
            return SlimUtils::renderErrorJSON(
                $response,
                sprintf(gettext('Cannot delete <parent>: %d <child-name> still reference it. Remove them first.'), $childCount),
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

Add the 409 to the OpenAPI annotation: `@OA\Response(response=409, description="<Parent> is in use by <child-name>")`.

When a service already blocks the delete, delegate to it so every caller gets the same semantics, and localize the message through `gettext()` instead of passing the exception text to the client:

```php
$group->delete('/{id:[0-9]+}', function (Request $request, Response $response, array $args): Response {
    try {
        // DonationFundService::deleteFund throws InvalidArgumentException (missing, 404)
        // and RuntimeException (pledges reference it, 409), and renumbers fun_Order on success
        (new DonationFundService())->deleteFund((int) $args['id']);
        return SlimUtils::renderSuccessJSON($response);
    } catch (\InvalidArgumentException $e) {
        return SlimUtils::renderErrorJSON($response, gettext('Donation fund not found'), [], 404);
    } catch (\RuntimeException $e) {
        return SlimUtils::renderErrorJSON($response, gettext('Cannot delete donation fund: it is still referenced by one or more pledges.'), [], 409, $e, $request);
    } catch (\Throwable $e) {
        return SlimUtils::renderErrorJSON($response, gettext('Failed to delete donation fund'), [], 500, $e, $request);
    }
});
```

Reference implementations: `src/api/routes/finance/finance-donation-funds.php` (service-delegated), `finance-fundraisers.php`, `src/admin/routes/api/system/property-types.php`, `src/admin/routes/api/system/volunteer-opportunities.php` (inline count), `src/event/routes/types.php` (event type, blocked while events use it).

### When cascade is correct

Cascade only where the children belong to the parent:

- Deposit -> Pledges: `Deposit::preDelete()` deletes the pledges; a deposit is a batch container
- Family -> Notes + non-Payment pledges: `src/api/routes/people/people-family.php` cascades them and blocks non-finance users when `Payment` pledges exist
- Property definition -> RecordProperty assignments: `src/api/routes/people/people-properties.php` deletes the assignments first

Otherwise block.

## Files

- Routes: `src/api/routes/`, `src/admin/routes/api/`
- Utilities: `src/ChurchCRM/Slim/SlimUtils.php`
- Middleware: `src/ChurchCRM/Slim/Middleware/`
- OpenAPI info: `docs/openapi/openapi-public-info.php`, `docs/openapi/openapi-private-info.php`
- Generated specs: `docs/openapi/generated/public-api.yaml`, `docs/openapi/generated/private-api.yaml`

## HTML + JSON content negotiation in middleware

Middleware that serves a browser-facing HTML route and a JSON API route under the same logic (e.g. `PublicCalendarMiddleware` for `/external/calendars/{token}` and `/api/public/calendar/{token}/...`) centralizes error rendering and picks the format by path prefix first, Accept header second, and always returns a body.

```php
private function renderError(ServerRequestInterface $request, ResponseInterface $response, int $status, string $title, string $message, string $icon = 'fa-calendar-slash'): ResponseInterface
{
    if ($this->prefersJson($request)) {
        return SlimUtils::renderJSON($response, ['error' => $title, 'message' => $message], $status);
    }
    $renderer = new PhpRenderer(SystemURLs::getDocumentRoot() . '/external/templates/calendar/');
    return $renderer->render($response->withStatus($status), 'error.php', [
        'title' => $title, 'message' => $message, 'icon' => $icon,
    ]);
}

private function prefersJson(ServerRequestInterface $request): bool
{
    if (str_contains($request->getUri()->getPath(), '/api/')) {
        return true;
    }
    $accept = strtolower($request->getHeaderLine('Accept'));
    return str_contains($accept, 'application/json') && !str_contains($accept, 'text/html');
}
```

The HTML branch renders a template wrapped in `HeaderNotLoggedIn` / `FooterNotLoggedIn` with `ChurchMetaData::getChurchLogoURL()`, so a recipient of a shared link sees the church's identity.

## Nested rows: AttendanceCounts round-trip

Endpoints that accept nested `AttendanceCounts` arrays (`POST /api/events`, `POST /api/events/:id`) treat client-supplied `id`, `name` and `notes` as untrusted: an `id` can target foreign rows, a `name` can tamper with the displayed label, and `notes` can carry HTML. The shared helper `applyEventExtendedFields()` whitelists by the event type's `EventCountName` rows:

```php
$typeId = (int) $event->getType();
$allowedNames = [];
foreach (EventCountNameQuery::create()->filterByTypeId($typeId)->find() as $cn) {
    $allowedNames[(int) $cn->getId()] = (string) $cn->getName();
}

foreach ($input['AttendanceCounts'] as $row) {
    $countId = (int) ($row['id'] ?? 0);
    if ($countId <= 0 || !array_key_exists($countId, $allowedNames)) {
        continue;
    }
    $count = EventCountsQuery::create()->findPk([$eventId, $countId])
        ?? (new EventCounts())->setEvtcntEventid($eventId)->setEvtcntCountid($countId);
    $count->setEvtcntCountname($allowedNames[$countId]);
    $count->setEvtcntCountcount((int) ($row['count'] ?? 0));
    $count->setEvtcntNotes(InputUtils::sanitizeText((string) ($row['notes'] ?? '')));
    $count->save();
}
```

Apply the same pattern to any nested "rows belonging to a parent entity" payload.

## Shared extended-fields helper

Keep API field names canonical (`Title`, `Type`, `Start`, `End`). Extract the extended fields (InActive, LinkedGroupId, AttendanceCounts for events) into one helper that `newXxx()` and `updateXxx()` both call after saving the main entity, so the two write paths stay identical:

```php
function newEvent(...) {
    // create, set fields, save
    applyEventExtendedFields($event, $input);
    return SlimUtils::renderSuccessJSON($response);
}

function updateEvent(...) {
    $Event->fromArray($input);
    // setCalendars, save
    applyEventExtendedFields($Event, $input);
    return SlimUtils::renderSuccessJSON($response);
}
```

`GET /{id}` returns the extended fields too, with type defaults when the entity has no rows yet (event type `EventCountName` categories with `count: 0`).

## CSV import

`League\Csv\Reader::getHeader()` throws `League\Csv\SyntaxError` on duplicate column names. Catch it in the upload endpoint and return a 400:

```php
use League\Csv\Reader;
use League\Csv\SyntaxError;

try {
    $csv = Reader::createFromPath($tmpPath, 'r');
    $csv->setHeaderOffset(0);
    $headers = $csv->getHeader();
} catch (SyntaxError $e) {
    return SlimUtils::renderErrorJSON($response, gettext('Your CSV has duplicate column names. Each column header must be unique.'), [], 400);
}
```

### CSV template column uniqueness

A template that combines custom fields and properties from several buckets (PersonCustom, FamilyCustom, PersonProperty, FamilyProperty) names columns `"{name} ({category})"` and appends `(2)`, `(3)` for residual duplicates within a category:

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
    $field['header'] = $candidate;
}
```

Store headers in English only, without `gettext()`, so a CSV downloaded in one locale auto-maps when uploaded under another.
