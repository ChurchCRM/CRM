---
title: "Authorization & Security"
intent: "Authorization patterns, RedirectUtils, input sanitization, and security guidance"
tags: ["security","auth","redirects","xss"]
prereqs: ["php-best-practices.md"]
complexity: "intermediate"
---

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
- `isNotesEnabled()` - Can view/edit notes (see detailed semantics below)

## Admin Purity — `isAdmin()` Implies All Permissions <!-- learned: 2026-07-06 -->

When `isAdmin()` returns `true`, **every** permission-check method also returns `true`, regardless of the DB column values. This is code-enforced in `User.php`:

```php
// Confirmed pattern in every isXxxEnabled() method:
public function isNotesEnabled(): bool
{
    if ($this->isEditSelfExclusive()) { return false; }
    return $this->isAdmin() || $this->isNotes(); // isAdmin() short-circuits
}
// Same for isEditRecordsEnabled(), isFinanceEnabled(), isManageGroupsEnabled(), etc.
```

**Exception — private-note content**: even when `isAdmin()=true`, admins:
- CANNOT view or edit the content of other users' private notes via the API (`canReadPrivateNotes()` → `isAdmin()`, BUT `Note::isVisibleTo()` gates the *content* read — the TimelineService renders a `[Private Note]` placeholder instead of the content for notes the admin did not write).
- CAN see that a private note exists (the placeholder appears in the timeline).
- CAN delete any note — the delete handler checks `isAdmin() || isAuthor`. Admins pass both `NotesRoleAuthMiddleware` (because `isAdmin() → isNotesEnabled()=true`) and the inner author/admin check.

## Notes Permission (`isNotesEnabled()`) Semantics <!-- learned: 2026-07-06 -->

`isNotesEnabled()` returns `true` for admin users (admin purity) and for users where `usr_Notes=1`. What it grants:

| Action | Notes=1 (non-admin) | Admin |
|--------|--------------------|--------------|
| View public notes by others | ✅ | ✅ |
| Edit public notes | ✅ | ✅ |
| View own private notes | ✅ (author check via `getEnteredBy()`) | ✅ |
| Edit own private notes | ✅ | ✅ |
| View private notes by other users | ❌ | ❌ (placeholder only) |
| Edit private notes by other users | ❌ | ✅ (admin bypass in handler) |
| Delete any note | ❌ (author only) | ✅ |
| Access Note API routes at all | ✅ | ✅ |

**Without Notes permission**: users cannot access any Note API routes — all Note routes are wrapped with `NotesRoleAuthMiddleware` which calls `isNotesEnabled()`. A user without Notes cannot see any notes at all, including public ones.

## Zero-Permission User (All Flags = 0) <!-- learned: 2026-07-06 -->

A user where `usr_Admin=0`, `usr_EditSelf=0`, and all other permission columns are 0 passes `hasNoAdminPermissions()` returning `true`:

```php
public function hasNoAdminPermissions(): bool
{
    if ($this->isAdmin()) { return false; }     // admin → has permissions
    if ($this->isEditSelf()) { return true; }  // pure EditSelf → no admin perms
    return !$this->isAddRecords() && ...;       // all-zero → no admin perms
}
```

`AuthMiddleware` redirects such users to `/external/limited-access`. They can log in but gain NO CRM access — all internal pages and API routes return 403 or redirect.

## Self-Service User Scope (EditSelf-Only) <!-- learned: 2026-07-06 -->

A user with `usr_EditSelf=1` and all other permission columns `= 0` ("EditSelf-only") is gated by the entry-gate rule:
- `hasNoAdminPermissions()` returns `true` for EditSelf-only users → redirected to `/external/limited-access` on every internal CRM page
- Their only self-service surface is the token-scoped `/external/verify/{token}` flow
- `canViewFamily()` is true only for their own family; `canEditPerson()` scoped to their own family members
- **`usr_EditSelf` defaults to `0` on new user creation** (must be explicitly granted in the UI — `UserEditor.php`)

**EditSelf is NOT a default right**. A user without explicit `usr_EditSelf=1` does NOT get the "Verify Family Info" link on the limited-access page, even if they belong to a family (enforced in `src/external/routes/system.php` via `isEditSelfEnabled()` check before issuing the verify token).

