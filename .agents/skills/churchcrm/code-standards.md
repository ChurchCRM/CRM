---
title: "Code Standards"
intent: "Coding conventions, PR checklist, and pre-commit guidance"
tags: ["standards","code-quality","pr"]
prereqs: ["php-best-practices.md"]
complexity: "beginner"
---

# Skill: Code Standards & Quality

## Context
This skill covers PHP 8.4+ requirements, coding standards, performance patterns, and quality guidelines for ChurchCRM.

## PHP 8.4+ Requirements (MANDATORY)

All code must be compatible with PHP 8.4+ and avoid deprecated patterns.

### Key Standards

- **Explicit nullable parameters**: `?int $param = null` not `int $param = null`
- **Dynamic properties**: Need attribute `#[\AllowDynamicProperties]`
- **Date formatting**: Use IntlDateFormatter instead of strftime
- **Use imports, never inline fully-qualified class names**: Add `use` statements at top of file
- **Explicit global namespace**: `\MakeFYString($id)` in namespaced code
- **Version checks**: `version_compare(phpversion(), '8.4.0', '<')`
- **Public constants**: For shared values `public const PHOTO_WIDTH = 200;`

### Import Statement Rules

**ALWAYS use `use` statements at the top of files instead of inline fully-qualified class names:**

```php
// ✅ CORRECT
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

// ❌ WRONG - Inline fully-qualified names
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

## Database Access Standards

```php
// ✅ CORRECT - Propel ORM
$event = EventQuery::create()->findById((int)$eventId);
if ($event === null) { 
    // Handle not found
}

// ❌ WRONG - Raw SQL
$result = RunQuery("SELECT * FROM events WHERE eventid = ?", $eventId);
$event['eventName'];  // TypeError: Cannot access offset on object
```

**Rules:**
- ALWAYS use Perpl ORM Query classes
- NEVER use raw SQL or RunQuery()
- Cast dynamic IDs to (int)
- Check `=== null` not `empty()` for objects
- Access properties as objects: `$obj->prop`, never `$obj['prop']`

## Global Functions from Namespaced Code

```php
// ✅ CORRECT
namespace ChurchCRM\Service;

class MyService {
    public function test() {
        \MakeFYString($id);  // Backslash prefix for global function
    }
}

// ❌ WRONG
MakeFYString($id);  // PHP Error: undefined function in namespace
```

## File Inclusion (require vs include)

```php
// ✅ CORRECT - Use require for critical layout files
require SystemURLs::getDocumentRoot() . '/Include/Header.php';
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';

