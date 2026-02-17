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

## Authorization & Access Control

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

- [PHP Best Practices](./php-best-practices.md) - Service layer, logging, ORM patterns
- [Git Workflow](./git-workflow.md) - Pre-commit validation checklist
- [Configuration Management](./configuration-management.md) - Secure config handling

---

Last updated: February 16, 2026
