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
- **Deprecated locations** (DO NOT USE):
  - `src/v2/routes/admin/` - REMOVED (admin routes consolidated to `/admin/system/`)
  - `src/api/routes/system/` - Legacy admin APIs (no new files here)
- Middleware order (CRITICAL - Slim 4 uses LIFO):
    1. addBodyParsingMiddleware()
    2. addRoutingMiddleware()
    3. add(CorsMiddleware)          // Last added, runs FIRST
    4. add(AuthMiddleware)          // Runs SECOND
    5. add(VersionMiddleware)       // First added, runs LAST

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

---

## Service Classes (Business Logic)

Located in `src/ChurchCRM/Service/` - handles domain logic separate from HTTP concerns.

Key Services:
- `PersonService` - Person/family operations
- `GroupService` - Group management
- `FinancialService` - Payments, pledges, funds
- `DepositService` - Deposit slip handling
- `SystemService` - System-wide operations

Example Usage:
```php
$service = $container->get('FinancialService');
$result = $service->addPayment($fam_id, $method, $amount, $date, $funds);
return $response->withJson(['data' => $result]);
```

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
- `cy.typeInQuill()` - Rich text editor input

**Credentials Configuration:**
Credentials are stored in `cypress.config.ts` and `docker/cypress.config.ts`:
```typescript
env: {
    'admin.username': 'admin',
    'admin.password': 'changeme',
    'standard.username': 'tony.wade@example.com',
    'standard.password': 'basicjoe',
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
| `src/Include/` | Utility functions, helpers, Config.php |
| `src/locale/` | i18n/translation strings |
| `src/skin/v2/` | Compiled CSS/JS from Webpack |
| `react/` | React TSX components |
| `webpack/` | Webpack entry points |
| `cypress/e2e/api/` | API test suites |
| `cypress/e2e/ui/` | UI test suites |
| `docker/` | Docker Compose configs |

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

---

Last updated: November 9, 2025

```
