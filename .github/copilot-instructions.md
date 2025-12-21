# ChurchCRM — AI Coding Agent (concise)

Purpose: Keep guidance compact. Follow these core rules when editing the repo.

Stack (short)
- PHP 8.2+
- Propel ORM (use Query classes, never raw SQL)
- Slim 4 (API routes)
- Bootstrap 4.6.2 (AdminLTE v2 pattern for legacy pages)
- React + TypeScript (frontend)
- Webpack, Cypress for tests

Key conventions (must follow)
- Service layer first: add or update services in `src/ChurchCRM/Service/` for business logic.
- Use Propel Query classes for DB access (no RunQuery or inline SQL).
- Use `use` imports at the top of PHP files; avoid inline fully-qualified class names.
- PHP templates: render initial UI state server-side (avoid JS-only initialization flashes).
- Boolean config: use `SystemConfig::getBooleanValue('key')` for truthy/falsey checks.
- Asset paths: use `SystemURLs::getRootPath()` for css/img/src references.
- For notifications, use `window.CRM.notify()` (i18n via i18next.t) — do not use alert().

Terminology & i18n conventions (project-standard)
- Use a single canonical UI term where possible to reduce translation surface: prefer "Family Listing" for family list menus and headers.
- Use `People` (not `Persons`) for all UI/display gettext strings. Do NOT rename API routes (`/api/persons`) or internal keys/array fields (e.g., `cartPayload['Persons']`) without explicit coordination — change only user-facing `gettext()`/PO entries and templates.
- For family lifecycle/status use **Active / Inactive** (avoid "Deactivated"). Use action labels like `Set Active` / `Set Inactive` and banners like `This Family is Inactive`.
- When recording status-change notes prefer phrasing like `Marked the Family as Inactive` / `Marked the Family as Active`.
- Add new canonical UI terms (for example `Apply`) to `locale/messages.po` before wiring them into templates; leave translations empty for translators to fill.

API error handling
- For API route errors, return standardized JSON errors with `SlimUtils::renderErrorJSON(...)` instead of throwing `Http*Exception` directly from route handlers. This ensures consistent logging and sanitized client messages.

Locale rebuild reminder
- After changing msgids or UI gettext strings, run `npm run locale:build` and `npm run build` to regenerate frontend translation assets and ensure all i18n bundles include the new keys.

Routing & middleware
- Put API routes in `src/api/routes/` and legacy pages in `src/*.php`.
- **Admin System Pages** (consolidated at `/admin/system/`):
  - Routes in `src/admin/routes/system.php`
  - Views in `src/admin/views/` with PhpRenderer
  - Examples: `/admin/system/debug`, `/admin/system/menus`, `/admin/system/backup`
  - Add menu entries in `src/ChurchCRM/Config/Menu/Menu.php`
  - Use AdminRoleAuthMiddleware for security
- **Admin APIs**: Place in `src/admin/routes/api/` (NOT in `src/api/routes/system/`)
  - Example: `orphaned-files.php` contains `/admin/api/orphaned-files/delete-all` endpoint
  - Routes are prefixed with `/admin/api/` when accessed from frontend
  - Use kebab-case for endpoint names (e.g., `/delete-all`)
  - AdminRoleAuthMiddleware is applied at the router level
- **Finance Module** (consolidated at `/finance/`):
  - Entry point: `src/finance/index.php` with Slim 4 app
  - Routes in `src/finance/routes/` (dashboard.php, reports.php)
  - Views in `src/finance/views/` with PhpRenderer
  - Examples: `/finance/` (dashboard), `/finance/reports`
  - Use FinanceRoleAuthMiddleware for security (allows admin OR finance permission)
  - Menu entry in `src/ChurchCRM/Config/Menu/Menu.php` under "Finance"
- **Deprecated locations** (DO NOT USE):
  - `src/v2/routes/admin/` - REMOVED (admin routes consolidated to `/admin/system/`)
  - `src/api/routes/system/` - Legacy admin APIs (no new files here)
- Middleware order (CRITICAL - Slim 4 uses LIFO):
    1. addBodyParsingMiddleware()
    2. addRoutingMiddleware()
    3. add(CorsMiddleware)          // Last added, runs FIRST
    4. add(AuthMiddleware)          // Runs SECOND
    5. add(VersionMiddleware)       // First added, runs LAST

## RedirectUtils (Security & Navigation)

**Use RedirectUtils for all redirects** - Located in `src/ChurchCRM/Utils/RedirectUtils.php`

Three core methods:

1. **`redirect($sRelativeURL)`** - Safe relative redirects (automatically handles root path)
   - Use for: Normal page navigation, error pages, "not found" pages
   - Example: `RedirectUtils::redirect('v2/dashboard')` or `RedirectUtils::redirect('v2/person/not-found?id=' . $iPersonID)`
   - Automatically prepends `SystemURLs::getRootPath()` and handles URL normalization

2. **`securityRedirect($missingRole)`** - Permission-denied redirects (logs warning)
   - Use for: Access denied due to missing permissions/roles
   - Example: `RedirectUtils::securityRedirect('PersonView')` when user lacks required permission
   - Logs warning via `LoggerUtils` and redirects to `v2/access-denied?role=[missingRole]`
   - Pass a descriptive string indicating what permission was missing

3. **`absoluteRedirect($sTargetURL)`** - Absolute URL redirects (no path manipulation)
   - Use for: External URLs or already-complete internal URLs
   - Example: `RedirectUtils::absoluteRedirect('https://example.com')` or `RedirectUtils::absoluteRedirect($completePath)`
   - Does NOT prepend root path, used as-is

### Authorization Redirect Pattern

For permission checks, use this pattern:

