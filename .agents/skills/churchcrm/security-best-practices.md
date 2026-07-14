# Security Best Practices

Comprehensive security guidelines including HTML sanitization, XSS prevention, TLS/SSL, authorization, and vulnerability handling.

---

## Core Security Principles

### Defense Layers

1. **Input Validation** - Reject invalid data at entry
2. **Sanitization** - Remove dangerous content before storage
3. **Authorization** - Verify user permissions for action
4. **Output Escaping** - Protect data when displaying
5. **Network Security** - TLS/SSL for all connections

### Critical Rules

- ✅ ALWAYS escape output when displaying user data
- ✅ ALWAYS validate user permissions before operations
- ✅ ALWAYS use ORM (parameterized queries)
- ✅ ALWAYS verify TLS certificates (default-secure)
- ✅ ALWAYS sanitize HTML with HTML Purifier

---

## HTML Sanitization & XSS Protection

### InputUtils Security Methods

Located in `src/ChurchCRM/Utils/InputUtils.php`

| Method | Use For | Example |
|--------|---------|---------|
| `sanitizeText($input)` | Plain text, removes ALL HTML | Usernames, descriptions |
| `sanitizeHTML($input)` | Rich text with XSS protection | Event descriptions, Quill editor |
| `escapeHTML($input)` | Output escaping for body | `<?= InputUtils::escapeHTML($name) ?>` |
| `escapeAttribute($input)` | Output escaping for attributes | `value="<?= InputUtils::escapeAttribute($val) ?>"` |
| `sanitizeAndEscapeText($input)` | Combined sanitization + escape | Untrusted plain text display |

### Method 1: sanitizeText() - Plain Text

Removes ALL HTML tags:

```php
// ✅ CORRECT - Plain text input
$personName = InputUtils::sanitizeText($_POST['firstName']);
// Input: "
<script>alert('xss')</script>" 
// Result: "alert"

$description = InputUtils::sanitizeText($userInput);
// Result has no HTML - completely safe

// ❌ WRONG - Sanitizes but doesn't escape for display
echo $personName;  // Could still contain special chars
```

### Method 2: sanitizeHTML() - Rich Text

XSS protection while allowing safe HTML. Uses HTML Purifier internally:

```php
// ✅ CORRECT - Rich text from Quill editor
$eventDesc = InputUtils::sanitizeHTML($_POST['description']);
// Allows: <b>, <i>, <u>, <h1-h6>, <pre>, <a>, <img>, <table>, <p>, etc.
// Blocks: <script>, <iframe>, <embed>, <form>, <style>, <meta>

$result = EventQuery::create()
    ->findOneById($eventId)
    ->setDescription(InputUtils::sanitizeHTML($descriptions));

// ❌ WRONG - Uses raw user input
$event->setDescription($_POST['desc']);  // XSS vulnerability!
```

**Allowed Safe Tags:**
```
<a>, <b>, <i>, <u>, <em>, <strong>, <h1-h6>, <pre>, <img>, 
<table>, <tr>, <td>, <th>, <p>, <blockquote>, <div>, <code>, <br>
```

**Blocked Dangerous Tags:**
```
<script>, <iframe>, <embed>, <form>, <style>, <meta>, <link>,
<onclick>, <onerror>, <onload>, on* event handlers
```

### Method 3: escapeHTML() - Output Escaping

Escape data for display in HTML body:

```php
// ✅ CORRECT - Escape for display
<?= InputUtils::escapeHTML($person->getFirstName()) ?>

// Handles:
// - HTML special chars: & < > " '
// - Automatic stripslashes for magic_quotes
// - UTF-8 safe

// ❌ WRONG - Using htmlspecialchars() directly
<?= htmlspecialchars($name) ?>  // Missing stripslashes

// ❌ WRONG - No escaping
<?= $name ?>  // XSS vulnerability!

// ❌ WRONG - Wrong function
<?= htmlentities($name) ?>  // Encodes too much
```

### Method 4: escapeAttribute() - Attribute Values

Escape data for HTML attributes:

```php
// ✅ CORRECT - Escape for attribute
<input type="text" 
       name="firstName"
       value="<?= InputUtils::escapeAttribute($person->getFirstName()) ?>"
       data-person-id="<?= InputUtils::escapeAttribute($personId) ?>">

<a href="<?= InputUtils::escapeAttribute($profileUrl) ?>">Profile</a>

// ❌ WRONG - No escaping in attributes
<input value="<?= $userInput ?>">  // If value contains ", breaks HTML
// Example: value="John" onclick="alert('xss')" " />

// ❌ WRONG - htmlspecialchars without ENT_QUOTES
<input value="<?= htmlspecialchars($val) ?>">  // Single quote not escaped
```

### Method 5: sanitizeAndEscapeText() - Combined

Sanitization + output escaping for untrusted input:

```php
// ✅ CORRECT - Remove HTML then escape for display
$userEntry = InputUtils::sanitizeAndEscapeText($_POST['comment']);
// Removes all HTML, escapes for display

// Equivalent to:
$text = InputUtils::sanitizeText($_POST['comment']);
$escaped = InputUtils::escapeHTML($text);

// ❌ WRONG - Only escaping, doesn't remove HTML
echo InputUtils::escapeHTML($_POST['comment']);
// Displays HTML source if user entered "<b>test</b>"

// ❌ WRONG - Only sanitizing, doesn't escape
echo InputUtils::sanitizeText($_POST['comment']);
// Could display raw content unsafely
```

### Decision Tree: Which Method?

```
Input Type: User-generated text
├─ Will display as plain text?
│  ├─ YES → Use sanitizeText()
│  └─ NO → Go to next
├─ Will be in HTML attribute?
│  ├─ YES → Use escapeAttribute()
│  └─ NO → Go to next
├─ Will be rich text (Quill editor)?
│  ├─ YES → Use sanitizeHTML()
│  └─ NO → Go to next
├─ Mixed use (sometimes plain, sometimes rich)?
│  └─ YES → Use sanitizeAndEscapeText()
└─ Default: Use escapeHTML()
```

### Security Pattern Examples

**User Profile Display:**
```php
// ✅ CORRECT - Sanitize on input, escape on output
$person->setFirstName(InputUtils::sanitizeText($_POST['firstName']));

// Later, in template:
<?= InputUtils::escapeHTML($person->getFirstName()) ?>
```

