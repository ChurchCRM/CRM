# Git Workflow & Development Standards

Guidelines for branching, commits, PRs, and pre-commit validation to maintain code quality.

---

## Branching Strategy

### Branch Naming Convention

All feature branches must follow a consistent naming pattern:

```
fix/issue-{NUMBER}-{description}
feature/{description}
```

**Examples:**
- `fix/issue-7698-replace-bootstrap-5-classes` - Bug fix for issue #7698
- `fix/CVE-2024-12345-sql-injection` - Security fix for CVE
- `feature/add-dark-mode-toggle` - New feature
- `fix/issue-8101-api-response-timeout` - API fix

**Rules:**
- Use kebab-case (lowercase with hyphens)
- Include issue number for bug fixes: `fix/issue-{NUMBER}`
- No spaces or underscores
- Keep descriptions concise (< 50 chars)
- One branch per issue

### Branch Lifecycle

```
master (default branch)
  ↓
git checkout master
git checkout -b fix/issue-1234-description
  ├─ [Make changes, test locally]
  ├─ [Run tests]
  ├─ [Stage & commit]
  │  git add .
  │  git commit -m "Fix issue #1234: ..."
  └─ [Push to GitHub]
     git push origin fix/issue-1234-description
       ↓
     [Create PR on GitHub]
       ↓
  [Review, approve, conflicts resolved]
       ↓
  [Merge to master]
       ↓
  [Delete feature branch]
     git branch -d fix/issue-1234-description
```

---

## Commit Message Format

### Structure

```
{action} issue #{number}: {description}
```

### Requirements

1. **Imperative mood** - Use command form, not past tense
2. **Reference issue number** - Include `#NUMBER` if fixing an issue
3. **< 72 characters** for subject line (soft limit: 80 hard limit)
4. **No file paths** - Don't mention which files changed
5. **Clear purpose** - What changed and why

### Examples

**✅ CORRECT:**
```
Fix issue #7698: Replace Bootstrap 5 classes with BS4 utilities
Add email validation to contact signup form
Fix null pointer exception in payment processing
Refactor user authorization checks for clarity
Update MailChimp sync interval configuration
```

**❌ WRONG:**
```
Fixed the bug in src/EventEditor.php                    # Not imperative, includes path
Fix issue #7698                                          # Too vague
Updates to multiple files for consistency                # Not imperative
Closes issue #6672: Renumber group property fields      # Should be "Fix", not "Closes"
Fix/issue/7698/replace bootstrap classes                # Wrong format, not imperative
```

### Multi-line Commit Messages

For complex changes, use multi-line format:

```
Fix issue #7698: Replace Bootstrap 5 classes with BS4

Bootstrap 4.6.2 is required by AdminLTE v3.2.0. Removed incompatible
Bootstrap 5 utilities like w-100, gap-*, d-grid, fw-*, fs-*. Updated
form classes to use Bootstrap 4 equivalents (e.g., w-100 → w-100).

Files changed:
- src/admin/views/*.php (3 files)
- src/finance/views/*.php (2 files)
- webpack/admin.js (styling imports)

Tests: All existing tests pass. Added new test for form responsiveness.
```

**Format:**
- Line 1: Subject (< 72 chars, imperative, reference issue)
- Line 2: Blank line
- Lines 3+: Detailed explanation (what, why, impact)
- Can include testing, migration steps, or breaking changes

---

## Pull Request Organization

### Creating a PR

