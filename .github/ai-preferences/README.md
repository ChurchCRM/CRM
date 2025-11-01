# AI Agent Setup Guide# AI Preferences & Setup Guide



**Complete documentation for AI coding agents working on ChurchCRM.**This directory contains all AI agent configuration and documentation for ChurchCRM development.



---## 📁 Files



## 📁 Files in This Directory### `preferences.yml`

**Core AI agent configuration** - Standards and rules for all coding agents.

| File | Purpose | Audience |

|------|---------|----------|**Used by:**

| **preferences.yml** | Core standards configuration in YAML format | All AI agents, developers |- GitHub Copilot (workspace settings)

| **setup.md** | Detailed tool integration guide | AI agent users (Copilot, Claude, Cursor) |- Claude (context file)

| **README.md** | This file - Overview and quick start | Everyone |

**What it covers:**

---- Communication style (direct, action-first)

- Commit & PR standards (imperative, < 72 chars)

## 🚀 Quick Start- Code quality (Propel ORM, Service classes)

- HTML5 & CSS standards (Bootstrap only)

### For GitHub Copilot Users- Database rules (ORM mandatory, no raw SQL)

1. Open VS Code Settings: `Cmd/Ctrl + ,`- Asset paths (SystemURLs::getRootPath())

2. Search for "GitHub › Copilot: Custom Instructions"- Testing & documentation policies

3. Set value to: `.github/ai-preferences/preferences.yml`- Branch naming conventions

4. Reload window: `Cmd/Ctrl + Shift + P` > "Reload Window"

### `setup.md`

### For Claude/Cursor Users**Detailed setup and adoption guide** - How to integrate these preferences with your tools and workflow.

1. Add as context file: `.github/ai-preferences/preferences.yml`

2. Reference in conversations: "Follow the ChurchCRM AI preferences"**Covers:**

3. See `setup.md` for detailed instructions- GitHub Copilot configuration

- Claude/Cursor setup

### For All Developers- Pre-commit hook installation

Before every commit, verify the checklist below.- PR template usage

- Validation examples

---

---

## 📋 Core Standards at a Glance

## 🚀 Quick Start

### Database (MANDATORY)

```php### For Claude/Copilot Users

// ✅ CORRECT - Always Propel ORM1. Reference `preferences.yml` in your conversation

$event = EventQuery::create()->findById((int)$eventId);2. Use as context: "Follow the ChurchCRM AI preferences in `.github/ai-preferences/preferences.yml`"

if ($event === null) { /* not found */ }3. Verify output against the pre-commit checklist



// ❌ WRONG - Never raw SQL### For Developers

$result = RunQuery("SELECT * FROM events WHERE eventid = ?", $eventId);1. Hooks run automatically on `git commit`

```2. See `setup.md` for manual validation commands

3. Check PR template for required validations

### API Response Format

```php---

// ✅ CORRECT

return $response->withJson([## 📋 Pre-commit Checklist

    'data' => $result,

    'message' => 'Success'**Before every commit, verify:**

]);- ✅ PHP syntax validation passed

```- ✅ Propel ORM used for all database operations

- ✅ Asset paths use `SystemURLs::getRootPath()`

### HTML & CSS- ✅ Service classes used for business logic

```php- ✅ Deprecated HTML attributes replaced with CSS

// ✅ CORRECT - Bootstrap classes- ✅ Bootstrap CSS classes applied correctly

<div class="text-center align-top">Content</div>- ✅ Tests pass (if available)

- ✅ Commit message follows imperative mood

// ❌ WRONG - Deprecated attributes- ✅ Branch name follows kebab-case format

<div align="center" valign="top">Content</div>

```---



### Asset Paths## 🔧 Enforcement Mechanisms

```php

// ✅ CORRECT1. **Pull Request Template** (`.github/pull_request_template.md`)

href="<?= SystemURLs::getRootPath() ?>/skin/v2/churchcrm.min.css"   - Includes AI preferences validation section

   - Reminds reviewers of standards

// ❌ WRONG

href="/skin/v2/churchcrm.min.css"2. **Code Quality Template** (`.github/ISSUE_TEMPLATE/code-quality-check.md`)

```   - Checklist for code reviews



### Null Safety3. **Contributing Guide** (`CONTRIBUTING.md`)

```php   - Links to these preferences

// ✅ CORRECT - Propel objects are null, not empty   - Integration guidance

if ($event === null) { /* not found */ }

---

// ❌ WRONG

if (empty($event)) { /* breaks with Propel objects */ }## 📚 Related Files

```

- `CONTRIBUTING.md` - Development workflow (links to this folder)

---- `.github/pull_request_template.md` - PR requirements