**Event Description Editor:**
```php
// ✅ CORRECT - Sanitize rich HTML on input
$event->setDescription(InputUtils::sanitizeHTML($_POST['description']));

// Later, in template (already sanitized):
<?= $event->getDescription() ?>  // Already safe
```

**Comment Display:**
```php
// ✅ CORRECT - Sanitize text, escape for display
$comment = InputUtils::sanitizeAndEscapeText($_POST['comment');
// Single-pass security

// Or two-pass approach:
$text = InputUtils::sanitizeText($_POST['comment']);
echo InputUtils::escapeHTML($text);
```

### External Links: `rel="noopener noreferrer"` <!-- learned: 2026-04-07 -->

All `target="_blank"` links MUST include `rel="noopener noreferrer"` to prevent reverse-tabnabbing (the opened page can redirect the opener via `window.opener`).

```php
// ✅ CORRECT
<a href="https://example.com" target="_blank" rel="noopener noreferrer">Link</a>

// ❌ WRONG — allows tabnabbing
<a href="https://example.com" target="_blank">Link</a>
```

---

## SQL Injection Prevention

### Rule: ALWAYS Use Perpl ORM

Never use raw SQL or `RunQuery()`:

```php
// ✅ CORRECT - Propel ORM (parameterized)
$payment = PaymentQuery::create()
    ->filterByAmount($amount)
    ->filterByPersonId((int)$personId)
    ->findOne();

// ✅ CORRECT - Type casting
$person = PersonQuery::create()
    ->findOneById((int)$_GET['personId']);

// ❌ WRONG - Raw SQL (SQL injection vulnerability)
$sql = "SELECT * FROM payment_pym WHERE pym_amount = " . $_GET['amount'];
RunQuery($sql);

// ❌ WRONG - No type casting
PersonQuery::create()
    ->findOneById($_GET['personId']);  // String passed to ID field

// ❌ WRONG - String concatenation
$person = PersonQuery::create()
    ->filterByName("'" . $name . "'");  // SQL injection via quotes
```

### Filter Integer Parameters

```php
// ✅ CORRECT - Cast to int for safety
$userId = InputUtils::filterInt($_GET['userId']);
$fundId = InputUtils::filterInt($_POST['fundId']);

// From PaymentAdd.php:
$personId = InputUtils::filterInt($_GET['personID']);
$paymentId = InputUtils::filterInt(!empty($_POST['Payment']) ? $_POST['Payment'] : 0);

// ❌ WRONG - No filtering
$userId = $_GET['userId'];  // Could be "1 OR 1=1"
```

---

## CSRF Protection on State-Changing Pages

### Every POST/delete page must validate a CSRF token <!-- learned: 2026-04-21 -->

Any legacy `*.php` page that performs a DB write (insert/update/delete) MUST validate a CSRF token before acting. The rule is **validate before any DB write**; legacy pages may reject an invalid token using either of two patterns — pick the one that matches the page's existing error-surfacing style. Applied in `UserEditor.php`, `PledgeDelete.php` (GHSA-3xq9-c86x-cwpp). `DonatedItemDelete.php` and `PaddleNumDelete.php` used this pattern too but were removed in the fundraiser MVC migration (PR #9124); their Slim equivalents (`/fundraiser/{id}/donated-items/{itemId}/delete` and `/fundraiser/{id}/paddle-numbers/{paddleId}/delete`) use `CSRFUtils::verifyRequest()` in the POST handler directly. For MVC routes, use `CSRFMiddleware` added to POST routes and `CSRFUtils::getTokenInputField()` in the view — the middleware **throws `HttpForbiddenException` on token failure** (the caller receives a 403 response); if you want inline re-rendering on failure, catch `HttpForbiddenException` in the route handler yourself:

```php
use ChurchCRM\Utils\CSRFUtils;
use ChurchCRM\Utils\RedirectUtils;

// Pattern A — 403 + exit. Preferred for delete/confirmation pages where there
// is no persistent form to redirect back to. Used by PledgeDelete.php.
if (!CSRFUtils::verifyRequest($_POST, 'pledge_delete')) {
    http_response_code(403);
    exit(gettext('Invalid security token. Please try again.'));
}

// Pattern B (MVC routes) — CSRFMiddleware throws HttpForbiddenException on failure;
// preferred for modern Slim 4 routes. The caller receives a 403 unless the route
// catches HttpForbiddenException and re-renders the form with an error message.
// Token included via CSRFUtils::getTokenInputField().
// Example: /admin/system/users/{personId}/edit in src/admin/routes/system.php.
$group->post('/users/{personId:[0-9]+}/edit', 'adminUserEditorEdit')
    ->add(new CSRFMiddleware('user_editor'));

// In the form HTML (same for both patterns):
<form method="post" action="...">
    <?= CSRFUtils::getTokenInputField('user_editor') ?>
    <!-- other inputs -->
</form>
```

- `getTokenInputField()` auto-renders `<input type="hidden" name="csrf_token" value="...">`.
- Tokens are NOT consumed by default (allows resubmission on validation error). Pass `$consume = true` to `validateToken()` only if you need one-time use.

### CSRFUtils is a single session-wide synchronizer token — `$formId` is ignored <!-- learned: 2026-07-14 -->

As of PR #9124, `CSRFUtils` stores **one stable token per session** in
`$_SESSION['csrf_token']` (singular). The `$formId` argument on every public
method is retained for backward compatibility but **ignored** — all forms share
the one token, matching how Laravel/Django/Rails do CSRF.

**Why the change:** `generateToken()` previously *rotated* the token on every
render and stored it per-`$formId` under `$_SESSION['csrf_tokens'][$formId]`.
Rendering the same form twice before submitting invalidated the first render's
token → `"Invalid security token. Please try again."` on save. The trigger in
the wild was a donated-item whose Picture URL was a **relative string** (e.g.
`"url"`): the template's `<img src="url">` resolved against the page URL and
re-hit the editor route as a second GET, rotating the token out from under the
on-screen form. `generateToken()` now **reuses** the existing non-expired token,
so any number of renders (prefetch, second tab, stray asset request) is safe.

