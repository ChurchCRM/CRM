---
title: "Development Workflows"
intent: "Setup, build, Docker, and testing workflows for development and CI"
tags: ["devops","workflows","docker","testing"]
prereqs: ["testing.md"]
complexity: "beginner"
---

# Skill: Development Workflows

## Context
This skill covers setup, build processes, Docker management, testing workflows, and deployment procedures for ChurchCRM.

## Stack Summary

- **PHP 8.4+** - Server-side language
- **Perpl ORM** - Database layer (actively maintained fork of Propel2)
- **Slim 4** - API routes and modern MVC
- **Bootstrap 4.6.2** - AdminLTE v2 pattern for legacy pages
- **React + TypeScript** - Modern frontend components
- **Webpack** - Build system for frontend assets
- **Cypress** - End-to-end testing

## Quick Start (GitHub Codespaces/Dev Containers)

- **GitHub Codespaces**: Click"Code" → "Codespaces" → "Create codespace" - fully automated setup
- **VS Code Dev Containers**: Install Dev Containers extension, open repo, click "Reopen in Container"
- **Manual setup**: Run `./scripts/setup-dev-environment.sh` for automated local setup

## Setup & Build

### Initial Setup

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

## Docker Management

### Development Containers

```bash
npm run docker:dev:start     # Start dev containers
npm run docker:dev:stop      # Stop containers
npm run docker:dev:logs      # View logs
npm run docker:dev:login:web # Shell into web container
```

### Testing Containers

```bash
npm run docker:test:start       # Start test containers
npm run docker:test:restart     # Restart all containers
npm run docker:test:restart:db  # Restart database only (refresh schema)
npm run docker:test:rebuild     # Full rebuild with new images
npm run docker:test:down        # Remove containers and volumes
```

### Docker Profiles

- **dev** - Development environment
- **test** - Local testing environment
- **ci** - CI/CD optimized containers

Configuration files: `docker-compose.yaml`, `docker-compose.gh-actions.yaml`

## Testing Workflows

### Local Testing

```bash
# Run all tests (headless)
npm run test

# Run specific test file
npx cypress run --spec "cypress/e2e/api/path/to/test.spec.js"

# Interactive browser testing
npm run test:ui
```

### CRITICAL Testing Workflow (FOR ALL RUNS)

**BEFORE every test run:**
```bash
# 1. Clear old logs
rm -f src/logs/$(date +%Y-%m-%d)-*.log
```

**RUN the test(s)**

**AFTER test completion (pass OR fail):**
```bash
# 2. Review logs for errors
cat src/logs/$(date +%Y-%m-%d)-php.log      # PHP errors, ORM errors
cat src/logs/$(date +%Y-%m-%d)-app.log      # App events
```

**Even if tests pass: Verify no 500 errors or exceptions were logged silently.**

### CI/CD Testing (GitHub Actions)

- **Docker profiles**: `dev`, `test`, `ci` in `docker-compose.yaml`
- **CI command**: `npm run docker:ci:start` with optimized containers
- **Artifacts uploaded**: `cypress-artifacts-{run_id}` contains logs, screenshots, videos
- **Access**: Actions → Workflow run → Artifacts section
- **Debugging**: Download `cypress-reports-{branch}` for detailed failure analysis

### Test Requirements Before Committing

**ALWAYS add tests when creating new features:**
- **API tests**: Required for all new API endpoints
- **UI tests**: Recommended for critical user workflows
- **Test location**: `cypress/e2e/api/` for API tests, `cypress/e2e/ui/` for UI tests

**Run tests before committing:**
```bash
# Clear logs
rm -f src/logs/$(date +%Y-%m-%d)-*.log

# Run relevant tests
npx cypress run --e2e --spec "path/to/test.spec.js"

# Review logs (even if tests pass)
cat src/logs/$(date +%Y-%m-%d)-php.log
cat src/logs/$(date +%Y-%m-%d)-app.log
```

**Only proceed to commit after:**
- Tests pass successfully
- Logs show no hidden errors

## Build Processes

### Frontend Build

```bash
npm run build               # Build all frontend assets (production)
npm run build:frontend      # Only rebuild JS/CSS
npm run watch              # Watch mode for development
```

**Output:** `src/skin/v2/churchcrm.min.js`, `src/skin/v2/churchcrm.min.css`

### PHP Build

```bash
npm run build:php          # Update Composer dependencies
npm run deploy             # Full build (PHP + frontend)
```

**Validates:** PHP syntax, Composer dependencies

### Locale Build (CRITICAL for i18n)

**BEFORE committing new gettext() or i18next.t() strings:**

```bash
npm run locale:build   # Extract terms into messages.po
npm run build          # Rebuild frontend bundles with new terms
# Commit updated locale/terms/messages.po with your changes
```

## Terminology & i18n Conventions

- Use a single canonical UI term where possible to reduce translation surface
- Use `People` (not `Persons`) for all UI/display gettext strings
- For family lifecycle/status use **Active / Inactive** (avoid "Deactivated")
- Add new canonical UI terms to `locale/messages.po` before wiring them into templates
- Leave translations empty for translators to fill