- `.github/ISSUE_TEMPLATE/code-quality-check.md` - Review checklist

## 📌 Pre-Commit Checklist

---

Before committing, verify ALL of these:

## 🔑 Key Standards

- ✅ PHP syntax validation passed (`npm run build:php`)

- ✅ Propel ORM used for all database operations (no raw SQL)### Database (Mandatory)

- ✅ Type casting applied to dynamic values: `(int)$id````php

- ✅ Asset paths use `SystemURLs::getRootPath()`// ✅ CORRECT - Propel ORM

- ✅ Service classes used for business logic$events = EventQuery::create()->findById($eventId);

- ✅ Deprecated HTML attributes replaced with CSS (Bootstrap)

- ✅ Bootstrap CSS classes applied correctly// ❌ WRONG - Raw SQL

- ✅ Tests pass (if available)$events = query("SELECT * FROM events WHERE eventid = ?", $eventId);

- ✅ Commit message follows imperative mood (< 72 chars)```

- ✅ Branch name follows kebab-case format

- ✅ Logs cleared before testing: `rm -f src/logs/$(date +%Y-%m-%d)-*.log`### Object Validation

```php

---// ✅ CORRECT

if ($event === null) {

## 🔗 Complete Documentation  RedirectUtils::redirect('ListEvents.php');

}

### For Detailed Standards

See **`preferences.yml`** which includes:// ❌ WRONG

- Communication style (direct, action-first)if (empty($event)) {  // Unreliable with Propel objects

- Commit & PR standards  RedirectUtils::redirect('ListEvents.php');

- Code quality requirements}

- Database rules & ORM usage```

- HTML5 & CSS standards

- Testing requirements & best practices### HTML Attributes

- CI/CD patterns```php

- Error handling strategies// ✅ CORRECT - Bootstrap CSS

- PR organization strategy<div class="text-center align-top">Content</div>



### For Tool Setup// ❌ WRONG - Deprecated attributes

See **`setup.md`** which covers:<div align="center" valign="top">Content</div>

- GitHub Copilot configuration```

- Claude/Cursor IDE setup

- Pre-commit hook installation---

- Manual validation commands

- PR template usage## ❓ Questions?

- Validation examples

1. **Standards reference?** → See `preferences.yml`

### For Architecture & Patterns2. **Tool setup?** → See `setup.md`

See **`../.github/copilot-instructions.md`** which includes:3. **Development workflow?** → See `CONTRIBUTING.md`

- Application architecture (4 layers)4. **Specific code patterns?** → Check `preferences.yml` special instructions

- Slim Framework middleware ordering

- Database layer patterns---

- Service classes

- Critical patterns from bug fixes## 📈 Continuous Improvement

- Testing requirements

- PR organization strategyThis framework evolves with the project:

- Report issues or suggest improvements in PRs

---- Update `preferences.yml` as standards change

- Keep `setup.md` synchronized with new tools/processes

## 🏗️ Architecture Overview

Last Updated: 2025-10-25

**ChurchCRM Tech Stack:**
- PHP 8.1+ with Propel ORM (mandatory for database)
- MySQL/MariaDB
- Slim Framework for REST APIs
- React/TypeScript for interactive components
- Cypress for E2E testing
- AdminLTE 3.2.0 + Bootstrap 4.6.2 for UI

**Application Structure:**
1. **Legacy Pages** (`src/*.php`) - Traditional form handlers
2. **Modern APIs** (`src/api/`) - Slim REST endpoints
3. **Services** (`src/ChurchCRM/Service/`) - Business logic
4. **React Components** (`react/`) - Interactive UI
5. **Webpack** - JS/CSS bundling

---

## ⚠️ Critical Patterns (Lessons Learned)

These are common issues that cause 500 errors:

### 1. Type Mismatches in API Parsers
```php
// ❌ WRONG - Parser returns object, not array
$amount = $data['amount'];  // TypeError: Cannot access offset on object

// ✅ CORRECT
$amount = $data->amount;
```

### 2. Namespaced Code Calling Global Functions
```php
// ❌ WRONG - PHP searches current namespace first
namespace ChurchCRM\Service;
MakeFYString($id);  // PHP Error: undefined function

// ✅ CORRECT - Explicit global namespace
\MakeFYString($id);
```

### 3. Slim 4 Route Handlers
```php
// ❌ WRONG - String callables don't work in Slim 4
$group->post('/path', 'MyHandler::process');

// ✅ CORRECT - Use inline closures
$group->post('/path', function ($request, $response) {
    return $response->withJson($data);
});
```

### 4. Middleware Order in Slim
```php
// ❌ WRONG - Auth before routing
$app->add(AuthMiddleware::class);
$app->addRoutingMiddleware();  // Wrong order!

// ✅ CORRECT
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();  // MUST be before add()
$app->add(AuthMiddleware::class);
```