**Rules:**
- One issue per PR (don't mix multiple fixes)
- All commits belong to single feature branch
- Branch created from `master`, not other branches
- All tests passing locally before pushing

**Workflow:**
```bash
# 1. Start from master
git checkout master
git pull origin master

# 2. Create feature branch
git checkout -b fix/issue-NUMBER-description

# 3. Make changes, test, commit
[edit files...]
npm run build          # Rebuild bundles
npm run test          # Run cypress tests
git add .
git commit -m "Fix issue #NUMBER: ..."

# 4. Push and create PR
git push origin fix/issue-NUMBER-description
# Open PR on GitHub with description
```

### PR Description Format

PR descriptions must follow a structured format in the GitHub UI:

```markdown
## Summary
Brief overview of what this PR accomplishes (2-3 sentences).

## Changes
- Replaced deprecated Bootstrap 5 classes with Bootstrap 4.6.2 equivalents
- Updated form styling in 3 admin pages
- Fixed responsive grid breakpoints for mobile devices
- Added tests for form validation on small screens

## Why
- Bootstrap 5 classes incompatible with AdminLTE v3.2.0
- Fixes issue #7698 (broken page layout on mobile)
- Improves accessibility on small screens

## Files Changed
- `src/admin/views/dashboard.php` - 12 insertions, 3 deletions
- `src/admin/views/settings.php` - 8 insertions, 2 deletions
- `webpack/admin.js` - 5 insertions, 1 deletion

## Testing
- ✅ All existing tests pass
- ✅ Added new responsive grid tests
- ✅ Tested on mobile (iPhone 12, Android)
- Tests: `npm run test -- --spec "cypress/e2e/ui/admin/*.spec.js"`

## Related Issues
Fixes #7698
```

**Requirements:**
- **Summary** - What does this PR do? (use imperative mood)
- **Changes** - Bulleted list of what changed (organized by feature/file)
- **Why** - Motivation and benefits
- **Files Changed** - List of modified files
- **Testing** - How to verify, test commands
- **Related Issues** - Links to related issues/PRs

### Code Review Checklist

Before marking PR ready for review, ensure:

- [ ] All changes on single feature branch
- [ ] Branch created from master (not other branches)
- [ ] All existing tests pass locally
- [ ] New tests added for new functionality
- [ ] No unrelated changes included
- [ ] Commit messages follow format
- [ ] PR description complete and clear
- [ ] No "WIP" or "TODO" comments in code
- [ ] No console.log, var_dump, or debug code left
- [ ] Code follows project style/standards

---

## Pre-commit Validation Checklist

**CRITICAL:** Before committing, verify all items:

### Code Quality
- [ ] PHP syntax validation passed (`npm run build:php`)
- [ ] No linting errors (`npm run lint`)
- [ ] Code follows project standards (read nearby files)

### Database & ORM
- [ ] Propel ORM used for all DB operations (no raw SQL)
- [ ] No `RunQuery()` calls in new code
- [ ] Dynamic IDs cast to `(int)`
- [ ] `=== null` checks, not `empty()` for objects
- [ ] Access object properties with `$obj->prop`, never `$obj['prop']`

### Asset Paths
- [ ] All CSS/image references use `SystemURLs::getRootPath()`
- [ ] No hardcoded `/skin/v2/` or relative paths

### Business Logic
- [ ] Service classes used for logic (in `src/ChurchCRM/Service/`)
- [ ] No business logic in controllers/routes
- [ ] No queries in templates
- [ ] Type casting applied to dynamic values

### File Inclusion
- [ ] Critical files use `require` (Header.php, Footer.php)
- [ ] Optional content uses `include` (plugins, supplementary)

### Frontend & UI
- [ ] Deprecated HTML attributes replaced with CSS
- [ ] Bootstrap 4.6.2 classes (NOT Bootstrap 5)
  - ✅ Correct: `col-md-6`, `w-100`, `d-flex`, `mt-3`
  - ❌ Wrong: `w-full`, `gap-4`, `d-grid`, `fw-bold`, `fs-5`
- [ ] All UI text wrapped with `i18next.t()` (JS) or `gettext()` (PHP)
- [ ] No `alert()` calls - use `window.CRM.notify()` instead

### Security & Input Validation
- [ ] `InputUtils` used for HTML escaping (not `htmlspecialchars` directly)
  - Use `escapeHTML()` for body content
  - Use `escapeAttribute()` for HTML attributes
  - Use `sanitizeText()` for plain text
  - Use `sanitizeHTML()` for rich text (Quill)
- [ ] `RedirectUtils` used for all redirects (not `header()`)
  - Use `redirect()` for relative URLs
  - Use `securityRedirect()` for access denied
  - Use `absoluteRedirect()` for absolute URLs
- [ ] `SlimUtils::renderErrorJSON()` for API errors (not exceptions)
- [ ] TLS verification enabled by default for HTTPS requests

### i18n & Translations
- [ ] **If new `gettext()` strings added**: Run `npm run locale:build`
- [ ] **If new `i18next.t()` strings added**: Run `npm run locale:build`
- [ ] After locale:build, run `npm run build` to regenerate assets
- [ ] Commit the updated `locale/terms/messages.po` file
- [ ] Use canonical UI terms (check for existing similar strings)

### Testing
- [ ] Tests pass locally before committing
- [ ] New functionality has tests (if applicable)
- [ ] Relevant Cypress tests run successfully
  - Clear logs before testing: `rm -f src/logs/$(date +%Y-%m-%d)-*.log`
  - Run tests: `npx cypress run --e2e --spec "path/to/test.spec.js"`
  - Review logs for hidden errors: `cat src/logs/$(date +%Y-%m-%d)-php.log`
- [ ] No skipped tests (`.skip` or `.only`)
- [ ] No console errors or warnings in tests

### Git & Commits
- [ ] Commit message follows format (imperative, < 72 chars)
- [ ] Branch name follows kebab-case format
- [ ] No debugging code left (`console.log`, `var_dump`, `dd()`)
- [ ] No commented-out code blocks
- [ ] No TODO/FIXME comments (remove or create issue)
- [ ] One issue per branch
- [ ] Logs cleared: `rm -f src/logs/$(date +%Y-%m-%d)-*.log`

### Documentation
- [ ] Complex logic has inline comments
- [ ] Public methods/classes documented with PHPDoc
- [ ] Breaking changes documented in commit message
- [ ] New config options documented in README or comments

---

## Agent-Specific Behaviors

### Regarding Auto-Commits

**DO NOT auto-commit changes** without explicit user request.

**Pattern:**
```
❌ WRONG - Auto-commits without asking
I'll make these changes and commit them.
[makes changes, runs git commit]

✅ CORRECT - Ask permission first
I've completed the changes and tests pass locally. Ready to commit with this message: 
"Fix issue #1234: ..."

Would you like me to proceed?
```

### When User Asks to Commit

If user explicitly requests a commit:

```bash
git add .
git commit -m "Fix issue #1234: Replace deprecated HTML with CSS"
git push origin fix/issue-1234-description
```

**Commit message should:**
- Reference the issue number
- Use imperative mood
- Describe what changed and why (not just file names)

### Pull Request Creation

When creating a PR:

1. **Let the user initialize** - Don't create PR without asking
2. **Provide description in code block** - Format as specified above
3. **Include all commits** - List the commits that will be in the PR
4. **Confirm before merging** - Get user approval before merge

---

## Pre-Commit Examples

### Example 1: Adding i18n Strings

```bash
# ❌ INCOMPLETE - Forgot locale rebuild
echo 'gettext("New Setting")' >> src/admin/views/settings.php
git add .
git commit -m "Add new setting to admin page"

# ✅ CORRECT - Rebuild locale before commit
echo 'gettext("New Admin Setting")' >> src/admin/views/settings.php
npm run locale:build      # Extract new strings
npm run build             # Regenerate assets
git add locale/terms/messages.po
git add src/admin/views/settings.php
git commit -m "Add new admin setting to UI"
```

### Example 2: Database Query

```bash
# ❌ WRONG - Uses raw SQL
$users = RunQuery("SELECT * FROM user_usr WHERE usr_role = 'admin'");

# ✅ CORRECT - Uses Perpl ORM
$users = UserQuery::create()
    ->filterByRole('admin')
    ->find();
```

### Example 3: HTML Escaping

```bash
# ❌ WRONG - No escaping
<?= $userData ?>

# ✅ CORRECT - Properly escaped
<?= InputUtils::escapeHTML($userData) ?>

# ✅ CORRECT - In form attribute
<input value="<?= InputUtils::escapeAttribute($address) ?>">
```

### Example 4: Bootstrap Classes

```bash
# ❌ WRONG - Bootstrap 5 classes
<div class="w-full gap-4 d-grid fw-bold fs-5">

# ✅ CORRECT - Bootstrap 4.6.2 classes
<div class="w-100 pr-2 d-flex font-weight-bold" style="font-size: 1.25rem;">
```

---

## Troubleshooting

### Commit Message Mistakes

**Scenario**: Created commit with wrong message

```bash
# Amend last commit (before push)
git commit --amend -m "Fix issue #1234: Correct message"
git push origin fix/issue-1234-description --force-with-lease
```

### Accidental File Commits

**Scenario**: Committed `node_modules/` or sensitive file

```bash
# Remove from history (before push)
git rm --cached node_modules
echo "node_modules/" >> .gitignore
git commit --amend
```

### Wrong Branch

**Scenario**: Made changes on master instead of feature branch

```bash
# Create new branch from current point
git branch fix/issue-1234-description
git reset --hard origin/master
git checkout fix/issue-1234-description
# Now on correct branch with changes
```

### Tests Failed After Commit

**Scenario**: Tests pass locally but fail in CI

```bash
# Clear logs and re-run locally
rm -f src/logs/$(date +%Y-%m-%d)-*.log
npm run test -- --spec "cypress/e2e/ui/path/to/test.spec.js"

# Check logs
cat src/logs/$(date +%Y-%m-%d)-php.log | tail -50

# Make fixes, amend commit
git add .
git commit --amend --no-edit
git push origin fix/issue-1234-description --force-with-lease
```

---

## Related Skills

- [Routing & Project Architecture](./routing-architecture.md) - Where to put code
- [PHP Best Practices](./php-best-practices.md) - Code standards
- [Security Best Practices](./security-best-practices.md) - Security validation before commit

---

Last updated: February 16, 2026