```php
// Check role-based permission
if (!AuthenticationManager::getCurrentUser()->isEditRecordsEnabled()) {
    RedirectUtils::securityRedirect('EditRecords');
}

// Check object-level permission (use User::canEditPerson for person records)
$currentUser = AuthenticationManager::getCurrentUser();
if (!$currentUser->canEditPerson($personId, $personFamilyId)) {
    RedirectUtils::securityRedirect('PropertyAssign');
}

// Check if resource exists, redirect to not-found page
$person = PersonQuery::create()->findOneById($personId);
if ($person === null) {
    RedirectUtils::redirect('v2/person/not-found?id=' . $personId);
}
```

**DO NOT use:**
- `header('Location: ...')` directly (bypasses root path handling)
- `header('Location: ' . SystemURLs::getRootPath() . ...)` (RedirectUtils does this automatically)
- Unhandled exception throws for access denied (use security redirects)

API & naming
- Prefer kebab-case endpoints for upgrade/system routes (e.g. `/download-latest-release`).
- GET for reads, POST for actions that change state.

JS/CSS/Frontend
- Bootstrap 4.6.2 utilities only. Follow v2 templates (no Bootstrap 5 utilities).
- Frontend state that matters on first paint should be rendered by PHP (examples: upgrade wizard toggle).

Testing & quality gates
- Add Cypress UI tests under `cypress/e2e/ui/` for critical user flows.
- Run relevant tests before committing changes that affect behavior.
- Ensure build/lint/tests pass locally when practical.

Logging
- Use `LoggerUtils::getAppLogger()` and include contextual data in logs.

Commits & PRs
- Do not run git commit on user's behalf. Ask before creating commits.
- Tests should pass before merging. Keep commits small and focused.

When editing files
- Use the repository tools (apply_patch) to make safe, minimal diffs.
- Prefer small, targeted changes; avoid broad reformatting unless requested.

If unsure
- Read nearby files to match style. If blocked, ask a specific question.

---

## Database Rules
- ALWAYS use Propel ORM Query classes
- NEVER use raw SQL or RunQuery()
- Cast dynamic IDs to (int)
- Check `=== null` not `empty()` for objects
- Access properties as objects: `$obj->prop`, never `$obj['prop']`

## Propel ORM Method Naming (CRITICAL)

**NEVER guess ORM method names.** Propel uses strict column-to-method mapping. **Always check the Query class documentation comments** to verify exact method names before writing code.

### Method Naming Pattern

Propel converts database column names to PHP method names using **phpName** (derived from table/column structure):

| Database Column | Query Method | Accessor Method | Mutator Method |
|---|---|---|---|
| `custom_order` (renamed to `Order` in phpName) | `orderByOrder()`, `filterByOrder()` | `getOrder()` | `setOrder()` |
| `custom_field` (renamed to `Id` in phpName) | `findOneById()`, `filterById()` | `getId()` | `setId()` |
| `custom_name` (renamed to `Name` in phpName) | `orderByName()`, `filterByName()` | `getName()` | `setName()` |
| `lst_ID` (renamed to `Id` in phpName) | `findOneById()`, `orderById()` | `getId()` | `setId()` |
| `lst_OptionID` (renamed to `OptionId`) | `filterByOptionId()` | `getOptionId()` | `setOptionId()` |
| `type_ID` (renamed to `TypeId` in phpName) | `filterByTypeId()` | `getTypeId()` | `setTypeId()` |

### How to Find Correct Method Names

1. **Check the Base Query class** at `src/ChurchCRM/model/ChurchCRM/Base/*Query.php`
2. **Look for `@method` PHPDoc comments** at the top of the class - they list ALL available query methods
3. **Common patterns to look for:**
   - `findOneById()` - Find by primary key
   - `findOneBy[ColumnName]()` - Find by any column
   - `filterBy[ColumnName]()` - Add WHERE condition
   - `orderBy[ColumnName]()` - Add ORDER BY
   - `groupBy[ColumnName]()` - Add GROUP BY

### Example: PersonCustomMasterQuery

```php
// Check Base/PersonCustomMasterQuery.php for @method comments:
// @method     ChildPersonCustomMasterQuery orderByOrder($order = Criteria::ASC)
// @method     ChildPersonCustomMasterQuery orderByName($order = Criteria::ASC)
// @method     ChildPersonCustomMaster|null findOneById(string $custom_Field)
// @method     ChildPersonCustomMaster|null findOneByName(string $custom_Name)

// CORRECT - These match the documented methods
$field = PersonCustomMasterQuery::create()
    ->findOneById($fieldName);
$fields = PersonCustomMasterQuery::create()
    ->orderByOrder()
    ->find();

// WRONG - These methods don't exist (will throw UnknownColumnException)
$field = PersonCustomMasterQuery::create()
    ->filterByCustomfield($fieldName);  // ❌ Should be findOneById()
$fields = PersonCustomMasterQuery::create()
    ->filterByCustomorder(1)  // ❌ Should be filterByOrder()
    ->find();
```

### Common Mistakes to Avoid

- ❌ `filterByCustomField()` → Use `findOneById()` for primary key lookups
- ❌ `filterByLstId()` → Use `findOneById()` (Propel renames `lst_ID` to `Id`)
- ❌ `filterByTypeId()` used with `findOne()` instead of `findOneByTypeId()` 
- ❌ `orderByCustomOrder()` → Use `orderByOrder()` (Propel uses phpName, not database column)
- ❌ `setCustomorder()` → Use `setOrder()` (consistent with getter/setter naming)

### When Migrating from Raw SQL

**Always consult the Query class before converting SQL to ORM:**

```php
// RAW SQL (find what to convert)
$sSQL = "SELECT * FROM person_custom_master WHERE custom_Field = '" . $fieldName . "'";
$record = RunQuery($sSQL);

// CORRECT ORM (check Base Query class for method names)
$record = PersonCustomMasterQuery::create()
    ->findOneById($fieldName);  // ← primary key lookup method

// Find column mappings in Base class:
// @method ChildPersonCustomMaster|null findOneById(string $custom_Field)
// This tells us custom_Field → Id in phpName
```

