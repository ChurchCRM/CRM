# AI Preferences & Setup Guide

This directory contains all AI agent configuration and documentation for ChurchCRM development.

## 📁 Files

### `preferences.yml`
**Core AI agent configuration** - Standards and rules for all coding agents.

**Used by:**
- GitHub Copilot (workspace settings)
- Claude (context file)

**What it covers:**
- Communication style (direct, action-first)
- Commit & PR standards (imperative, < 72 chars)
- Code quality (Propel ORM, Service classes)
- HTML5 & CSS standards (Bootstrap only)
- Database rules (ORM mandatory, no raw SQL)
- Asset paths (SystemURLs::getRootPath())
- Testing & documentation policies
- Branch naming conventions

### `setup.md`
**Detailed setup and adoption guide** - How to integrate these preferences with your tools and workflow.

**Covers:**
- GitHub Copilot configuration
- Claude/Cursor setup
- Pre-commit hook installation
- PR template usage
- Validation examples

---

## 🚀 Quick Start

### For Claude/Copilot Users
1. Reference `preferences.yml` in your conversation
2. Use as context: "Follow the ChurchCRM AI preferences in `.github/ai-preferences/preferences.yml`"
3. Verify output against the pre-commit checklist

### For Developers
1. Hooks run automatically on `git commit`
2. See `setup.md` for manual validation commands
3. Check PR template for required validations

---

## 📋 Pre-commit Checklist

**Before every commit, verify:**
- ✅ PHP syntax validation passed
- ✅ Propel ORM used for all database operations
- ✅ Asset paths use `SystemURLs::getRootPath()`
- ✅ Service classes used for business logic
- ✅ Deprecated HTML attributes replaced with CSS
- ✅ Bootstrap CSS classes applied correctly
- ✅ Tests pass (if available)
- ✅ Commit message follows imperative mood
- ✅ Branch name follows kebab-case format

---

## 🔧 Enforcement Mechanisms

1. **Pull Request Template** (`.github/pull_request_template.md`)
   - Includes AI preferences validation section
   - Reminds reviewers of standards

2. **Code Quality Template** (`.github/ISSUE_TEMPLATE/code-quality-check.md`)
   - Checklist for code reviews

3. **Contributing Guide** (`CONTRIBUTING.md`)
   - Links to these preferences
   - Integration guidance

---

## 📚 Related Files

- `CONTRIBUTING.md` - Development workflow (links to this folder)
- `.github/pull_request_template.md` - PR requirements
- `.github/ISSUE_TEMPLATE/code-quality-check.md` - Review checklist

---

## 🔑 Key Standards

### Database (Mandatory)
```php
// ✅ CORRECT - Propel ORM
$events = EventQuery::create()->findById($eventId);

// ❌ WRONG - Raw SQL
$events = query("SELECT * FROM events WHERE eventid = ?", $eventId);
```

### Object Validation
```php
// ✅ CORRECT
if ($event === null) {
  RedirectUtils::redirect('ListEvents.php');
}

// ❌ WRONG
if (empty($event)) {  // Unreliable with Propel objects
  RedirectUtils::redirect('ListEvents.php');
}
```

### HTML Attributes
```php
// ✅ CORRECT - Bootstrap CSS
<div class="text-center align-top">Content</div>

// ❌ WRONG - Deprecated attributes
<div align="center" valign="top">Content</div>
```

---

## ❓ Questions?

1. **Standards reference?** → See `preferences.yml`
2. **Tool setup?** → See `setup.md`
3. **Development workflow?** → See `CONTRIBUTING.md`
4. **Specific code patterns?** → Check `preferences.yml` special instructions

---

## 📈 Continuous Improvement

This framework evolves with the project:
- Report issues or suggest improvements in PRs
- Update `preferences.yml` as standards change
- Keep `setup.md` synchronized with new tools/processes

Last Updated: 2025-10-25
