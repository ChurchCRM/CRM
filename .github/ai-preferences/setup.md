# AI Agent Setup Guide

## Quick Start

### GitHub Copilot
1. Open VS Code Settings: `Cmd/Ctrl + ,`
2. Search for "GitHub › Copilot: Custom Instructions"
3. Set value to: `.github/ai-preferences/preferences.yml`
4. Reload window: `Cmd/Ctrl + Shift + P` > "Reload Window"

### Claude/Cursor
1. Add `.github/ai-preferences/preferences.yml` as context file
2. Reference in conversations: "Follow the ChurchCRM AI preferences"

---

## Manual Validation

Before committing, run these checks:

```bash
# Validate specific PHP file
php -l src/EditEventAttendees.php

# Check all modified PHP files
git diff --name-only | grep '.php$' | xargs php -l

# Check for raw SQL
grep -r "SELECT\|INSERT\|UPDATE\|DELETE" src/ | grep -v "//" || true

# Check for deprecated HTML
grep -r "align=\|valign=\|nowrap\|<center>\|<font" src/ | grep -v "//" || true
```

---

## Pre-commit Checklist

- PHP syntax validation passed
- Propel ORM used for all database operations (no raw SQL)
- Asset paths use SystemURLs::getRootPath()
- Service classes used for business logic
- Type casting applied to dynamic values
- Deprecated HTML attributes replaced with CSS
- Bootstrap CSS classes applied correctly
- All UI text wrapped with i18next.t() (JavaScript) or gettext() (PHP)
- No alert() calls - use window.CRM.notify() instead
- Tests pass
- Commit message follows imperative mood (< 72 chars)
- Branch name follows kebab-case format

---

## Commit Message Format

```
<Imperative verb> <description, < 72 chars>
```

Examples:
- Fix SQL injection in EditEventAttendees
- Replace deprecated HTML attributes with Bootstrap CSS
- Add missing element ID for test selector

---

Last updated: November 2, 2025