---

## User Authorization Methods

The `User` class provides permission checking methods located in `src/ChurchCRM/model/ChurchCRM/User.php`

**Role-based methods** (check generic permission type):
- `isAdmin()` - Super admin access
- `isEditRecordsEnabled()` - Can edit any person/family record
- `isEditSelfEnabled()` - Can edit own record and family members
- `isEditRecords()` / `isDeleteRecords()` / `isAddRecords()` - Specific record permissions
- `isManageGroupsEnabled()` - Can manage groups
- `isFinanceEnabled()` - Can access finance module
- `isNotesEnabled()` - Can add/edit notes

**Object-level method** (check permission for specific person):
- `canEditPerson(int $personId, int $personFamilyId = 0): bool` - Check if user can edit a specific person
  - Returns `true` if user has EditRecords permission, OR
  - Returns `true` if user has EditSelf permission AND it's their own record or a family member
  - Use this method to prevent privilege escalation (broken access control)
  - Example:
    ```php
    $currentUser = AuthenticationManager::getCurrentUser();
    if (!$currentUser->canEditPerson($iPersonID, $person->getFamId())) {
        RedirectUtils::securityRedirect('PropertyAssign');
    }
    ```

**Pattern for authorization checks**:
1. First check: General permission (`isEditRecordsEnabled()`, `isManageGroupsEnabled()`, etc.)
2. Second check: Object-level permission (use `canEditPerson()` for person records)
3. Third: Redirect with appropriate method (`securityRedirect()` for auth failures, `redirect()` for "not found")

---

## Service Classes (Business Logic)

Located in `src/ChurchCRM/Service/` - handles domain logic separate from HTTP concerns.

Key Services:
- `PersonService` - Person/family operations
- `GroupService` - Group management
- `FinancialService` - Payments, pledges, funds
- `DepositService` - Deposit slip handling
- `SystemService` - System-wide operations
- `UserService` - User management with optimized database operations

Example Usage:
```php
$service = $container->get('FinancialService');
$result = $service->addPayment($fam_id, $method, $amount, $date, $funds);
return $response->withJson(['data' => $result]);
```

### Service Layer Performance Best Practices

When creating services, optimize database operations:
- **Selective field loading**: Use `->select(['field1', 'field2'])` to fetch only required columns
- **Single query philosophy**: Group related data retrieval in one query, then process in PHP memory
- **Avoid N+1 queries**: Pre-fetch related data instead of looping with individual queries
- **Example**: UserService `getUserStats()` fetches all users' `failedLogins` and `twoFactorAuthSecret` in one query, then processes statistics in memory

---

## Admin MVC Module Migration Patterns

When migrating legacy pages to the Admin MVC structure:

### File Organization
- **Views**: `src/admin/views/[feature].php` - Use PhpRenderer for clean separation
- **Routes**: `src/admin/routes/[feature].php` - Define route endpoints
- **APIs**: `src/admin/routes/api/[feature-api].php` - Admin API endpoints
- **Services**: `src/ChurchCRM/Service/[Feature]Service.php` - Business logic (shared with APIs)

### Key Migration Steps
1. **Extract business logic** from the legacy PHP file into a Service class
2. **Create views** in `src/admin/views/` to render the UI with initial state server-side
3. **Create routes** in `src/admin/routes/` that call the Service and pass data to views
4. **Create APIs** in `src/admin/routes/api/` if UI needs dynamic updates (optional)
5. **Update menu** entries in `src/ChurchCRM/Config/Menu/Menu.php` to point to new route

### SystemConfig for UI Settings Panels

For admin pages that display system settings:
- **Call `SystemConfig::getSettingsConfig($settingKeys)`** to get structured configuration for a settings panel
- Provide an array of setting keys you want in the panel
- Service method example:
  ```php
  public function getUserSettingsConfig(): array {
      $userSettings = [
          'iSessionTimeout',
          'iMaxFailedLogins',
          'bEnableLostPassword'
      ];
      return SystemConfig::getSettingsConfig($userSettings);
  }
  ```
- This returns array with `category`, `name`, `value`, `type`, `options` for each setting
- Frontend can render collapsed setting panels using this structured data
- Avoid hardcoding settings or creating separate SystemConfig lookups - use the service method

### Example: User Management Module
- Legacy: `src/UserList.php` (mixed concerns, hardcoded settings)
- Modern:
  - `src/ChurchCRM/Service/UserService.php` - Statistics + settings config
  - `src/admin/views/users.php` - Dashboard with stats cards and user table
  - `src/admin/routes/api/user-admin.php` - User operations (reset password, delete, 2FA)
  - Dashboard statistics use efficient single-query approach
  - Settings panel rendered from dynamic `SystemConfig::getSettingsConfig()` output

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
- **Use imports, never inline fully-qualified class names**: Add `use` statements at top of file
- Explicit global namespace: `\MakeFYString($id)` in namespaced code
- Version checks: `version_compare(phpversion(), '8.2.0', '<')`
- Public constants for shared values: `public const PHOTO_WIDTH = 200;`

### Import Statement Rules

ALWAYS use `use` statements at the top of files instead of inline fully-qualified class names:

```php
// CORRECT
<?php
namespace ChurchCRM\Slim;

use ChurchCRM\dto\SystemURLs;
use Slim\Exception\HttpNotFoundException;

class MyClass {
    public function test() {
        $path = SystemURLs::getRootPath();
        throw new HttpNotFoundException($request);
    }
}

// WRONG - Inline fully-qualified names
<?php
namespace ChurchCRM\Slim;

class MyClass {
    public function test() {
        $path = \ChurchCRM\dto\SystemURLs::getRootPath();
        throw new \Slim\Exception\HttpNotFoundException($request);
    }
}
```