## Family/Person View Access <!-- learned: 2026-07-06 -->

- **Any user where `hasNoAdminPermissions()` returns `false`** (i.e. they have at least one module permission OR they are admin) can view ALL families and persons in the CRM.
- **EditSelf-only users** are restricted to their own family via `canViewFamily(int $familyId)` — returns `true` only when `$familyId === $person->getFamId()`.
- **Zero-permission users** (all flags 0) are redirected away from CRM pages before any family/person check runs.

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

**`canViewFamily(int $familyId): bool`** - Check if user can access a specific family

- `true` for Admin/EditRecords; EditSelf users restricted to their own family.
- Enforced centrally in `FamilyMiddleware::process()` (overrides the base to run after entity load), so every route using `FamilyMiddleware` is scoped automatically.

## Low-Sensitivity Family Read Endpoints — FamilyReadMiddleware <!-- learned: 2026-07-02 -->

Not all family API routes need the `canViewFamily()` scope restriction. Avatar, nav (prev/next IDs),
and photo GET are considered non-sensitive metadata; they are accessible to any authenticated user
who passes `hasNoAdminPermissions()` in `AuthMiddleware` (i.e. has at least Notes, EditRecords, etc.).

Use a **separate `FamilyReadMiddleware` class** (not a constructor parameter on `FamilyMiddleware`)
for these endpoints. The entity is still loaded (404 on missing family), but authorisation uses
`canReadFamily()` instead of `canViewFamily()`. This mirrors the person pattern where avatar/photo
GET routes are registered without `PersonMiddleware`.

**Anti-pattern:** Do NOT add a constructor parameter to `FamilyMiddleware` (e.g. `new FamilyMiddleware(false)`).
Slim 4 resolves `FamilyMiddleware::class` by calling `new FamilyMiddleware()` at request time; a
non-injected constructor parameter causes all family routes to return 500.

```php
// Low-sensitivity group — read baseline, no EditSelf scope restriction
$app->group('/family/{familyId:[0-9]+}', function (RouteCollectorProxy $group): void {
    $group->get('/avatar', ...);
    $group->get('/nav', ...);
    $group->get('/photo', ...)->add(new Cache(...));
})->add(FamilyReadMiddleware::class);

// Sensitive group — canViewFamily() scope enforced (GHSA-jjcj-h3cm-p7x7)
$app->group('/family/{familyId:[0-9]+}', function (RouteCollectorProxy $group): void {
    $group->get('', ...);
    $group->get('/geolocation', ...);
    // write routes ...
})->add(FamilyMiddleware::class);
```

Endpoints that remain fully scoped (EditSelf restricted to own family):
- `GET /family/{id}` — full profile (address/phone/email)
- `GET /family/{id}/geolocation` — lat/lng of family address
- Notes, timeline, write operations

## Two-Layer EditSelf Authorization (entry gate + object-level) <!-- learned: 2026-06-14 -->

EditSelf scoping is enforced in **two layers** — know which one applies:

1. **Coarse entry gate (`hasNoAdminPermissions()`)** — blocks users with *none* of {AddRecords, EditRecords, DeleteRecords, MenuOptions, ManageGroups, Finance, Notes}. **EditSelf is NOT counted**, so a **pure EditSelf-only user is bounced to `/external/limited-access` everywhere internal**. This runs in `AuthMiddleware` (all `/api`, `/kiosk`, `/plugins`), `PageInit.php` (legacy pages), and — because `MvcAppFactory::create()` *always* adds `AuthMiddleware` — every MVC app (`/v2`, `/people`, `/event`, …) even when no `roleMiddleware` is set. Their only self-service surface is the token-scoped `/external/verify/{token}` flow (family derived from the token's `referenceId` — no IDOR).

2. **Object-level checks** — only matter for the **EditSelf + module combo** (e.g. EditSelf+Notes) that *passes* the entry gate. Here `canEditPerson()` / `canViewFamily()` keep them scoped to their own family.

### Gotcha: `PersonMiddleware` does NOT enforce object-level scope

Unlike `FamilyMiddleware`, `PersonMiddleware` (and the other `AbstractEntityMiddleware` subclasses) only loads the entity — **no `canEditPerson()` check**. Do **not** add a blanket check to `PersonMiddleware`: it is shared with ManageGroups member routes (`new PersonMiddleware('userID')`), where `canEditPerson()` would wrongly 403 group admins. Put the scope check **in the handler** for person-DATA routes instead:

```php
// person notes / sensitive person-scoped routes — handler-level scope check
$person = $request->getAttribute('person');
if (!$currentUser->canEditPerson((int) $person->getId(), (int) $person->getFamId())) {
    return SlimUtils::renderErrorJSON($response, gettext('Access denied'), [], 403);
}
```

Routes that already do this: `GET /api/person/{id}` (people-person.php), `people/view.php`, and person notes GET/POST (notes.php). Family equivalents are covered centrally by `FamilyMiddleware`.

## MVC Module Middleware: View vs Add Split <!-- learned: 2026-04-09 -->

When wrapping an MVC module with role middleware via `MvcAppFactory::create([... 'roleMiddleware' => ...])`, choose **the lowest permission tier the module's *read* routes need**, then add the higher tier per write route.

```php
// ❌ WRONG — module-level AddEvents middleware 403s the dashboard, calendar,
// and read-only check-in pages for users who only have View permission.
$app = MvcAppFactory::create('/event', [
    'roleMiddleware' => AddEventsRoleAuthMiddleware::class,
]);

// ✅ CORRECT — module-level View gate, Add gate per write route
$app = MvcAppFactory::create('/event', [
    'roleMiddleware' => ViewEventsRoleAuthMiddleware::class,
]);

$app->get('/dashboard', $listEventsHandler);                     // View only
$app->post('/editor', $saveEventHandler)
    ->add(new AddEventsRoleAuthMiddleware());                    // requires Add
$app->post('/types/{id}', $saveTypeHandler)
    ->add(new AddEventsRoleAuthMiddleware());                    // requires Add
```

**Why it matters**: menu items linking to read-only routes (`Calendar`, `Check-in`, `Events Dashboard`) are visible to all logged-in users. If the module middleware demands the elevated write permission, every click 403s — defeats the menu and looks like a regression.

**Defense in depth**: it's still fine for `AddEventsRoleAuthMiddleware` to additionally enforce the system-wide feature flag (`bEnabledEvents`), so writes are blocked even if a route is missing the per-route middleware.

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

## Kiosk Device Routes — Cookie + Roster Check <!-- learned: 2026-04-09 -->

Routes under `/kiosk/device/*` are NOT user-authenticated — they identify the caller via a kiosk device cookie, not a `User` session. Every device route must:

1. Validate the kiosk cookie via `$getKioskFromCookie()` and 401 if missing
2. **For routes that take a `PersonId` argument**: verify that person belongs to the kiosk's active assignment roster, otherwise the endpoint becomes a person-id enumeration oracle disclosing names and photo flags

```php
$group->get('/activeClassMember/{PersonId}/family', function (Request $req, Response $res, array $args) use ($getKioskFromCookie): Response {
    $kiosk = $getKioskFromCookie();
    if ($kiosk === null) {
        return SlimUtils::renderErrorJSON($res, gettext('Kiosk device not found'), [], 401);
    }

    $personId = InputUtils::filterInt($args['PersonId'] ?? 0);
    if ($personId <= 0) {
        return SlimUtils::renderErrorJSON($res, gettext('Invalid person ID'), [], 400);
    }

    // Roster membership check — prevents enumeration outside assigned group
    $assignment = $kiosk->getActiveAssignment();
    if ($assignment === null) {
        return SlimUtils::renderErrorJSON($res, gettext('No active assignment'), [], 403);
    }
    $rosterIds = array_map('intval', array_column($assignment->getActiveGroupMembers(), 'PersonId'));
    if (!in_array($personId, $rosterIds, true)) {
        return SlimUtils::renderErrorJSON($res, gettext('Person not in active class roster'), [], 403);
    }

    // ... safe to fetch + return person data
});
```

**Related**: `Event::checkInPerson()` / `Event::checkOutPerson()` are called from kiosk routes with no `User` session. Any code path they touch must guard `AuthenticationManager::getCurrentUser()` with `isUserAuthenticated()` first — see `Event::addTimelineNote()` in `src/ChurchCRM/model/ChurchCRM/Event.php` for the fallback pattern.

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

## Related Skills

- [Security Best Practices](./security-best-practices.md) - HTML sanitization, XSS protection, TLS/SSL verification, API error handling, CVE vulnerability handling
- [Code Standards](./code-standards.md) - General code quality and standards
- [API Development](./api-development.md) - API error handling and security response patterns

## PR Permission Audit — Required Before Merge <!-- learned: 2026-07-06 -->

Any PR that touches the files listed below **must be reviewed against the confirmed permission model** before it can be recommended for merge. This is a hard gate, not a suggestion.

### Trigger files — any change to these requires a permission audit

| File | Why |
|------|-----|
| `src/ChurchCRM/model/ChurchCRM/User.php` | Admin purity shortcut, `hasNoAdminPermissions()`, all `isXxxEnabled()` methods |
| `src/ChurchCRM/Slim/Middleware/AuthMiddleware.php` | Entry gate for all internal CRM and API routes |
| `src/ChurchCRM/Slim/Middleware/Api/FamilyMiddleware.php` | `canViewFamily()` — GHSA-jjcj-h3cm-p7x7 scope fix |
| `src/ChurchCRM/Slim/Middleware/Api/FamilyReadMiddleware.php` | `canReadFamily()` — low-sensitivity read baseline |
| `src/ChurchCRM/Slim/AbstractEntityMiddleware.php` | `postEntityLoad()` hook — base class for all entity middleware |
| `src/Include/PageInit.php` | Legacy page permission gate |
| `src/api/routes/people/notes.php` | Notes visibility and privacy rules |
| `src/external/routes/system.php` | verifyFamily token — EditSelf gate |
| `src/UserEditor.php` | New user permission defaults |
| `cypress/data/seed.sql` | Test user permission flags must match intended model |

### Checklist — verify all rules still hold after the change

**1. Admin purity**
- [ ] `isAdmin()=true` still causes every `isXxxEnabled()` method to return `true` regardless of DB value
- [ ] Admin still cannot view or edit the *content* of another user's private note (`Note::isVisible()` still restricts)
- [ ] Admin can still see the `[Private Note]` placeholder in TimelineService
- [ ] Admin can still delete any note (admin purity passes `NotesRoleAuthMiddleware`; DELETE handler checks `isAdmin() || isAuthor`)

**2. EditSelf exclusivity**
- [ ] `hasNoAdminPermissions()` still returns `true` for any user with `isEditSelf()=true` (EditSelf is excluded from the 7-flag check deliberately)
- [ ] EditSelf-only users are still redirected to `/external/limited-access` by `AuthMiddleware`
- [ ] EditSelf-only users still cannot reach any internal CRM or API route
- [ ] `usr_EditSelf` still defaults to `0` for new users in `UserEditor.php`
- [ ] `isEditSelfEnabled()` guard is still required before issuing a verifyFamily token in `system.php`

**3. Notes permission (`isNotesEnabled()`)**
- [ ] `NotesRoleAuthMiddleware` still gates all Note API routes (list, create, edit, delete)
- [ ] `Note::isVisible($userId)` still restricts private note content to the creator (`getEnteredBy() === $userId`)
- [ ] Users without Notes permission still have zero access to notes created by others
- [ ] A user can still view and edit their own private notes regardless of whether Notes is enabled (author exception)

**4. Family / person view access**
- [ ] Any user with ≥1 module permission (i.e., `hasNoAdminPermissions()=false`) can view ALL families and persons
- [ ] `canViewFamily()` still restricts EditSelf-only users to their own family and family members
- [ ] Low-sensitivity family endpoints (avatar, photo, nav) still use `FamilyReadMiddleware::class`
- [ ] Sensitive / write family endpoints still use `FamilyMiddleware::class` (`canViewFamily()`)

**5. Seed data integrity (Cypress)**
- [ ] Every test user's `user_usr` row permission flags match the stated intent in comments/spec headers
- [ ] Zero-permission user (`noperm.user`, ID 901): all 8 flags = 0 including `usr_EditSelf=0`
- [ ] EditSelf-only user: `usr_EditSelf=1`, all other flags = 0
- [ ] EditSelf+Notes sentinel (`lena.black`, ID 100): `usr_EditSelf=1`, `usr_Notes=1`, all others = 0
