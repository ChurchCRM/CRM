# AI Agent Setup & Preferences Guide

This document explains how to integrate ChurchCRM's AI preferences with your development tools and workflow.

## Quick Reference

**Location:** `.github/ai-preferences/`

**Core File:** `preferences.yml` - YAML configuration used by all agents

**Related:** `.husky/pre-commit` (Git hooks), `CONTRIBUTING.md` (main guide)

---

## GitHub Copilot Setup

### Method 1: Workspace Settings (Recommended)

1. **Open VS Code Settings:**
   - Mac: `Cmd + ,`
   - Linux/Windows: `Ctrl + ,`

2. **Search for "Copilot"**

3. **Find: "GitHub › Copilot: Custom Instructions"**

4. **Set to file path:**
   ```
   .github/ai-preferences/preferences.yml
   ```

5. **Restart Copilot** (Cmd/Ctrl + Shift + P > "Reload Window")

### Method 2: Inline Comments

Reference preferences in your code:

```php
// Per .github/ai-preferences/preferences.yml:
// - Use Propel ORM, not raw SQL
// - Validate with === null, not empty()
```

---

## Claude Setup (Cursor/Manual)

### Option 1: Add File to Context

1. **In Cursor or other IDE:**
   - Add `.github/ai-preferences/preferences.yml` as a context file

2. **In any conversation:**
   - Reference the file: "Follow .github/ai-preferences/preferences.yml"

### Option 2: Copy Content

1. **Copy preferences.yml content**

2. **Paste into your system prompt or conversation starter**

3. **Reference in every new conversation:**
   ```
   Follow the ChurchCRM AI preferences from .github/ai-preferences/preferences.yml
   ```

---

## Pre-commit Hooks (Husky)

### Already Installed?

Check if Husky is initialized:

```bash
ls -la .husky/
```

Expected output:
```
.husky/
  pre-commit  (executable)
  _/
```

### Installation (if needed)

```bash
npm install
npx husky install
chmod +x .husky/pre-commit
```

### What Runs on Commit

```bash
git commit -m "Your message"
```

**The `.husky/pre-commit` hook automatically:**

1. ✅ **PHP Syntax Validation**
   ```bash
   php -l src/YourFile.php
   ```
   - Blocks commit if PHP is invalid