### 5. Email Failure Handling
```php
// ❌ WRONG - Crashes API on email failure
if (!mail(...)) throw new Exception("Email failed");

// ✅ CORRECT - Log but continue for non-critical
if (!mail(...)) {
    error_log("Email send failed");
}
return $response->withJson(['data' => $result]);
```

---

## 🧪 Testing Standards

### API Tests (Mandatory for API Changes)
```javascript
// Use provided Cypress helpers, NEVER cy.request directly
cy.makePrivateAdminAPICall("POST", "/api/payments", payload, 200)
cy.makePrivateUserAPICall("GET", "/api/events", null, 200)
```

### Test Categories Required
1. **Successful operations** - Valid data, 200 response
2. **Validation tests** - Invalid inputs, 400 response
3. **Type safety** - Verify object/array conversions work
4. **Error handling** - Test 401/403/404/500 responses
5. **Edge cases** - Null values, empty arrays

### Pre-Test Protocol
```bash
# Clear logs BEFORE testing (important!)
rm -f src/logs/$(date +%Y-%m-%d)-*.log

# Run tests
npm run test

# Review logs AFTER failures
cat src/logs/$(date +%Y-%m-%d)-php.log
```

---

## 🔄 PR Organization

Split large changes into focused branches:

```
❌ WRONG: Fix bug A + implement feature B + refactor C in one PR

✅ CORRECT:
  - Branch 1: fix/bug-a
  - Branch 2: feature/new-b  
  - Branch 3: refactor/cleanup-c
```

**Benefits:**
- Cleaner git history
- Easier to revert if needed
- Simpler code review
- Easier to isolate regressions

---

## 🔧 Enforcement & Integration

### Automatic Checks
- **Pull Request Template** (`.github/pull_request_template.md`)
  - Includes AI preferences validation section
  - Reminds reviewers of standards

- **Contributing Guide** (`CONTRIBUTING.md`)
  - Links to these preferences
  - Development workflow

### Manual Validation
```bash
# Check PHP syntax on modified files
git diff --name-only | grep '.php$' | xargs php -l

# Check for raw SQL
grep -r "SELECT\|INSERT\|UPDATE\|DELETE" src/ | grep -v "//" | grep -v "vendor/"

# Check for deprecated HTML
grep -r "align=\|valign=\|<center>\|<font" src/ | grep -v "//"
```

---

## 📚 Related Documentation

- **`CONTRIBUTING.md`** - Development workflow & how to contribute
- **`.github/pull_request_template.md`** - PR requirements & checklist
- **`.github/copilot-instructions.md`** - Complete AI agent instructions with examples
- **`README.md`** (root) - Project overview
- **GitHub Wiki** - Additional documentation

---

## 💡 Key Decision Points for AI Agents

### Database Layer
- **Always use:** Propel ORM Query classes
- **Never use:** Raw SQL or RunQuery()
- **Cast:** All dynamic IDs to `(int)`

### API Endpoints
- **Location:** `src/api/routes/[domain]/`
- **Service:** Use injected service class from container
- **Response:** Always `['data' => $result, 'message' => '...']`
- **Status codes:** Preserve proper HTTP codes (401, 403, 404, 422, 500)

### Service Classes
- **Location:** `src/ChurchCRM/Service/`
- **Pattern:** Stateless, focused business logic
- **Usage:** Injected via Symfony DI container

### Asset References
- **Always:** `SystemURLs::getRootPath()`
- **Never:** Relative paths or hardcoded `/`

### HTML & CSS
- **Framework:** Bootstrap 4.6.2
- **Pattern:** CSS classes, not deprecated attributes
- **Target:** HTML5 compliance

---

## ❓ Quick Navigation

**"How do I..."**
- Set up Copilot? → See `setup.md`
- Understand the standards? → See `preferences.yml`
- Learn code patterns? → See `.github/copilot-instructions.md`
- Write an API endpoint? → See architecture section above
- Write tests? → See "Testing Standards" section
- Fix a common error? → See "Critical Patterns" section

---

## 📈 Continuous Improvement

This framework evolves with the project:
- **Report issues:** Open a GitHub issue
- **Suggest improvements:** Submit a PR
- **Update standards:** Edit `preferences.yml` when patterns change
- **Keep docs in sync:** Update this README and `setup.md`

---

## 📞 Support

- **Questions about standards?** Check `preferences.yml`
- **Tool integration issues?** See `setup.md`
- **Architecture questions?** See `.github/copilot-instructions.md`
- **Chat with team?** Join the Gitter channel

---

*Last Updated: November 1, 2025*
*Framework Version: 1.0*
