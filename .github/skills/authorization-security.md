# Skill: Authorization & Security

## Context
This skill covers user authorization, authentication patterns, security redirects, and XSS protection in ChurchCRM.

## User Authorization Methods

The `User` class provides permission checking methods located in `src/ChurchCRM/model/ChurchCRM/User.php`

### Role-based methods (check generic permission type)

- `isAdmin()` - Super admin access
- `isEditRecordsEnabled()` - Can edit any person/family record
- `isEditSelfEnabled()` - Can edit own record and family members
- `isEditRecords()` / `isDeleteRecords()` / `isAddRecords()` - Specific record permissions
- `isManageGroupsEnabled()` - Can manage groups
- `isFinanceEnabled()` - Can access finance module
- `isNotesEnabled()` - Can add/edit notes

### Object-level method (check permission for specific person)

**`canEditPerson(int $personId, int $personFamilyId = 0): bool`** - Check if user can edit a specific person

- Returns `true` if user has EditRecords permission, OR
- Returns `true` if user has EditSelf permission AND it's their own record or a family member
- Use this method to prevent privilege escalation (broken access control)

**Example:**

```php
$currentUser = AuthenticationManager::getCurrentUser();
if (!$currentUser->canEditPerson($iPersonID, $person->getFamId())) {
    RedirectUtils::securityRedirect('PropertyAssign');
}
```

## Authorization Redirect Pattern

**For permission checks, use this pattern:**

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

**Pattern for authorization checks:**
1. **First check**: General permission (`isEditRecordsEnabled()`, `isManageGroupsEnabled()`, etc.)
2. **Second check**: Object-level permission (use `canEditPerson()` for person records)
3. **Third**: Redirect with appropriate method (`securityRedirect()` for auth failures, `redirect()` for "not found")

## RedirectUtils (Security & Navigation)

**Use RedirectUtils for all redirects** - Located in `src/ChurchCRM/Utils/RedirectUtils.php`

### Three core methods

**1. `redirect($sRelativeURL)` - Safe relative redirects**

- Use for: Normal page navigation, error pages, "not found" pages
- Example: `RedirectUtils::redirect('v2/dashboard')` or `RedirectUtils::redirect('v2/person/not-found?id=' . $iPersonID)`
- Automatically prepends `SystemURLs::getRootPath()` and handles URL normalization

**2. `securityRedirect($missingRole)` - Permission-denied redirects**

- Use for: Access denied due to missing permissions/roles
- Example: `RedirectUtils::securityRedirect('PersonView')` when user lacks required permission
- Logs warning via `LoggerUtils` and redirects to `v2/access-denied?role=[missingRole]`
- Pass a descriptive string indicating what permission was missing

**3. `absoluteRedirect($sTargetURL)` - Absolute URL redirects**

- Use for: External URLs or already-complete internal URLs
- Example: `RedirectUtils::absoluteRedirect('https://example.com')` or `RedirectUtils::absoluteRedirect($completePath)`
- Does NOT prepend root path, used as-is

### DO NOT use

- ❌ `header('Location: ...')` directly (bypasses root path handling)
- ❌ `header('Location: ' . SystemURLs::getRootPath() . ...)` (RedirectUtils does this automatically)
- ❌ Unhandled exception throws for access denied (use security redirects)

## HTML Sanitization & XSS Protection

**Use `InputUtils` for all HTML/text handling** - Located in `src/ChurchCRM/Utils/InputUtils.php`

### Four core methods for security

**1. `sanitizeText($input)` - Plain text, removes ALL HTML tags**

- Use for: Names, descriptions, social media handles
- Example: `$person->setFirstName(InputUtils::sanitizeText($_POST['firstName']))`

**2. `sanitizeHTML($input)` - Rich text with XSS protection (HTML Purifier)**

- Use for: User-provided HTML content (event descriptions, Quill editor)
- Allows safe tags: `<a><b><i><u><h1-h6><pre><img><table><p><blockquote><div><code>` etc.
- Blocks dangerous: `<script><iframe><embed><form><style><meta>`
- Example: `$event->setDesc(InputUtils::sanitizeHTML($sEventDesc))`

**3. `escapeHTML($input)` - Output escaping for HTML body content**

- Automatically handles `stripslashes()` for magic quotes
- Use for: Displaying database/user values in HTML
- Example: `<?= InputUtils::escapeHTML($person->getFirstName()) ?>`

**4. `escapeAttribute($input)` - Output escaping for HTML attributes**

- Same security as `escapeHTML()` (uses `ENT_QUOTES`)
- Use for: Values in HTML attributes or form fields
- Example: `<input value="<?= InputUtils::escapeAttribute($address) ?>">`

**5. `sanitizeAndEscapeText($input)` - Combined plain text sanitization + output escape**

- Use for: Untrusted user input that must be plain text and escaped
- Example: `$data[$key] = InputUtils::sanitizeAndEscapeText($userSubmittedValue)`

### CRITICAL Security Rules

- ❌ NEVER use `htmlspecialchars()` or `htmlentities()` directly
- ❌ NEVER use `ENT_NOQUOTES` flag (doesn't escape quotes in attributes)
- ❌ NEVER use `stripslashes()` directly (let InputUtils handle it)
- ✅ ALWAYS use InputUtils methods for all HTML/text handling
- ✅ ALWAYS use `escapeAttribute()` for form input values
- ✅ ALWAYS use `sanitizeHTML()` for rich text editors (Quill)

## TLS/SSL Verification (Network Requests)

When making HTTPS requests (cURL, Guzzle, etc.), **always enable TLS verification by default**:

```php
// CORRECT - Secure by default with optional override for self-signed certs
public function sendRequest(string $url, bool $allowSelfSigned = false): void
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    
    if ($allowSelfSigned) {
        // Only disable for explicitly configured local network servers
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    } else {
        // Default: verify SSL certificates (secure)
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    }
    curl_exec($ch);
}

// WRONG - Disables security by default (allows MITM attacks)
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);  // ❌ Never hardcode false
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);      // ❌ Security vulnerability
```

**Guidelines:**
- **Default to secure**: Always verify TLS certificates by default
- **Make insecure behavior opt-in**: Add explicit config option (e.g., `allowSelfSigned`)
- **Document the risk**: Add setting description explaining when to use (local networks only)
- **Use case**: Self-signed certs are common in home/church networks for local servers (OpenLP, etc.)

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

When a CVE issue is confirmed fixed, provide this response:

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

## Files

**Authorization:** `src/ChurchCRM/model/ChurchCRM/User.php`
**Redirects:** `src/ChurchCRM/Utils/RedirectUtils.php`
**Input Sanitization:** `src/ChurchCRM/Utils/InputUtils.php`
**Security Policy:** `SECURITY.md`
**Issue Templates:** `.github/issue-comments/security.md`