### i18n Term Consolidation

**Reduce translator burden by consolidating compound terms:**

```php
// ✅ CORRECT - Consolidates 7+ variants to 1 term
$sPageTitle = gettext('Delete Confirmation') . ': ' . gettext('Note');
$buttonLabel = gettext('Add New') . ' ' . gettext('Fund');

// ❌ WRONG - Creates separate translations for each variant
$sPageTitle = gettext('Note Delete Confirmation');
$buttonLabel = gettext('Add New Fund');
```

## File Locations Reference

| Path | Purpose |
|------|---------|
| `orm/` | Perpl ORM schema.xml and propel.php.dist configuration |
| `src/ChurchCRM/Service/` | Business logic layer |
| `src/ChurchCRM/model/ChurchCRM/` | Perpl ORM generated classes (don't edit) |
| `src/api/` | REST API entry point + routes |
| `src/admin/routes/api/` | Admin-only API endpoints (NEW - use this for admin APIs) |
| `src/finance/` | Finance module (Slim 4 MVC) - dashboard, reports |
| `src/plugins/` | Plugin system entry point + management routes |
| `src/plugins/core/` | Core plugins shipped with ChurchCRM |
| `src/plugins/community/` | Third-party community plugins |
| `src/Include/` | Utility functions, helpers, Config.php |
| `src/locale/` | i18n/translation strings |
| `src/skin/v2/` | Compiled CSS/JS from Webpack |
| `react/` | React TSX components |
| `webpack/` | Webpack entry points |
| `cypress/e2e/api/` | API test suites |
| `cypress/e2e/ui/` | UI test suites |
| `docker/` | Docker Compose configs |
| `cypress/data/seed.sql` | Demo database dump - **NEVER edit manually** (auto-generated) |

## Development Best Practices

### Frontend State Rendering

**Render initial UI state server-side to avoid JS-only initialization flashes:**

```php
// ✅ CORRECT - Server-side initial state
<div id="user-stats">
    <span class="badge"><?= $data['stats']['total'] ?></span>
</div>

<script>
// JavaScript only for dynamic updates
function refreshStats() {
    $.get('/admin/api/users/stats', function(data) {
        $('#user-stats .badge').text(data.total);
    });
}
</script>

// ❌ WRONG - Empty div filled by JS (causes flash)
<div id="user-stats"></div>
<script>
// Page loads with empty div, then JS fills it (visible delay)
$.get('/admin/api/users/stats', function(data) {
    $('#user-stats').html('<span class="badge">' + data.total + '</span>');
});
</script>
```

### Boolean Config

Use `SystemConfig::getBooleanValue('key')` for truthy/falsey checks:

```php
// ✅ CORRECT
if (SystemConfig::getBooleanValue('bEnableLostPassword')) {
    // Feature enabled
}

// ❌ WRONG - String comparison issues
if (SystemConfig::getValue('bEnableLostPassword') == '1') {
    // May fail with different truthy values
}
```

### Asset Paths

**ALWAYS use SystemURLs::getRootPath() for asset references:**

```php
// ✅ CORRECT
<link rel="stylesheet" href="<?= SystemURLs::getRootPath() ?>/skin/v2/churchcrm.min.css">
<img src="<?= SystemURLs::getRootPath() ?>/images/logo.png">

// ❌ WRONG - Relative paths break in subdirectories
<link rel="stylesheet" href="/skin/v2/churchcrm.min.css">
```

## Commit & PR Workflow

### Branching

```bash
# Always create a new branch from master
git checkout master
git pull origin master
git checkout -b fix/issue-NUMBER-description

# Make changes...

# Stage changes
git add -A

# DO NOT commit automatically - ask user first
```

### Commit Format

- **Imperative mood**, < 72 chars for subject line
- **Examples**:
  - "Fix validation in Checkin form"
  - "Replace deprecated HTML attributes with Bootstrap CSS"
  - "Add missing element ID for test selector"
  - "Fix issue #7698: Replace Bootstrap 5 classes with BS4"

### Pull Request

- One issue per branch - do not mix fixes
- Keep commits small and focused
- Test each branch independently
- Include issue number in PR title
- Link related issues in PR description

## Configuration Files

- **Build**: `webpack.config.js`, `Gruntfile.js`, `package.json`
- **Docker**: `docker-compose.yaml`, `docker/docker-compose.*.yaml`
- **Cypress**: `cypress.config.ts`, `docker/cypress.config.ts`
- **PHP**: `composer.json`, `orm/propel.php.dist`
- **ORM**: `orm/schema.xml`

## Logs & Debugging

**Application logs:** `src/logs/YYYY-MM-DD-php.log`, `src/logs/YYYY-MM-DD-app.log`

**Always clear logs before testing:**
```bash
rm -f src/logs/$(date +%Y-%m-%d)-*.log
```

**Review logs after any operation:**
```bash
cat src/logs/$(date +%Y-%m-%d)-php.log
cat src/logs/$(date +%Y-%m-%d)-app.log
```

**Log levels:**
- `debug` - Development info
- `info` - Business events
- `warning` - Non-critical issues
- `error` - Failures, exceptions
