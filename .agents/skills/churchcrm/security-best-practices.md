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

## Config & Secrets: Data/Code Separation <!-- learned: 2026-04-22 -->

**Never render user-supplied values into an executable PHP file.** Templating untrusted input into a `.php` file reopens the pre-auth RCE class regardless of how carefully you sanitize at write time — one missed field, one future placeholder, and a quote-breaking payload becomes PHP code (GHSA-mp2w-4q3r-ppx7 / CVE-2026-39337 was exactly this pattern: `$sPASSWORD = '||DB_PASSWORD||';` with a raw password).

**Correct architecture**: values live in data, `Config.php` is a static bootstrap.

```php
// src/Include/Config.php.example (static — shipped in the repo, copied verbatim on install)
$values = \ChurchCRM\Config\ConfigLoader::load(__DIR__ . '/config-values.json');
$sSERVERNAME = $values['DB_SERVER_NAME'];
$sPASSWORD   = $values['DB_PASSWORD'];
// ...
```

```php
// src/setup/routes/setup.php — POST handler writes data, never code
file_put_contents($tmp, json_encode($userInput, JSON_THROW_ON_ERROR), LOCK_EX);
rename($tmp, $valuesFile);          // atomic data write
copy($examplePath, $configFile);    // verbatim bootstrap — no substitution
```

```php
// src/ChurchCRM/Config/ConfigLoader.php — validation happens ON READ
if (!preg_match('/^[0-9]{1,5}$/', $data['DB_SERVER_PORT'])) {
    throw new RuntimeException('Invalid DB_SERVER_PORT');
}
```

### Rules

- **Write JSON, not PHP.** `json_encode()` quoting is airtight for arbitrary bytes.
- **Validate at read time, not write time.** The boundary to defend is where data becomes code — which is `Config.php` → `require_once`, not the wizard. The loader rejects malformed fields regardless of how the file got there (attacker with filesystem access, misconfigured migration, hand-edited typo, etc.).
- **Block web access to the config file.** `src/Include/.htaccess` denies all (matching `src/logs/.htaccess`); `docker/nginx/default.conf` and `docker/frankenphp/Caddyfile` have matching `location ~ ^/Include` deny rules. A JSON secrets file served over HTTP is an instant credential leak.
- **Upgrade migration for existing installs.** A PHP migration under `src/mysql/upgrade.json:current.scripts` captures the legacy `$GLOBALS` variables, writes JSON, preserves the old file as `Config.php.legacy-backup`, and swaps in the new bootstrap. Idempotent.

---

## Authorization & Access Control

### Block Users With No Admin Permissions <!-- learned: 2026-04-12 -->

Users with `EditSelf=1` and **all other permissions at 0** can log in but have no functional admin access. They must NOT see the full admin UI — instead they should be redirected to a limited-access page.

Use `User::hasNoAdminPermissions()` to detect this state. The check fires in two places:

1. **`PageInit.php`** — for legacy `*.php` pages
2. **`AuthMiddleware`** — for MVC routes (`/people/`, `/admin/`, `/v2/`) and API endpoints

```php
// In PageInit.php — runs for legacy pages
if (AuthenticationManager::getCurrentUser()->hasNoAdminPermissions()) {
    RedirectUtils::redirect(SystemURLs::getRootPath() . '/external/limited-access');
}

// In AuthMiddleware (session branch) — runs for MVC pages
if ($sessionUser->hasNoAdminPermissions()) {
    if ($this->isBrowserRequest($request)) {
        return (new Response())->withStatus(302)->withHeader('Location', $rootPath . '/external/limited-access');
    }
    return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
}
```

The `/external/limited-access` page is in the external module (no auth required) and shows:
- A "Verify Family Info" button (generates time-limited token bound to user's family)
- A "Log Out" button

**Reference**: GHSA-5w59-32c8-933v / PR #8616 / Issue #237

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

Last updated: April 5, 2026