**File Structure Order:**
1. `<?php` tag and namespace declaration
2. All `use` statements (alphabetically organized)
3. Class declaration and code

**Exception:** Only use `\` prefix for global functions in namespaced code (e.g., `\MakeFYString()`)

---

## HTML Sanitization & XSS Protection

**Use `InputUtils` for all HTML/text handling** - Located in `src/ChurchCRM/Utils/InputUtils.php`

Four core methods for security:

1. **`sanitizeText($input)`** - Plain text, removes ALL HTML tags
   - Use for: Names, descriptions, social media handles
   - Example: `$person->setFirstName(InputUtils::sanitizeText($_POST['firstName']))`

2. **`sanitizeHTML($input)`** - Rich text with XSS protection (HTML Purifier)
   - Use for: User-provided HTML content (event descriptions, Quill editor)
   - Allows safe tags: `<a><b><i><u><h1-h6><pre><img><table><p><blockquote><div><code>` etc.
   - Blocks dangerous: `<script><iframe><embed><form><style><meta>`
   - Example: `$event->setDesc(InputUtils::sanitizeHTML($sEventDesc))`

3. **`escapeHTML($input)`** - Output escaping for HTML body content
   - Automatically handles `stripslashes()` for magic quotes
   - Use for: Displaying database/user values in HTML
   - Example: `<?= InputUtils::escapeHTML($person->getFirstName()) ?>`

4. **`escapeAttribute($input)`** - Output escaping for HTML attributes
   - Same security as `escapeHTML()` (uses `ENT_QUOTES`)
   - Use for: Values in HTML attributes or form fields
   - Example: `<input value="<?= InputUtils::escapeAttribute($address) ?>">`

5. **`sanitizeAndEscapeText($input)`** - Combined plain text sanitization + output escape
   - Use for: Untrusted user input that must be plain text and escaped
   - Example: `$data[$key] = InputUtils::sanitizeAndEscapeText($userSubmittedValue)`

**CRITICAL Security Rules:**
- ❌ NEVER use `htmlspecialchars()` or `htmlentities()` directly
- ❌ NEVER use `ENT_NOQUOTES` flag (doesn't escape quotes in attributes)
- ❌ NEVER use `stripslashes()` directly (let InputUtils handle it)
- ✅ ALWAYS use InputUtils methods for all HTML/text handling
- ✅ ALWAYS use `escapeAttribute()` for form input values
- ✅ ALWAYS use `sanitizeHTML()` for rich text editors (Quill)

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

### File Inclusion (require vs include)
```php
// CORRECT - Use require for critical layout files
require SystemURLs::getDocumentRoot() . '/Include/Header.php';
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';