// ❌ WRONG - include allows missing critical files
include SystemURLs::getDocumentRoot() . '/Include/Header.php';  // Silent failure
```

**Guidelines:**
- **Use `require`** for critical files: Header.php, Footer.php, core utilities
- **Use `include`** for optional content: plugins, supplementary files that gracefully degrade
- **Why**: `require` fails loudly (fatal error), `include` fails silently (warning)
- **Admin views** (`src/admin/views/*.php`): ALL must use `require` for Header/Footer

## Null Safety

```php
// ✅ CORRECT
echo $notification?->title ?? 'No Title';

// ❌ WRONG
echo $notification->title;  // TypeError if null
```

## Email Handling in APIs

```php
// ✅ CORRECT - Log but don't crash
if (!mail($to, $subject, $body)) {
    error_log("Email failed: " . $to);
}
return $response->withJson(['data' => $result]);

// ❌ WRONG - Throws exception
if (!mail($to, $subject, $body)) {
    throw new Exception("Email failed");  // Returns 500
}
```

## Algorithm Performance (Avoid O(N*M))

When filtering or matching items between two collections, **use hash-based lookups instead of nested loops:**

```php
// ✅ CORRECT - O(N+M) using set membership
$localIds = [];
foreach ($localPeople as $person) {
    $localIds[$person->getId()] = true;  // Build hash map
}
$remoteOnly = [];
foreach ($remotePeople as $remotePerson) {
    if (!isset($localIds[$remotePerson['id']])) {  // O(1) lookup
        $remoteOnly[] = $remotePerson;
    }
}

// ✅ CORRECT - Using array_flip for sets
$localIdSet = array_flip(array_column($localPeople, 'id'));  // O(N)
$remoteOnly = array_filter($remotePeople, function($p) use ($localIdSet) {
    return !isset($localIdSet[$p['id']]);  // O(1) per item
});

// ❌ WRONG - O(N*M) nested filter (scales poorly)
$remoteOnly = array_filter($remotePeople, function($remotePerson) use ($localPeople) {
    foreach ($localPeople as $localPerson) {  // ❌ O(M) per remote person
        if ($localPerson->getId() === $remotePerson['id']) {
            return false;
        }
    }
    return true;
});
```

**Guidelines:**
- **Build lookup structures first**: Use `array_flip()`, associative arrays, or `isset()` for O(1) membership tests
- **Avoid `in_array()` in loops**: `in_array()` is O(N); use `isset()` on flipped array instead
- **Scale consideration**: 1000 local × 1000 remote = 1M comparisons with O(N*M), only 2K with O(N+M)

## Logging Standards

**Always use LoggerUtils for business logic operations:**

```php
use ChurchCRM\Utils\LoggerUtils;

$logger = LoggerUtils::getAppLogger();
$logger->debug('Operation starting', ['context' => $value]);
$logger->info('Operation succeeded', ['result' => $value]);
$logger->error('Operation failed', ['error' => $e->getMessage()]);
```

**Log levels:**
- `debug` - Development info, detailed execution flow
- `info` - Business events, successful operations
- `warning` - Issues that don't stop execution
- `error` - Failures, exceptions

**Always include context** as second parameter array for meaningful logs.

## File Operations (Git)

### Moving/Renaming Files

**Always use `git mv` to preserve history:**

```bash
# ✅ CORRECT
git mv old/path/file.php new/path/file.php

# ❌ WRONG - Loses history
mv old/path/file.php new/path/file.php
git add new/path/file.php
```

### Creating/Deleting Files

- **Creating**: Use `create_file` tool for new files
- **Deleting**: Use `rm` command via `run_in_terminal` for simple deletions
- Git will track file moves properly when using `git mv`

## Commit & PR Standards

### Commit Message Format

- **Imperative mood**, < 72 chars for subject line
- **Examples**: 
  - "Fix validation in Checkin form"
  - "Replace deprecated HTML attributes with Bootstrap CSS"
  - "Add missing element ID for test selector"
- **Wrong**: "Fixed the bug in src/EventEditor.php" (not imperative, includes file paths)
- **Include issue number**: "Fix issue #7698: Replace Bootstrap 5 classes with BS4"

### PR Organization

- Create feature branches: `fix/issue-NUMBER-description` or `feature/description`
- **One issue per branch** - do not mix fixes for different issues
- Keep commits small and focused
- Each PR addresses one specific bug or feature
- Related but separate concerns get separate branches
- Test each branch independently before creating PR

### PR Descriptions

- **ALWAYS output PR description in a Markdown code block** when asked to create a PR
- Format with clear sections:
  - **Summary**: Brief overview of changes
  - **Changes**: Bulleted list organized by feature/area
  - **Why**: Motivation and benefits
  - **Files Changed**: List of modified/added/deleted files
- Include all commits in the branch
- Use imperative mood for change descriptions

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
- [ ] Use InputUtils for HTML escaping (not htmlspecialchars directly)
- [ ] Use RedirectUtils for redirects (not manual header/withHeader)
- [ ] Use SlimUtils::renderErrorJSON for API errors (not throw exceptions)
- [ ] TLS verification enabled by default for HTTPS requests
- [ ] No O(N*M) algorithms - use hash-based lookups for set membership
- [ ] **If new gettext() strings added**: Run `npm run locale:build` to extract terms
- [ ] Tests pass (if available) - run relevant tests before committing
- [ ] Commit message follows imperative mood (< 72 chars, no file paths)
- [ ] Branch name follows kebab-case format
- [ ] Logs cleared before testing: `rm -f src/logs/$(date +%Y-%m-%d)-*.log`

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

```bash
gh pr view <number> --json reviews           # Get review comments and status
gh pr view <number> --json latestReviews     # Get most recent reviews with full body
gh pr view <number> --json comments          # Get top-level PR comments
gh pr view <number> --comments               # Human-readable view with all comments
```

**Example workflow when user asks to review a PR:**
1. Use `gh pr view 7774 --json latestReviews` to fetch reviewer comments
2. Check the review state (`COMMENTED`, `APPROVED`, `CHANGES_REQUESTED`)
3. Parse the review body for any requested changes or issues
4. If changes are needed, implement them based on feedback
5. Run tests to verify all changes work
6. Report back with summary of changes made (or "no changes needed" if already good)

**DO NOT** use `github-pull-request_openPullRequest` or `github-pull-request_issue_fetch` tools for PR comments - these return incomplete comment data. Always use `gh` command for full review content.

### Resolving Review Threads After Addressing Comments

After pushing fixes for PR review comments, **always resolve the threads** using the GitHub GraphQL API:

```bash
# 1. Get inline comment thread IDs via GraphQL
gh api graphql -f query='
{
  repository(owner: "OWNER", name: "REPO") {
    pullRequest(number: NUMBER) {
      reviewThreads(first: 20) {
        nodes {
          id
          isResolved
          comments(first: 1) {
            nodes { databaseId body }
          }
        }
      }
    }
  }
}' --jq '.data.repository.pullRequest.reviewThreads.nodes[] | {id, resolved: .isResolved, preview: (.comments.nodes[0].body | .[0:60])}'

# 2. Resolve each thread (replace THREAD_ID with each id from step 1)
gh api graphql -f query='mutation { resolveReviewThread(input: {threadId: "THREAD_ID"}) { thread { id isResolved } } }'

# 3. Post a follow-up PR comment summarising what was addressed
gh pr comment NUMBER --body "## Follow-up changes pushed\n\n..."
```

**Workflow when user says "fix PR comments then push":**
1. Fetch inline comments: `gh api repos/OWNER/REPO/pulls/NUMBER/comments`
2. Implement all requested fixes
3. Commit + push
4. Get thread IDs via GraphQL (step 1 above)
5. Resolve each thread (step 2 above)
6. Post a summary comment on the PR (step 3 above)

## Files

**Logger:** `src/ChurchCRM/Utils/LoggerUtils.php`
**Service Container:** `src/ChurchCRM/ServiceContainerBuilder.php`
**Logs:** `src/logs/`