- Don't pass `$formId` in new code — call `getTokenInputField()` / `verifyRequest($body)` with no id.
- Only render user-supplied URLs as `<img src>` when they're absolute: `preg_match('~^https?://~i', $url)`. A relative src can silently re-request the current route.
- `regenerateToken()` still forces a fresh token (e.g. after a privileged op); no current caller uses it.

### Prefer one group-level CSRFMiddleware over per-route wiring (Slim ordering gotcha) <!-- learned: 2026-07-14 -->

In an MVC module, guard **all** state-changing routes with a single
`CSRFMiddleware` on a route **group** rather than repeating `->add()` per route
or calling `CSRFUtils::verifyRequest()` inline in every handler:

```php
use ChurchCRM\Slim\Middleware\CSRFMiddleware;
use Slim\Routing\RouteCollectorProxy;

$app->group('', function (RouteCollectorProxy $group): void {
    $app = $group;                 // alias so required route files that use $app still work
    require __DIR__ . '/routes/foo.php';
    // ...
})->add(new CSRFMiddleware());     // validates every POST/PUT/DELETE/PATCH in the group
```

**Why a group, not `$app->add()`:** Slim runs `BodyParsingMiddleware`
*innermost*, so an app-level middleware executes **before** the body is parsed
and `getParsedBody()` returns null — the token is never seen and every POST
403s. Group (and route) middleware run inside the routing layer, after body
parsing, so the parsed `csrf_token` is available. `CSRFMiddleware` only acts on
POST/PUT/DELETE/PATCH (GET/report routes pass through) and throws
`HttpForbiddenException` (403) on failure. The fundraiser module
(`src/fundraiser/index.php`) uses this; per-route inline `verifyRequest` checks
were removed in favor of it.

### Never delete on GET — always confirmation-page pattern <!-- learned: 2026-04-21 -->

A GET-based delete endpoint (`<a href="Foo.php?id=X">Delete</a>`) is exploitable by any HTML with an `<img>` or `<link>` tag pointing at it — no form submission needed. Convert to a two-step confirmation:

1. **GET** — renders a confirmation `<form method="post">` with the CSRF token and Delete/Cancel buttons.
2. **POST** — validates the token, then performs the delete and redirects.

The caller's link stays a plain GET (`<a href="FooDelete.php?id=X">`), but the landing page now shows the confirmation form instead of silently deleting. This is what `PledgeDelete.php` already did before the CSRF fix; `DonatedItemDelete.php` and `PaddleNumDelete.php` also used this pattern after GHSA-3xq9-c86x-cwpp, but were removed in the fundraiser MVC migration (PR #9124). The new Slim routes enforce the same security guarantee structurally: delete endpoints are POST-only with `CSRFUtils::verifyRequest()`, and the confirmation step is a client-side `confirm()` dialog.

### Role checks belong on every delete page <!-- learned: 2026-04-21 -->

Delete pages must also enforce the relevant role guards — a CSRF token alone doesn't gate the feature, it only proves the request came from a real logged-in session. Finance-scoped deletes need both:

```php
AuthenticationManager::redirectHomeIfFalse(AuthenticationManager::getCurrentUser()->isDeleteRecordsEnabled(), 'DeleteRecords');
AuthenticationManager::redirectHomeIfFalse(AuthenticationManager::getCurrentUser()->isFinanceEnabled(), 'Finance');
```

### Don't use `$_REQUEST` for destructive-action inputs <!-- learned: 2026-04-22 -->

For pages that both render (GET) and perform (POST) a destructive action, read the primary-key / redirect params from the superglobal that matches the current request method, not from `$_REQUEST`. `$_REQUEST`'s contents depend on PHP's `request_order` ini setting and may include `$_COOKIE`, which means an attacker-set cookie could silently influence the ID that gets deleted.

```php
// ✅ CORRECT — read from $_POST on POST, $_GET on GET
$isPostAction = isset($_POST['Delete']) || isset($_POST['Cancel']);
$idSource = $isPostAction ? ($_POST['GroupKey'] ?? '') : ($_GET['GroupKey'] ?? '');
$sGroupKey = InputUtils::legacyFilterInput($idSource, 'string');

// ❌ WRONG — $_REQUEST can resolve to $_COOKIE based on request_order
$sGroupKey = InputUtils::legacyFilterInput($_REQUEST['GroupKey'] ?? '', 'string');
```

### Pass `linkBack` as a hidden input, not a query-string param on the form action <!-- learned: 2026-04-22 -->

`RedirectUtils::validateRedirectUrl()` intentionally allows `?` and `&` in relative return-URLs (e.g. `FindFundRaiser.php?FundRaiserID=3`). Interpolating such a value into the `action="..."` attribute as a query-string segment corrupts the URL — the browser parses the embedded `&` as a parameter boundary, and outputting raw `&` in an HTML attribute is invalid markup. Always post return-URLs via hidden form inputs and re-validate server-side before redirecting:

```php
// ✅ CORRECT — post linkBack as a hidden field, revalidate on the server
// Form:
<form method="post" action="PledgeDelete.php">
    <?= CSRFUtils::getTokenInputField('pledge_delete') ?>
    <input type="hidden" name="GroupKey" value="<?= InputUtils::escapeAttribute($sGroupKey) ?>">
    <input type="hidden" name="linkBack" value="<?= InputUtils::escapeAttribute($linkBack) ?>">
    ...
</form>

// Handler:
$linkBack = RedirectUtils::validateRedirectUrl(
    InputUtils::legacyFilterInput($_POST['linkBack'] ?? '', 'string') ?? '',
    'v2/dashboard'
);

// ❌ WRONG — `$linkBack` containing `&` truncates; raw `&` in attribute is invalid HTML
<form action="PledgeDelete.php?GroupKey=<?= htmlspecialchars($sGroupKey) ?>&linkBack=<?= InputUtils::escapeAttribute($linkBack) ?>">
```

---

## Authorization & Access Control

### The Entry Gate: `isEditSelfExclusive()` (read-default policy) <!-- learned: 2026-07-11 -->

> ⚠️ **`hasNoAdminPermissions()` was REMOVED in #9121.** If you find it referenced
> anywhere, that guidance is stale. Do not reintroduce it.

The entry gate bounces a user out of the whole CRM and into `/external/limited-access`.
Only **EditSelf-exclusive** users hit it:

```php
// src/ChurchCRM/model/ChurchCRM/User.php
public function isEditSelfExclusive(): bool
{
    return !$this->isAdmin() && $this->isEditSelf();
}
```

**The semantics inverted, not just the name.** Under the old `hasNoAdminPermissions()`,
a **zero-permission user (all flags 0) was also bounced**. Under the **read-default
policy (#9003 / #9121) they are not** — reading people and family records is now a
default capability of any authenticated user. Writes are still denied, by the per-page
and per-route permission checks rather than by the gate.

| User | Entry gate | Effect |
|------|-----------|--------|
| Admin | passes | full access |
| Any module permission (Notes, Finance, …) | passes | read all; write per permission |
| **Zero-permission (all flags 0)** | **passes** | **read-only people/family; no writes** |
| **EditSelf-exclusive (EditSelf=1, rest 0)** | **blocked** | confined to `/external/limited-access` |

Note EditSelf is **exclusive**, not additive: an EditSelf+Notes user is *not*
EditSelf-exclusive, so they pass the gate and are then scoped by object-level checks.
Every `isXxxEnabled()` getter also short-circuits to `false` for EditSelf-exclusive users.

The gate fires in two places — a check added to only one leaves a hole:

```php
// src/Include/PageInit.php — legacy *.php pages
if ($currentUser->isEditSelfExclusive()) {
    RedirectUtils::redirect(SystemURLs::getRootPath() . '/external/limited-access');
}

// src/ChurchCRM/Slim/Middleware/AuthMiddleware.php — MVC + API
if ($sessionUser->isEditSelfExclusive() && !$this->isAuthFlowExemptPath($request)) {
    // browser → 302 /external/limited-access; API → 403 JSON
}
```

`isAuthFlowExemptPath()` keeps login/password-change/logout reachable — without it an
EditSelf-exclusive user redirects in a loop.

**Read is a default capability.** `canReadPerson(int $personId)` and
`canReadFamily(int $familyId = 0)` both `return true` for any authenticated user. The ID
parameters are **deliberate ABAC hooks** — pass the real ID at every call site so that
adding row-level rules later (pastoral-confidentiality holds, privacy flags) needs no
call-site changes. Do not "simplify" them away because they look unused.

**Reference**: GHSA-5w59-32c8-933v / PR #8616 / Issue #237 (original gate);
#9003 / #9121 (read-default policy). Covered by
`cypress/e2e/ui/security/no-permission-user.spec.js` and `limited-access.spec.js`.

### MVC vs Legacy Auth Enforcement Points <!-- learned: 2026-04-12 -->

Permission checks must run in BOTH `AuthMiddleware` (MVC + API) AND `PageInit.php` (legacy). Adding a check in only one leaves a hole — the MVC modules `/people/`, `/admin/`, `/v2/`, `/event/`, `/groups/`, `/finance/` all use `AuthMiddleware`, while legacy `*.php` files in `src/` go through `PageInit.php`.

### Object-Level Authorization

Check if user can edit SPECIFIC person:

```php
<?php
// ✅ CORRECT - Object-level permission check
$currentUser = AuthenticationManager::getCurrentUser();
$personId = InputUtils::filterInt($_POST['personId']);
$person = PersonQuery::create()->findOneById($personId);

if (!$currentUser->canEditPerson($personId, $person->getFamId())) {
    RedirectUtils::securityRedirect('PropertyAssign');
}

// Update person
$person->setFirstName(InputUtils::sanitizeText($_POST['firstName']));
$person->save();

// ❌ WRONG - Only role check (broken access control)
if (!$currentUser->isEditRecordsEnabled()) {
    // User with EditRecords can edit ANY person, including others' family members
    // Missing object-level check!
    
    // This is exploitable:
    // Step 1: User logs in with EditRecords permission
    // Step 2: User manually crafts POST with someone else's personId
    // Step 3: No object-level check prevents editing their data
}
```

### Role-Based Authorization

```php
// ✅ CORRECT - Role-based checks
$user = AuthenticationManager::getCurrentUser();

if (!$user->isAdmin()) {
    RedirectUtils::securityRedirect('AdminAccess');
}

if (!$user->isEditRecordsEnabled()) {
    RedirectUtils::securityRedirect('EditRecords');
}

if (!$user->isManageGroupsEnabled()) {
    RedirectUtils::securityRedirect('GroupManage');
}

// ❌ WRONG - Failing silently
if (!$user->isAdmin()) {
    // Silently continue with operation
    // User has no idea permission was denied
}
```

### Authorization Pattern

```php
// Step 1: Check general permission (role-based)
if (!AuthenticationManager::getCurrentUser()->isEditRecordsEnabled()) {
    RedirectUtils::securityRedirect('EditRecords');
}

// Step 2: Check object-level permission (break privilege escalation)
if (!AuthenticationManager::getCurrentUser()->canEditPerson($personId, $familyId)) {
    RedirectUtils::securityRedirect('PropertyAssign');
}

// Step 3: Check resource exists (redirect to not-found if missing)
$person = PersonQuery::create()->findOneById($personId);
if ($person === null) {
    RedirectUtils::redirect('v2/person/not-found?id=' . $personId);
}

// NOW safe to proceed
$person->setFirstName($input);
```

---

## TLS/SSL Verification

### Secure by Default

Enable TLS verification by default, make insecure behavior opt-in:

```php
// ✅ CORRECT - Verify SSL by default
public function sendRequest(string $url, bool $allowSelfSigned = false): void
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    
    if ($allowSelfSigned) {
        // Only for explicitly configured self-signed certs (local networks)
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    } else {
        // Default: verify SSL certificates
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);  // Verify hostname
        curl_setopt($ch, CURLOPT_CAINFO, '/etc/ssl/certs/ca-bundle.crt');
    }
    
    $result = curl_exec($ch);
    if ($result === false) {
        error_log("SSL Error: " . curl_error($ch));
    }
    curl_close($ch);
}

// ❌ WRONG - Disables security by default (MITM vulnerability)
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);  // ❌ Security hole!
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);      // ❌ Allows MITM attacks
```

### Configuration for Self-Signed Certs

In local/home networks with self-signed certificates (OpenLP, etc.):

```php
// Config with annotation explaining risk
public const ALLOW_SELF_SIGNED_CERTS = false;  // Set true only for local networks

public function connectToLocalServer(): void
{
    $allowInsecure = self::ALLOW_SELF_SIGNED_CERTS;
    $this->sendRequest('https://local-server:8080/api', $allowInsecure);
}
```

---

## API Error Handling

### Use SlimUtils::renderErrorJSON()

Never throw exceptions in API routes:

```php
// ✅ CORRECT - Wrap in try/catch, return sanitized error
$group->post('/endpoint', function (Request $request, Response $response) {
    try {
        // Your operation
        $result = doSomething();
        return SlimUtils::renderJSON($response, ['data' => $result]);
    } catch (\Throwable $e) {
        $status = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
        return SlimUtils::renderErrorJSON($response, gettext('Operation failed'), [], $status, $e, $request);
    }
});

// ❌ WRONG - Throws exception (exposes details to client)
throw new HttpNotFoundException($request);
// Client receives: Full stack trace with file paths, SQL, config

// ❌ WRONG - Returns raw error
return $response->withStatus(500)->write($e->getMessage());
// Could expose: SQL errors, file paths, internal configs
```

### Error Response Sanitization

`SlimUtils::renderErrorJSON()` automatically:
- Logs exception server-side (full details)
- Sanitizes message (detects password/token/host patterns)
- Returns generic message to client
- Never exposes: stack traces, file paths, database errors

---

## CVE & Security Vulnerability Handling

### Reviewing CVE Issues

1. **Fetch the issue** - Get CVE details
2. **Check vulnerable file exists** - Verify it still needs fixing
3. **Verify vulnerability** - Confirm input sanitization/validation
4. **Focus on security only** - Ignore code style issues

### Common Vulnerability Types

**SQL Injection:**
```php
// ❌ VULNERABLE - Raw SQL with user input
$sql = "SELECT * FROM person WHERE name = '" . $_POST['name'] . "'";
RunQuery($sql);

// ✅ FIXED - Use ORM
PersonQuery::create()
    ->filterByName(InputUtils::sanitizeText($_POST['name']))
    ->find();
```

**XSS (Cross-Site Scripting):**
```php
// ❌ VULNERABLE - No output escaping
<?= $_POST['comment'] ?>

// ✅ FIXED - Escape output
<?= InputUtils::escapeHTML($_POST['comment']) ?>
```

**Broken Access Control:**
```php
// ❌ VULNERABLE - Only checks role, not specific object
if (isAdmin()) {
    deletePerson($_POST['personId']);  // Any admin can delete anyone
}

// ✅ FIXED - Check object-level permission
if ($user->canEditPerson($personId, $familyId)) {
    deletePerson($personId);
}
```

### Reporting & Response

When CVE is confirmed fixed:

```markdown
**Issue #XXXX (CVE-YYYY-ZZZZZ) - SQL Injection via Payment Amount:**

The vulnerability allowed unauthenticated attackers to inject SQL via the
payment amount parameter. Fixed by replacing raw SQL with Perpl ORM queries
and filtering integer inputs through InputUtils::filterInt().

We are deleting this issue to ensure the software's safety. Please refer to
https://github.com/ChurchCRM/CRM/security/policy for reporting CVE issues.
Thank you for helping keep ChurchCRM secure!
```

---

## Pre-Release Security Checklist

Before committing security fixes:

- [ ] All user input sanitized (InputUtils for HTML, ORM for SQL)
- [ ] Output properly escaped (escapeHTML/escapeAttribute)
- [ ] Object-level authorization checked (canEditPerson)
- [ ] API errors return sanitized messages (SlimUtils::renderErrorJSON)
- [ ] TLS verification enabled by default (allow opt-out only)
- [ ] No hardcoded credentials in code
- [ ] No debug output left (var_dump, console.log)
- [ ] Tests pass (including security tests)
- [ ] Code review completed

---

## Related Skills

- [Authorization & Security](./authorization-security.md) - Permission checks, authorization redirect patterns, User class methods
- [PHP Best Practices](./php-best-practices.md) - Service layer, logging, ORM patterns
- [Git Workflow](./git-workflow.md) - Pre-commit validation checklist
- [Configuration Management](./configuration-management.md) - Secure config handling

---

### No Inline Event Handlers (CSP Compliance) <!-- learned: 2026-03-28 -->

Never use inline `onclick`, `onchange`, or other `on*` attributes in HTML/PHP templates. ChurchCRM enforces CSP with `script-src` that does not include `unsafe-inline`, so inline handlers are silently blocked.

```php
// ❌ WRONG — blocked by CSP
<button onclick="window.print()">Print</button>

// ✅ CORRECT — bind in the page's JS file
<button id="printPerson">Print</button>
```
```js
// In skin/js/PersonView.js (or relevant JS file)
$("#printPerson").on("click", function () { window.print(); });
```

---

### Always Escape SystemConfig Values in Output — Use Context-Specific Getters <!-- learned: 2026-04-03, updated: 2026-04-03 -->

`SystemConfig::getValue()` returns raw strings from the database. **Never use it directly
in HTML or JavaScript output.** Use the context-specific helper methods instead — they
encode correctly for the target context and prevent double-escaping:

| Method | Use for | Encoding |
|--------|---------|----------|
| `SystemConfig::getValueForAttr($name)` | HTML attributes: `value=`, `placeholder=`, `data-*=` | `htmlspecialchars()` |
| `SystemConfig::getValueForHtml($name)` | HTML text content: textarea body, labels | `htmlspecialchars()` |
| `SystemConfig::getValueForJs($name)` | JavaScript literals, JSON blobs in `<script>` | `json_encode()` (includes surrounding quotes) |

```php
// ❌ WRONG — raw config value in HTML attribute
data-system-default="<?= SystemConfig::getValue('sDefaultState') ?>"

// ❌ WRONG — manual wrapping (verbose, easy to miss)
data-system-default="<?= InputUtils::escapeAttribute(SystemConfig::getValue('sDefaultState')) ?>"

// ✅ CORRECT — use context getter
data-system-default="<?= SystemConfig::getValueForAttr('sDefaultState') ?>"
data-phone-mask='{"mask":"<?= SystemConfig::getValueForAttr('sPhoneFormat') ?>"}'

// ✅ CORRECT — JS literal (getValueForJs() includes surrounding quotes — do NOT add "")
timeZone:<?= SystemConfig::getValueForJs('sTimeZone') ?>,
churchWebSite:<?= SystemConfig::getValueForJs('sChurchWebSite') ?>,

// ✅ CORRECT — textarea / HTML text content
<textarea><?= SystemConfig::getValueForHtml('sDirectoryDisclaimer1') ?></textarea>
```

**Files where this pattern is enforced** (src/ChurchCRM/dto/SystemConfig.php defines the helpers):
- [src/Include/Header.php](../../../src/Include/Header.php) — `window.CRM` JS literals
- [src/Include/HeaderNotLoggedIn.php](../../../src/Include/HeaderNotLoggedIn.php) — `churchWebSite` JS literal
- [src/FamilyEditor.php](../../../src/FamilyEditor.php) — `data-system-default`, `data-phone-mask`, `placeholder`
- [src/PersonEditor.php](../../../src/PersonEditor.php) — date picker `placeholder`
- [src/CartToFamily.php](../../../src/CartToFamily.php) — `data-inputmask` phone format
- [src/ChurchCRM/utils/CustomFieldUtils.php](../../../src/ChurchCRM/utils/CustomFieldUtils.php) — `placeholder`, `data-phone-mask`
- [src/DirectoryReports.php](../../../src/DirectoryReports.php) — form `value=`, textarea content
- [src/external/templates/registration/family-register.php](../../../src/external/templates/registration/family-register.php) — JS literals, address/phone inputs

### JSON_HEX flags for inline `<script>` JSON <!-- learned: 2026-04-21 -->

When embedding PHP data into a JS literal inside a `<script>` tag, `json_encode()`
alone does not escape `<`, `>`, `&`, `'`, `"` — a value containing `</script>`
(or `</SCRIPT`, `--!>`, etc.) can break out of the script context and become
XSS. Always pass the four HEX flags alongside `JSON_THROW_ON_ERROR`:

```php
// ❌ WRONG — </script> in the data closes the script tag
<script>
window.CRM.calendarArgs = <?= json_encode($args, JSON_THROW_ON_ERROR) ?>;
</script>

// ✅ CORRECT — hex-encodes </script> and friends
<script>
window.CRM.calendarArgs = <?= json_encode(
    $args,
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR
) ?>;
</script>
```

Grep for lone `json_encode($x, JSON_THROW_ON_ERROR)` inside `<script>` tags when
reviewing — **every** such call needs the HEX flags. `SystemConfig::getValueForJs()`
already handles this internally; the HEX flags apply when you call `json_encode()`
directly on arrays/objects. Example site: [src/event/views/calendar.php](../../../src/event/views/calendar.php).

### Escape captured SMTP / debug output before rendering as HTML <!-- learned: 2026-04-21 -->

PHPMailer's `Debugoutput='html'` and similar debug capture modes emit **untrusted
server-originated HTML** — server banners, auth exchanges, error text. Dumping the
captured buffer straight into a page — even an admin-only page — is an XSS vector
because a remote SMTP server (or any downstream service an attacker controls) owns
part of the string. Strip tags, escape, then re-introduce line breaks with
`nl2br()`:

```php
// ❌ WRONG — raw HTML from the SMTP server
<div><?= $sendResult['debugLog'] ?></div>

// ✅ CORRECT — strip, decode entities, escape, re-add line breaks
$plain = trim(html_entity_decode(
    strip_tags((string) $sendResult['debugLog']),
    ENT_QUOTES | ENT_HTML5,
    'UTF-8'
));
echo nl2br(InputUtils::escapeHTML($plain), false);
```

Same pattern applies to any "capture stream, then render" flow: `ob_get_clean()`
output, cURL verbose logs, library diagnostic dumps. "Admin-only" is not a
mitigation — an admin viewing a compromised downstream service's output is the
exact attack surface.

### sanitizeText() Is Not Sufficient for HTML Attributes <!-- learned: 2026-04-03 -->

`InputUtils::sanitizeText()` only calls `strip_tags()` — it does NOT escape HTML
entities. A payload like `" onclick="alert(1)` passes through `sanitizeText()` intact
and breaks out of an attribute context. Always use `escapeAttribute()` for `href=`
and `escapeHTML()` for link text.

```php
// ❌ WRONG — sanitizeText doesn't escape quotes
<a href="https://facebook.com/<?= InputUtils::sanitizeText($per_Facebook) ?>">
    <?= $per_Facebook ?>
</a>

// ✅ CORRECT — escapeAttribute for href, escapeHTML for text
<a href="https://facebook.com/<?= InputUtils::escapeAttribute($per_Facebook) ?>">
    <?= InputUtils::escapeHTML($per_Facebook) ?>
</a>
```

### API Routes Need Explicit Auth Middleware <!-- learned: 2026-04-03 -->

Group-level middleware (e.g., `FamilyMiddleware`) only validates the entity exists —
it does NOT check user permissions. Each route that modifies data must explicitly add
the appropriate auth middleware. Check for this pattern when reviewing API routes:

```php
// ❌ WRONG — only entity existence check (any authenticated user can call)
$group->post('/verify', function (...) {
    $family->sendVerifyEmail();
    return SlimUtils::renderSuccessJSON($response);
});
// Group-level FamilyMiddleware only checks family exists

// ✅ CORRECT — add permission middleware per route
$group->post('/verify', function (...) {
    $family->sendVerifyEmail();
    return SlimUtils::renderSuccessJSON($response);
})->add(EditRecordsRoleAuthMiddleware::class);
```

### Command Injection: execFileSync Over execSync <!-- learned: 2026-04-03 -->

Node.js `execSync(cmd)` passes the string through a shell, enabling injection via
metacharacters (`; | && $()` etc.). Use `execFileSync(program, args[])` which bypasses
the shell entirely.

```js
// ❌ WRONG — shell string injection risk
const { execSync } = require('child_process');
execSync(`git commit -m "${message}"`); // message could contain "; rm -rf /"

// ✅ CORRECT — array args, no shell
const { execFileSync } = require('child_process');
execFileSync('git', ['commit', '-m', message]); // message is a single argument
```

### Session Values and extract() Results in SQL Need Explicit int Cast <!-- learned: 2026-04-03 -->

`$_SESSION` values and variables produced by `extract($row)` are strings, not integers.
When interpolated into SQL without quotes they are directly injectable. Always cast to `(int)` at the point of use.

```php
// ❌ WRONG — session value uncast, injectable in unquoted integer context
$id = $_SESSION['iCurrentFundraiser'];
$sql = 'WHERE fr_ID = ' . $id;

// ✅ CORRECT — cast at read time
$id = (int)$_SESSION['iCurrentFundraiser'];

// ❌ WRONG — extract() result used bare in SQL
extract($row);  // sets $pn_per_ID as string
$sql = '... AND di_donor_id = ' . $pn_per_ID;

// ✅ CORRECT — cast at point of use (or before heredoc where inline cast is not possible)
$iPnPerID = (int)$pn_per_ID;
$sql = '... AND di_donor_id = ' . (int)$pn_per_ID;
```

---

### Permission Checks Must Appear on Both Display and Save <!-- learned: 2026-04-03 -->

Hiding a form field is not a security control — a user can POST to the save handler directly.
Any permission check applied to the display loop (`if permission != 'TRUE' → skip row`) must
also be applied in the save loop (`if permission != 'TRUE' → skip update`).

```php
// ❌ WRONG — check only on display, not on save
foreach ($configs as $config) {
    if (!($config->getPermission() === 'TRUE' || $user->isAdmin())) continue; // display gate
    // ... render field
}
// save loop has no equivalent check — non-admin can POST arbitrary IDs

// ✅ CORRECT — mirror the same check in the save loop
while ($current_type = current($type)) {
    // ... filter value ...
    $userConfig = UserConfigQuery::create()->filterById($id)->filterByPeronId($userId)->findOne();
    // Enforce same permission gate as display
    if (!$user->isAdmin() && $userConfig->getPermission() !== 'TRUE') {
        next($type);
        continue;
    }
    $userConfig->setValue($value)->save();
    next($type);
}
```

---

### Clone ORM Records: Copy All NOT NULL Columns <!-- learned: 2026-04-03 -->

When cloning a Propel record to create a copy for another user/context, check the
schema for `NOT NULL` columns. Missing a required column causes a silent database
error. Common miss: `ucfg_cat` on `UserConfig`.

```php
// ❌ WRONG — misses ucfg_cat (NOT NULL column)
$userConfig = new UserConfig();
$userConfig->setPeronId($userId)->setId($id)
    ->setName($default->getName())
    ->setValue($default->getValue());
$userConfig->save(); // Fails: ucfg_cat cannot be null

// ✅ CORRECT — copy all NOT NULL columns
$userConfig = new UserConfig();
$userConfig->setPeronId($userId)->setId($id)
    ->setName($default->getName())
    ->setValue($default->getValue())
    ->setType($default->getType())
    ->setCat($default->getCat())          // NOT NULL — don't forget!
    ->setTooltip($default->getTooltip())
    ->setPermission($default->getPermission());
$userConfig->save();
```

### Use Typed Config Getters — Never getValue() for Boolean/Int Contexts <!-- learned: 2026-04-03 -->

Always match the getter to the config key prefix and usage context:

| Key prefix | Correct getter | Wrong getter |
|-----------|---------------|--------------|
| `bXxx` in `if()` condition | `getBooleanValue('bXxx')` | `getValue('bXxx')` |
| `iXxx` in arithmetic / ORM `limit()` | `getIntValue('iXxx')` | `getValue('iXxx')` |
| `(int) getValue('iXxx')` anywhere | `getIntValue('iXxx')` | manual cast |

```php
// ❌ WRONG — getValue() returns string, PHP silently coerces
if (SystemConfig::getValue('bUseScannedChecks')) { ... }
->limit(SystemConfig::getValue('bSearchIncludePersonsMax'))

// ✅ CORRECT
if (SystemConfig::getBooleanValue('bUseScannedChecks')) { ... }
->limit(SystemConfig::getIntValue('bSearchIncludePersonsMax'))
```

Note: `iChurchLatitude` / `iChurchLongitude` are named with `i` prefix but store float values — use `(float) getValue()` for these two keys, not `getIntValue()`.

### Operator Precedence Trap with getValue() in Ternary <!-- learned: 2026-04-03 -->

PHP's `.` (concatenation) operator has higher precedence than `?:`. Mixing them without parentheses silently produces wrong logic:

```php
// ❌ BUG — evaluates as: (' per_fmr_ID = ' . getValue()) ?: '1'
// The entire LHS is truthy, so the fallback '1' is never used
$head_criteria = ' per_fmr_ID = ' . SystemConfig::getValue('sDirRoleHead') ? SystemConfig::getValue('sDirRoleHead') : '1';

// ✅ CORRECT — parentheses force ternary to apply only to the config value
$head_criteria = ' per_fmr_ID = ' . (SystemConfig::getValue('sDirRoleHead') ?: '1');
```

---

### data-* Attributes Require escapeAttribute() — Not Just escapeHTML() <!-- learned: 2026-04-05 -->

`data-*` HTML attributes are attribute contexts, not body text contexts. Always use
`InputUtils::escapeAttribute()` for `data-*` values. Using `escapeHTML()` or no escaping
at all still allows attribute-breakout XSS (e.g. a value containing `"` terminates the
attribute and injects new ones).

Real-world example: `PersonView.php` line 816 — `data-groupname` was unescaped, fixed
as part of GHSA-44j4-jjw2-wcr6:

```php
// ❌ WRONG — unescaped data attribute (XSS via attribute breakout)
data-groupname="<?= $grp_Name ?>"

// ❌ ALSO WRONG — escapeHTML() is for body text, not attributes
data-groupname="<?= InputUtils::escapeHTML($grp_Name) ?>"

// ✅ CORRECT — escapeAttribute() for any attribute value, including data-*
data-groupname="<?= InputUtils::escapeAttribute($grp_Name) ?>"
```

This applies to ALL `data-*` attributes whether the source is a database field, ORM
getter, or user-supplied input.

---

### ListOption::getOptionName() Must Be Escaped in HTML Contexts <!-- learned: 2026-04-05 -->

`getOptionName()` returns raw database strings — admin-entered values that can contain
`<`, `>`, or `"`. Always wrap with `InputUtils::escapeHTML()` when rendering inside
HTML elements. Affects group roles, family roles, group types, custom field options,
classifications, and any other ListOption values rendered to the DOM.

```php
// ❌ WRONG — raw getOptionName() in <option> or <td>
echo '<option value="' . $role->getOptionId() . '">' . $role->getOptionName() . '</option>';

// ✅ CORRECT — escape before output
echo '<option value="' . $role->getOptionId() . '">' . InputUtils::escapeHTML($role->getOptionName()) . '</option>';

// ✅ CORRECT — when wrapped in gettext()
echo InputUtils::escapeHTML(gettext($role->getOptionName()));
```

Fixed in GHSA-j9gv-26c7-3qrh (CVE-2025-67876) across 6 files: MemberRoleChange.php,
CartToFamily.php, GroupEditor.php, CartToEvent.php, CustomFieldUtils.php,
external/templates/registration/family-register.php.

---

### InputSanitizationMiddleware Is the Standard for API Write Routes <!-- learned: 2026-07-11 -->

For Slim API/MVC write routes (`post`/`put`/`patch`), sanitize free-text body
fields with **`InputSanitizationMiddleware`** attached to the route — do **not**
call `InputUtils::sanitizeText()` / `sanitizeHTML()` inline in the handler. The
middleware sanitizes the parsed body **before** the handler runs, so every code
path in the handler (storage, logging, echo-back) sees the clean value and no
field can be forgotten.

```php
use ChurchCRM\Slim\Middleware\InputSanitizationMiddleware;

// Field map: 'text' → InputUtils::sanitizeText() (strip tags);
//            'html' → InputUtils::sanitizeHTML() (HTMLPurifier, safe rich text).
$group->post('', 'createFund')
    ->add(new InputSanitizationMiddleware(['name' => 'text', 'description' => 'text']));

$group->put('', 'updateNote')
    ->add(new InputSanitizationMiddleware(['text' => 'html']));

// Handler reads the already-sanitized field directly — cast for type safety,
// keep validation (empty/length/uniqueness) which now runs on the clean value:
function createFund(Request $request, Response $response): Response
{
    $input = (array) $request->getParsedBody();
    $name  = (string) ($input['name'] ?? '');   // already sanitized by middleware
    if ($name === '') { /* 400 */ }
    // ...
}
```

**Middleware order (LIFO rule):** In Slim 4, `->add()` is Last-In-First-Out —
the **last** `->add()` call is outermost and runs **first**. Therefore:

- Add `InputSanitizationMiddleware` **first** (`->add()` call #1) so it is
  innermost and runs **just before the handler** (sanitize-then-handle).
- Add auth/entity middleware **after** it so they are outer layers and run
  **before** sanitization.

Concrete pattern (follows `groups-properties.php`):

```php
$group->post('/{id}/resource/{resId}', 'myHandler')
    ->add(new InputSanitizationMiddleware(['name' => 'text'])) // 1st = innermost = runs last
    ->add($entityMiddleware)                                   // 2nd = runs before sanitize
    ->add(SomeAuthMiddleware::class);                          // 3rd = outermost = runs first
// Execution order: SomeAuthMiddleware → $entityMiddleware → InputSanitizationMiddleware → handler
```

❌ **Wrong** — adding `InputSanitizationMiddleware` last makes it outermost
(runs before auth), defeating the purpose of sanitizing input that only
arrives after entity resolution:

```php
// WRONG — sanitize runs before auth/entity (inverted LIFO order)
$group->post('/…', 'handler')
    ->add($entityMiddleware)
    ->add(SomeAuthMiddleware::class)
    ->add(new InputSanitizationMiddleware(['name' => 'text'])); // last = outermost = wrong
```

**Routes using this pattern** (`src/ChurchCRM/Slim/Middleware/InputSanitizationMiddleware.php`):
calendar events/`calendar.php`, group create + roles (`people-groups.php`),
finance deposits/donation-funds/fundraisers, property-types,
volunteer-opportunities, list options (`admin/api/options.php`), person/family
notes (`html`), and person/group property values. When adding a **new** write
route that stores free text, add the middleware — never a bare `getParsedBody()`
value into a setter.

> Note: legacy `src/*.php` pages still sanitize inline with `InputUtils` because
> they are not Slim routes — the middleware only applies to the Slim apps.

### Input Sanitization Is Not Output Escaping — Escape Names Client-Side Too <!-- learned: 2026-07-11 -->

`InputSanitizationMiddleware` / `sanitizeText()` strips tags **on the primary
write paths**, but it is **not** a complete XSS defense on its own. Values can
still contain markup when they arrive by a path that bypasses the middleware —
data migrated from older versions, SQL/backup restore, CSV import, or a future
route that forgets the middleware. **Output escaping is the authoritative
control**, and it must be applied on the **client side** too, not only in PHP.

The server-side ListOption/`OptionName` escaping was fixed in GHSA-j9gv-26c7-3qrh,
but the **JavaScript** renderings of the same values were missed (draft
GHSA-mfmp-q643-vj39): `src/skin/js/GroupView.js` and `GroupRoles.js` injected
group-role names into the DOM as raw HTML via `.html()` / `.append()`.

```js
// ❌ WRONG — role name injected as raw HTML (executes if it contains markup)
html += '<a class="nav-link" href="#">' + i18next.t(role.OptionName) + '</a>';
$pills.html(html);

// ✅ CORRECT — escape every DB/API-sourced string before it becomes HTML
html += '<a class="nav-link" href="#">' +
        window.CRM.escapeHtml(i18next.t(role.OptionName)) + '</a>';
$pills.html(html);
```

**Rule:** any DB/API-sourced string (names, titles, `OptionName`, `getName()`,
custom-field labels) inserted into the DOM via `.html()`, `.append()`,
`innerHTML`, or a template literal MUST be wrapped in `window.CRM.escapeHtml()`
— even when the write path sanitizes input. When reviewing JS, grep for
`.OptionName`, `.Name`, `.title` near `.html(`/`.append(`/`innerHTML` and confirm
`escapeHtml` wraps each one. `$("<div>").text(x).html()` is an equivalent escape.

---

Last updated: July 11, 2026