2. ⚠️ **Raw SQL Detection**
   ```bash
   grep -E "SELECT|INSERT|UPDATE|DELETE" src/*.php
   ```
   - Warning only (doesn't block)

3. ⚠️ **Deprecated HTML Warnings**
   ```bash
   grep -E "align=|valign=|nowrap|border=" src/*.php
   ```
   - Warning only (doesn't block)

### Manual Validation

Run checks manually before committing:

```bash
# Validate specific PHP file
php -l src/EditEventAttendees.php

# Check all modified PHP files
git diff --cached --name-only --diff-filter=ACM | grep '.php$' | xargs php -l

# Check for raw SQL
grep -r "SELECT\|INSERT\|UPDATE\|DELETE" src/ | grep -v "//"

# Check for deprecated HTML
grep -r "align=\|valign=\|nowrap\|<center>\|<font" src/ | grep -v "//"
```

---

## Pull Request Validation

### Automatic Checklist

When opening a PR, the template automatically includes:

✅ **Commit Message Standards**
- Imperative mood ("Fix", not "Fixed")
- First line under 72 characters
- No filler phrases

✅ **Code Quality Standards**
- All queries use Propel ORM
- Object validation uses `=== null`
- Business logic in Service classes
- Assets use `SystemURLs::getRootPath()`

✅ **HTML5 & CSS Standards**
- No deprecated HTML attributes
- Bootstrap CSS classes used
- CSS bundled via webpack

✅ **Testing & Validation**
- PHP files pass syntax validation
- Cypress tests: element IDs maintained
- No broken test selectors

### Manual Review

Before pushing, verify:

```bash
# 1. PHP syntax on all modified files
git diff --name-only | grep '.php$' | xargs php -l

# 2. Commit message format
git log -1 --format=%B

# 3. No raw SQL
git diff --cached | grep -E "^\+.*SELECT|^\+.*INSERT"

# 4. No deprecated HTML
git diff --cached | grep -E "align=|valign=|nowrap"
```

---

## Commit Message Standards

### Format

```
<type>(<scope>): <subject>

<body (optional)>

<footer (optional)>
```

### Examples

✅ **CORRECT** - Imperative, under 72 chars
```
Fix SQL injection in EditEventAttendees

Changed empty($event) to $event === null to properly
validate Propel ORM objects. This prevents unexpected
redirects in the Edit Event Attendees page.
```

✅ **CORRECT** - Concise
```
Replace deprecated HTML attributes with Bootstrap CSS
```

❌ **WRONG** - Past tense
```
Fixed SQL injection in EditEventAttendees
```

❌ **WRONG** - Too long
```
This commit fixes the SQL injection vulnerability that was found in the EditEventAttendees.php file by updating the validation logic
```

---

## Code Quality Checklist

**Before every commit:**

- [ ] PHP syntax passes: `php -l src/YourFile.php`
- [ ] No raw SQL in your changes
- [ ] Propel ORM used for all database queries
- [ ] Objects validated with `=== null` (not `empty()`)
- [ ] Asset paths use `SystemURLs::getRootPath()`
- [ ] Service classes used for business logic
- [ ] Deprecated HTML attributes replaced with CSS
- [ ] Bootstrap CSS classes applied
- [ ] Tests pass (if available)
- [ ] Commit message follows standards
- [ ] Branch name is kebab-case

---

## Validation Examples

### Example 1: Database Query

❌ **WRONG** - Raw SQL
```php
$sql = "SELECT * FROM events WHERE eventid = " . $eventId;
$result = RunQuery($sql);
```

✅ **CORRECT** - Propel ORM
```php
$event = EventQuery::create()
  ->findById($eventId);
```

### Example 2: Object Validation

❌ **WRONG** - Unreliable for Propel objects
```php
if (empty($event)) {
  RedirectUtils::redirect('ListEvents.php');
}
```

✅ **CORRECT** - Explicit null check
```php
if ($event === null) {
  RedirectUtils::redirect('ListEvents.php');
}
```

### Example 3: Asset Paths

❌ **WRONG** - Breaks in subdirectories
```php
<link rel="stylesheet" href="/skin/v2/churchcrm.min.css">
```

✅ **CORRECT** - Uses SystemURLs
```php
use ChurchCRM\dto\SystemURLs;
<link rel="stylesheet" href="<?= SystemURLs::getRootPath() ?>/skin/v2/churchcrm.min.css">
```

### Example 4: HTML Attributes

❌ **WRONG** - Deprecated attributes
```html
<div align="center" valign="top" nowrap>Content</div>
```

✅ **CORRECT** - Bootstrap CSS
```html
<div class="text-center align-top text-nowrap">Content</div>
```

---

## Testing Commands

### Full Validation Suite

```bash
#!/bin/bash
# Run all checks before committing

echo "🔍 Validating PHP syntax..."
git diff --name-only | grep '.php$' | xargs php -l || exit 1

echo "🔍 Checking for raw SQL..."
git diff --cached | grep -E "^\+.*(SELECT|INSERT|UPDATE|DELETE)" | grep -v "//" && echo "⚠️ Raw SQL detected!" || echo "✅ No raw SQL"

echo "🔍 Checking for deprecated HTML..."
git diff --cached | grep -E "align=|valign=|nowrap" && echo "⚠️ Deprecated HTML detected!" || echo "✅ No deprecated HTML"

echo "🔍 Validating commit message..."
MESSAGE=$(git diff --cached HEAD -- 2>/dev/null | head -1)
echo "Message length check..."

echo "✅ All checks passed!"
```

### Run Tests

```bash
# Cypress tests
npm run test:ui

# Individual test file
npm run test -- --spec "cypress/e2e/path/to/test.cy.js"
```

---

## Troubleshooting

### Hook Not Running

**Problem:** Pre-commit hook doesn't execute

**Solution:**
```bash
# Reinstall Husky
npm install
npx husky install

# Make hook executable
chmod +x .husky/pre-commit

# Verify
ls -la .husky/pre-commit
```

### PHP Lint Errors

**Problem:** `php -l` shows syntax errors

**Solution:**
```bash
# Check specific file
php -l src/YourFile.php

# View error details
php -d display_errors=1 src/YourFile.php
```

### Commit Still Blocked

**Problem:** Commit blocked even though fixes are in place

**Solution:**
```bash
# Stage changes
git add .

# Try commit again
git commit -m "Your message"

# If still blocked, bypass hooks (not recommended)
git commit -m "Your message" --no-verify
```

---

## Integration with CI/CD

GitHub Actions can enforce these standards automatically:

**Future enhancement:** `.github/workflows/code-quality.yml`

```yaml
name: Code Quality
on: [push, pull_request]
jobs:
  validate:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - name: Validate PHP Syntax
        run: |
          git diff origin/master --name-only | grep '.php$' | xargs php -l
      - name: Check for Raw SQL
        run: |
          ! git diff origin/master | grep -E "^\+.*(SELECT|INSERT|UPDATE)"
      - name: Check Commit Messages
        run: |
          # Validate imperative mood and length
```

---

## Questions?

1. **Need to check preferences?** → See `.github/ai-preferences/preferences.yml`
2. **Want to update standards?** → Edit `preferences.yml` and update `CONTRIBUTING.md`
3. **Found an issue?** → Report in PR description

---

Last Updated: 2025-10-25