// WRONG - include allows missing critical files
include SystemURLs::getDocumentRoot() . '/Include/Header.php';  // Silent failure
```

**Guidelines:**
- **Use `require`** for critical files: Header.php, Footer.php, core utilities
- **Use `include`** for optional content: plugins, supplementary files that gracefully degrade
- **Why**: `require` fails loudly (fatal error), `include` fails silently (warning)
- **Admin views** (`src/admin/views/*.php`): ALL must use `require` for Header/Footer

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

### API Error Handling (Critical)

**ALWAYS use `SlimUtils::renderErrorJSON()` for API errors** — Located in `src/ChurchCRM/Slim/SlimUtils.php`

Never throw exceptions in route handlers. Wrap operations in try/catch and return sanitized error responses.

**Pattern:**
```php
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

**`renderErrorJSON` behavior:**
- Server-side logs: exception class, message, file, line, trace, request method/path/IP/user-agent
- Client receives: sanitized message only (no traces, file paths, or credentials)
- Sanitizes messages automatically (detects and masks password/token/host patterns)
- Status passed as `int $status` parameter (NOT via `response->withStatus(...)`)

**Signature:**
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

**Examples:**

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

**DO NOT:**
- ❌ Throw exceptions in API routes (caught by error handler, exposes details to clients)
- ❌ Use `response->withStatus(500)` with renderErrorJSON (pass status as parameter)
- ❌ Return raw exception messages (use gettext() for localization and sanitization)
- ❌ Log exceptions separately in routes (renderErrorJSON handles all logging)

### API Response Standardization

**Maintain consistent error response format across all APIs** to prevent client-side errors like "undefined" values.

**Key Pattern:**
- **Success responses** should use consistent structure: `{'success': true, 'data': ...}` or `{'success': true, 'message': ...}`
- **Error responses** should ALWAYS use `message` field (not `error`, `msg`, or other variations)
- **Security**: Return generic error messages to users, not specific validation details

**Example - Standardized Error Response:**
```php
// WRONG - Uses 'error' field which client may not expect
return SlimUtils::renderErrorJSON($response, 'Invalid image format', [], 400, $e, $request);
// Client receives: {success: false, error: "Invalid image format"}

// CORRECT - Uses 'message' field consistently
return SlimUtils::renderErrorJSON($response, gettext('Upload failed'), [], 400, $e, $request);
// Client receives: {success: false, message: "Upload failed"}
```

**Client-side Error Handler Resilience:**
When handling API errors in JavaScript, check multiple possible error field names for backward compatibility:
```javascript
// Handle message, error, or msg field names
var errorText = error.message || error.error || error.msg || i18next.t("Unknown error");
```

**Real Example: Photo Upload Validation**
```php
// API returns generic error for security (don't expose validation details)
catch (\Throwable $e) {
    return SlimUtils::renderErrorJSON($response, gettext('Failed to upload photo'), [], 400, $e, $request);
}
```

```javascript
// Client gets: {success: false, message: "Failed to upload photo"}
// Server logs full exception details for debugging
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

**Bootstrap Version: 4.6.2** - NEVER use Bootstrap 5 classes!

Always use Bootstrap 4.6.2 CSS classes, never deprecated HTML attributes or Bootstrap 5 classes:

```php
// CORRECT - Bootstrap 4.6.2 classes
<div class="text-center align-top">Content</div>
<button class="btn btn-primary btn-block">Full Width Button</button>
<div class="btn-group btn-group-sm d-flex" role="group">
    <a class="btn btn-outline-primary flex-fill">Button 1</a>
    <a class="btn btn-outline-primary flex-fill">Button 2</a>
</div>

// WRONG - Bootstrap 5 classes (DO NOT USE!)
<button class="btn btn-primary w-100">Button</button>  // Use btn-block instead
<div class="d-flex flex-wrap gap-2">Content</div>      // gap- is Bootstrap 5 only
<div class="d-grid gap-3">Content</div>               // d-grid is Bootstrap 5 only

// WRONG - Deprecated HTML attributes  
<div align="center" valign="top">Content</div>
<button style="margin-top: 12px;">Click</button>
```

**Bootstrap 5 Classes to AVOID:**
- `w-100` on buttons (use `btn-block`)
- `gap-*` utilities (use margins/padding instead)
- `d-grid` (use `d-flex` or Bootstrap 4 grid)
- `text-decoration-*` (use existing classes)
- `fw-*` and `fs-*` font utilities
- `rounded-*` beyond Bootstrap 4 values
- `justify-content-*` with `gap-*` (gap is Bootstrap 5 only)
- `flex-wrap` with `gap-*` (use proper spacing classes instead)

---

## Internationalization (i18n)

CRITICAL: Always wrap user-facing text for translation.

JavaScript:
```javascript
window.CRM.notify(i18next.t('Operation completed'), {
    type: 'success',
    delay: 3000
});
```

PHP:
```php
echo gettext('Welcome to ChurchCRM');
```

NEVER use alert() - only use window.CRM.notify() with Notyf:
```javascript
// WRONG
alert('Operation completed');

// CORRECT
window.CRM.notify(i18next.t('Operation completed'), {
    type: 'success',
    delay: 3000
});
```

### i18n Term Consolidation Patterns (Reduce Localization Burden)

To reduce translator burden across 45+ languages, **consolidate compound terms into reusable components**.

**Delete Confirmation Pattern:**
- ❌ WRONG: `gettext('Family Delete Confirmation')`, `gettext('Note Delete Confirmation')`, etc. (7+ variants = 7 translations per language)
- ✅ CORRECT: `gettext('Delete Confirmation') . ': ' . gettext('Family')`
- **Reduces**: 7 variants → 1 term + type names (reduces translator workload)
- **Usage**: In deletion confirmation pages (NoteDelete.php, PropertyDelete.php, etc.)
- **Example**:
  ```php
  $sPageTitle = gettext('Delete Confirmation') . ': ' . gettext('Note');
  ```

**Add New Pattern:**
- ❌ WRONG: `gettext('Add New Field')`, `gettext('Add New Fund')`, `gettext('Add New User')`, etc. (16+ variants per language)
- ✅ CORRECT: `gettext('Add New') . ' ' . gettext('Field')`
- **Reduces**: 16+ variants → 1 "Add New" term + reuse type names (significant translator workload reduction)
- **Usage**: Button labels, page titles, form headers
- **Examples**:
  ```php
  // Button value
  <input type="submit" value="<?= gettext('Add New') . ' ' . gettext('Fund') ?>" />
  
  // Page title
  <h3><?= gettext('Add New') . ' ' . gettext('Group') ?></h3>
  
  // Card header
  <?= gettext('Add New') . ' ' . gettext('Field') ?>
  ```

**Exception**: Menu items with consistent patterns (e.g., "Add New Person", "Add New Family") may stay unified for clarity.

**General Consolidation Principles:**
1. **Identify compound terms**: Look for "[Action] [Type]" or "[Type] [Action]" patterns
2. **Split into components**: Translate action/type separately when repeated 2+ times
3. **Reuse type names**: If "Person", "Family", "Group" are standalone translation terms, reuse them
4. **Test rebuilds**: Always run `npm run locale:build` to verify consolidation
5. **Reduce total terms**: Every term consolidated = 45 fewer translations needed (45 languages)

**When NOT to Consolidate:**
- Unique terms with only 1 usage → Keep as-is
- Idiomatic phrases that don't split naturally → Keep as-is
- Menu items with consistent naming → May keep unified for UX consistency

**Before Adding New Terms:**
- Check `locale/messages.po` for similar existing terms
- Look for opportunities to reuse existing terms instead of creating new ones
- Example: Instead of `"Add New Sponsor"`, use `gettext('Add New') . ' ' . gettext('Sponsor')`

**Locale Rebuild Workflow:**
After consolidating terms:
```bash
npm run locale:build   # Regenerate messages.po and .json files
npm run build          # Rebuild frontend bundles with new terms
```

---

## Admin API Calls (JavaScript)

**Recommended Approaches for `/admin/api/` calls:**

1. **`window.CRM.AdminAPIRequest()`** - Preferred for jQuery-based code
2. **Native `fetch()`** - Acceptable for modern JavaScript code

Both approaches are valid. Choose based on the existing code patterns in the file you're editing.

**Option 1: AdminAPIRequest (jQuery-based)**
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

**Option 2: Native fetch (Modern JavaScript)**
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

**AdminAPIRequest Details:**
- Automatically prepends `/admin/api/` to the path
- Sets proper `Content-Type: application/json` and `dataType: 'json'`
- Integrates with CRM error handler for consistent error display
- Returns jQuery promise (supports `.done()`, `.fail()`, `.always()`)
- Path format: `'database/reset'` becomes `/admin/api/database/reset`

**For public/private API calls, use:**
- `window.CRM.APIRequest()` for `/api/` endpoints
- Native `fetch()` is also acceptable for modern code

## Webpack TypeScript API Utilities

**For all new webpack TypeScript bundles, use the `webpack/api-utils.ts` helper module.** This ensures consistent, safe API URL construction across webpack modules.

**Critical Issue:** Webpack bundles load **before** `window.CRM` is initialized. Therefore:
- ❌ **DON'T** assign `window.CRM.root` in constructors (too early, undefined)
- ✅ **DO** use `api-utils.ts` functions which evaluate at runtime

**Available Functions:**

```typescript
import { buildAPIUrl, buildAdminAPIUrl, fetchAPIJSON } from './api-utils';

// URL construction (safe - evaluated at runtime)
const url = buildAPIUrl('person/123/avatar');           // → '/api/person/123/avatar'
const adminUrl = buildAdminAPIUrl('system/config/key'); // → '/admin/api/system/config/key'

// Fetch with automatic error handling
const data = await fetchAPIJSON<AvatarInfo>('person/123/avatar');

// With fetch options
const response = await fetchAPI('person/123/photo', {
    method: 'DELETE'
});
```

**API Functions:**
- `getRootPath()` - Get `window.CRM.root` dynamically
- `buildAPIUrl(path)` - Build `/api/` endpoint URL
- `buildAdminAPIUrl(path)` - Build `/admin/api/` endpoint URL
- `fetchAPI(path, options)` - Fetch with error logging
- `fetchAPIJSON<T>(path, options)` - Fetch and parse JSON (recommended)
- `fetchAdminAPI(path, options)` - Admin API fetch variant
- `fetchAdminAPIJSON<T>(path, options)` - Admin API JSON variant

**Examples in codebase:**
- `webpack/avatar-loader.ts` - Uses `buildAPIUrl()` for URL construction
- `webpack/photo-utils.ts` - Uses `buildAPIUrl()` as fallback
- See `webpack/API_UTILITIES.md` for full documentation

**Migration from old patterns:**
- Old: `${(window as any).CRM.root}/api/...` → New: `buildAPIUrl('...')`
- Old: `window.CRM.APIRequest()` → New: `fetchAPIJSON()` (modern, async/await)

---

## Testing

### Cypress Configuration & Logging
- Two config files: `cypress.config.ts` (dev) and `docker/cypress.config.ts` (CI)
- Enhanced logging: `cypress-terminal-report` plugin captures browser console output
- CI artifacts: Logs uploaded to `cypress/logs/`, accessible via GitHub Actions artifacts
- Log retention: 30 days for debugging failed CI runs

### API Tests
Location: `cypress/e2e/api/private/[feature]/[endpoint].spec.js`

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

### Debugging 500 Errors (CRITICAL)

**NEVER ignore or skip a test that returns HTTP 500.** Always investigate the root cause:

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

### UI Tests

Location: `cypress/e2e/ui/[feature]/`

#### Session-Based Login Pattern (REQUIRED)
All UI tests MUST use modern session-based login. This pattern uses `cy.session()` for efficient login caching across tests and configuration-driven credentials.

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

#### Commands & Configuration
**Available Commands:**
- `cy.setupAdminSession()` - Authenticates as admin (reads `admin.username`, `admin.password` from config)
- `cy.setupStandardSession()` - Authenticates as standard user (reads `standard.username`, `standard.password` from config)
- `cy.setupNoFinanceSession()` - Authenticates as user without finance permission (reads `nofinance.username`, `nofinance.password` from config)
- `cy.typeInQuill()` - Rich text editor input

**Credentials Configuration:**
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
- DO NOT hardcode credentials in test files
- DO NOT add commented-out tests or TODO comments - remove them
- Configuration-driven approach prevents secrets leaking into git

#### Test Structure Requirements
- Maintain element IDs for test selectors (use `cy.get('#element-id')`)
- Avoid text-based selectors (fragile across language changes)
- Test complete user workflows end-to-end
- Clear test descriptions (avoid generic names)
- Clean test files (no commented code blocks)

#### Migration Guide
See `PR_SUMMARY.md` for comprehensive migration details from old to new pattern, including all 21 files refactored and lessons learned.

---

## Development Workflows

### Quick Start (GitHub Codespaces/Dev Containers)
- **GitHub Codespaces**: Click "Code" → "Codespaces" → "Create codespace" - fully automated setup
- **VS Code Dev Containers**: Install Dev Containers extension, open repo, click "Reopen in Container"
- **Manual setup**: Run `./scripts/setup-dev-environment.sh` for automated local setup

### Setup & Build
```bash
npm ci                    # Install exact dependencies  
npm run deploy            # Build everything (PHP + frontend)
npm run docker:dev:start  # Start Docker containers
```

### Development Cycle
```bash
npm run build:frontend       # Rebuild JS/CSS (watches via Webpack)
npm run build:php            # Update Composer dependencies
npm run docker:dev:logs      # View container logs
npm run docker:dev:login:web # Shell into web container
```

### Docker Management
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

### Testing (Local)
```bash
npm run test              # Run all tests (headless)
npm run test:ui           # Interactive browser testing

# BEFORE every test run: clear old logs
rm -f src/logs/$(date +%Y-%m-%d)-*.log

# AFTER failures: review logs
cat src/logs/$(date +%Y-%m-%d)-php.log      # PHP errors, ORM errors
cat src/logs/$(date +%Y-%m-%d)-app.log      # App events
```

**CRITICAL Testing Workflow for Agents:**
1. **BEFORE running any test**: Clear logs with `rm -f src/logs/$(date +%Y-%m-%d)-*.log`
2. **Run the test(s)**
3. **AFTER test completion (pass OR fail)**: Review logs to ensure no hidden errors
   - Check PHP log: `cat src/logs/$(date +%Y-%m-%d)-php.log`
   - Check App log: `cat src/logs/$(date +%Y-%m-%d)-app.log`
4. **Even if tests pass**: Verify no 500 errors or exceptions were logged silently

### CI/CD Testing (GitHub Actions)
- Docker profiles: `dev`, `test`, `ci` in `docker-compose.yaml`
- CI uses `npm run docker:ci:start` with optimized containers
- Artifacts uploaded: `cypress-artifacts-{run_id}` contains logs, screenshots, videos
- Access via Actions → Workflow run → Artifacts section
- Debugging: Download `cypress-reports-{branch}` for detailed failure analysis

---

## Commit & PR Standards

**Commit Message Format:**
- Imperative mood, < 72 chars for subject line
- Examples: "Fix validation in Checkin form", "Replace deprecated HTML attributes with Bootstrap CSS", "Add missing element ID for test selector"
- Wrong: "Fixed the bug in src/EventEditor.php" (not imperative, includes file paths)
- Include issue number when applicable: "Fix issue #7698: Replace Bootstrap 5 classes with BS4"

**PR Organization:**
- Create feature branches: `fix/issue-NUMBER-description` or `feature/description`
- One issue per branch - do not mix fixes for different issues
- Keep commits small and focused
- Each PR addresses one specific bug or feature
- Related but separate concerns get separate branches
- Test each branch independently before creating PR

---

## Pre-commit Checklist

Before committing code changes, verify:

- [ ] PHP syntax validation passed (npm run build:php)
- [ ] Propel ORM used for all database operations (no raw SQL)
- [ ] Asset paths use SystemURLs::getRootPath()
- [ ] Service classes used for business logic
- [ ] Type casting applied to dynamic values (`(int)`, `(string)`, etc.)
- [ ] Critical files use `require` not `include` (Header.php, Footer.php)
- [ ] Deprecated HTML attributes replaced with CSS
- [ ] Bootstrap 4.6.2 CSS classes applied correctly (not Bootstrap 5)
- [ ] All UI text wrapped with i18next.t() (JavaScript) or gettext() (PHP)
- [ ] No alert() calls - use window.CRM.notify() instead
- [ ] Tests pass (if available) - run relevant tests before committing
- [ ] Commit message follows imperative mood (< 72 chars, no file paths)
- [ ] Branch name follows kebab-case format
- [ ] Logs cleared before testing: rm -f src/logs/$(date +%Y-%m-%d)-*.log

---

## File Locations

| Path | Purpose |
|------|---------|
| `src/ChurchCRM/Service/` | Business logic layer |
| `src/ChurchCRM/model/ChurchCRM/` | Propel ORM generated classes (don't edit) |
| `src/api/` | REST API entry point + routes |
| `src/admin/routes/api/` | Admin-only API endpoints (NEW - use this for admin APIs) |
| `src/finance/` | Finance module (Slim 4 MVC) - dashboard, reports |
| `src/Include/` | Utility functions, helpers, Config.php |
| `src/locale/` | i18n/translation strings |
| `src/skin/v2/` | Compiled CSS/JS from Webpack |
| `react/` | React TSX components |
| `webpack/` | Webpack entry points |
| `cypress/e2e/api/` | API test suites |
| `cypress/e2e/ui/` | UI test suites |
| `docker/` | Docker Compose configs |
| `demo/ChurchCRM-Database.sql` | Demo database dump - **NEVER edit manually** (auto-generated) |

---

## Agent Behavior Guidelines

### Documentation Files
- **DO NOT create** unnecessary `.md` review/planning documents unless explicitly requested
- **DO NOT create** analysis or audit documents for the user to review
- Make code changes directly without documentation overhead
- Only create documentation when the user specifically asks for it

### Branching Workflow
- **ALWAYS create a new branch from master** for each issue fix
- **Branch naming**: `fix/issue-NUMBER-description` or `fix/CVE-YYYY-NNNNN-description`
- **Workflow**:
  1. `git checkout master` - start from master
  2. `git checkout -b fix/issue-NUMBER-description` - create feature branch
  3. Make changes and stage files
  4. Commit with descriptive message referencing the issue
- **One issue per branch** - do not mix fixes for different issues

### Git Commits
- **DO NOT auto-commit** changes without explicit user request
- **DO NOT run git commit** commands unless the user explicitly asks
- **DO ask permission** before committing when work is complete: "Tests passed. Ready to commit? [describe changes]"
- **IF user asks to commit**: Use descriptive, imperative mood commit messages referencing the issue
- Tests should pass before committing (if tests exist for the changes)
- Keep commits small and focused

### Code Changes
- Make all requested changes directly to files using appropriate tools
- Use exact tool calls (`replace_string_in_file`, `create_file`, etc.) for precision
- Keep explanations brief and focused on what was changed
- Don't ask for permission—implement code changes based on the user's intent
- If intent is unclear, infer the most useful approach and clarify with the user

### Pull Request Review & Comments

**Always use `gh` CLI for PR details:**
- `gh pr view <number> --json reviews` - Get review comments and status
- `gh pr view <number> --json latestReviews` - Get the most recent reviews with full body text
- `gh pr view <number> --json comments` - Get top-level PR comments
- `gh pr view <number> --comments` - Human-readable view with all comments

**Example workflow when user asks to review a PR:**
1. Use `gh pr view 7774 --json latestReviews` to fetch reviewer comments
2. Check the review state (`COMMENTED`, `APPROVED`, `CHANGES_REQUESTED`)
3. Parse the review body for any requested changes or issues
4. If changes are needed, implement them based on feedback
5. Run tests to verify all changes work
6. Report back with summary of changes made (or "no changes needed" if already good)

**DO NOT** use `github-pull-request_openPullRequest` or `github-pull-request_issue_fetch` tools for PR comments - these return incomplete comment data. Always use `gh` command for full review content.

---

## Security Vulnerability (CVE) Handling

### Reviewing CVE Issues
When asked to review a CVE issue:
1. **Fetch the issue** using `github-pull-request_issue_fetch`
2. **Check if the vulnerable file still exists** - use `file_search` or `read_file`
3. **Verify the specific vulnerability** - check if input sanitization is in place
4. **Focus on security fixes only** - ignore code style issues unless explicitly requested

### Common Security Fixes

**SQL Injection Prevention:**
```php
// CORRECT - Use InputUtils::filterInt() for integer parameters
$iCurrentFundraiser = InputUtils::filterInt($_GET['CurrentFundraiser']);
$tyid = InputUtils::filterInt($_POST['EN_tyid']);

// CORRECT - Use Propel ORM (parameterized queries)
$event = EventQuery::create()->findOneById((int)$eventId);

// WRONG - Raw SQL with unsanitized input
$sSQL = "SELECT * FROM table WHERE id = " . $_GET['id'];
RunQuery($sSQL);
```

**XSS Prevention:**
```php
// CORRECT - Escape output
<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>
<?= htmlentities($value, ENT_QUOTES, 'UTF-8') ?>

// WRONG - Unescaped output
<?= $value ?>
```

### CVE Issue Response Format
When a CVE issue is confirmed fixed, provide this response in a markdown code block:

```markdown
**Issue #XXXX (CVE-YYYY-ZZZZZ) - [Brief Description]:**

[Explanation of how the vulnerability was fixed - 1-2 sentences]

We are deleting this issue to ensure the software's safety. Please refer to the new https://github.com/ChurchCRM/CRM/security/policy for reporting CVE issues. Thank you again for reporting it and helping keep our software secure. Happy to accept the CVE via the new process.
```

### Automated CVE Detection Workflow
The repository has an automated GitHub Actions workflow (`.github/workflows/issue-comment.yml`) that:
1. Detects CVE mentions in issue titles or bodies (patterns: `CVE-`, `CVE-YYYY-NNNNN`, or `GHSA-xxxx-xxxx-xxxx`)
2. Posts a security comment from `.github/issue-comments/security.md`
3. Adds `security` and `security-delete-required` labels
4. Closes the issue automatically

This ensures security vulnerabilities are not publicly disclosed and directs reporters to use GitHub Security Advisories instead.

### Security Policy Reference
- Security policy: `SECURITY.md` in repository root
- Private disclosure: https://github.com/ChurchCRM/CRM/security/advisories
- Issue comment templates: `.github/issue-comments/security.md`

---

## Agent Preferences & Standards

### Service Layer First
- When implementing business logic, **create/update Service classes** in `src/ChurchCRM/Service/`
- Service methods encapsulate domain logic, database operations, and validation
- Call services from legacy pages (`src/*.php`), not raw SQL
- Services use Propel ORM exclusively - no RunQuery() or direct SQL

### Logging Standards
- **Always use LoggerUtils** for business logic operations:
  ```php
  use ChurchCRM\Utils\LoggerUtils;
  $logger = LoggerUtils::getAppLogger();
  $logger->debug('Operation starting', ['context' => $value]);
  $logger->info('Operation succeeded', ['result' => $value]);
  $logger->error('Operation failed', ['error' => $e->getMessage()]);
  ```
- Log levels: `debug` (development info), `info` (business events), `warning` (issues), `error` (failures)
- Include relevant context in log messages as second parameter array

### Import Organization
- Always add `use` statements at the top of files (alphabetically organized)
- Import all external classes/namespaces explicitly
- Do NOT use inline fully-qualified class names (e.g., `\ChurchCRM\model\ChurchCRM\GroupQuery`)
- Exception: Global functions in namespaced code use backslash prefix (e.g., `\MakeFYString()`)

### Testing Approach
- Create Cypress UI tests in `cypress/e2e/ui/` for user workflows
- Do NOT create API tests for simple service calls (test via UI)
- UI tests verify complete workflows end-to-end
- Test files: descriptive names, organized by feature area
- **Run tests with**: `npx cypress run --e2e --spec "cypress/e2e/ui/path/to/test.spec.js"`
- Run full suite with: `npm run test` (runs all tests - use sparingly)
- **ALWAYS run relevant tests before committing**
- Only proceed to commit after tests pass successfully

### API Endpoints
- Create API endpoints in `src/api/routes/` ONLY when needed by external clients
- If a service method is only called from a legacy page, **do NOT create an API endpoint**
- Call services directly from legacy pages instead
- Avoid redundant endpoints that just wrap service calls with no additional value

### Branching & Commits
- Create feature branches: `fix/issue-NUMBER-description` or `feature/description`
- Commit format: Imperative mood, descriptive (not just file names)
- Example: "Fix issue #6672: Renumber group property fields after deletion"
- Include what changed and why in commit message

### File Operations
- **Moving/renaming files**: Always use `git mv` to preserve history
  ```bash
  git mv old/path/file.php new/path/file.php
  ```
- **Creating files**: Use `create_file` tool for new files
- **Deleting files**: Use `rm` command via `run_in_terminal` for simple deletions
- Git will track file moves properly when using `git mv`

### Pull Request Descriptions
- **ALWAYS output PR description in a Markdown code block** when asked to create a PR
- Format PR descriptions with clear sections:
  - **Summary**: Brief overview of changes
  - **Changes**: Bulleted list organized by feature/area
  - **Why**: Motivation and benefits
  - **Files Changed**: List of modified/added/deleted files
- Include all commits in the branch in the description
- Use imperative mood for change descriptions

### API Test Requirements
- **ALWAYS add API tests** when creating new API endpoints
- Test location: `cypress/e2e/api/private/standard/` for standard user endpoints
- Test location: `cypress/e2e/api/private/admin/` for admin-only endpoints
- Required test cases for each endpoint:
  1. **Success case**: Returns expected status code and data structure
  2. **Data validation**: Response contains expected properties and types
  3. **Authentication**: Returns 401 when not authenticated
- Run tests before committing: `npx cypress run --e2e --spec "path/to/test.spec.js"`
- Clear logs before testing: `rm -f src/logs/$(date +%Y-%m-%d)-*.log`

---

Last updated: December 13, 2025

```
