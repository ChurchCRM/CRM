---
title: "Development Workflows"
intent: "Setup, build, Docker, and testing workflows for development and CI"
tags: ["devops","workflows","docker","testing"]
prereqs: ["[[testing]]"]
complexity: "beginner"
---

# Skill: Development Workflows

## Context
This skill covers setup, build processes, Docker management, testing workflows, and deployment procedures for ChurchCRM.

## Stack Summary

- **PHP 8.4+** - Server-side language
- **Perpl ORM** - Database layer (actively maintained fork of Propel2)
- **Slim 4** - API routes and modern MVC
- **Tabler + Bootstrap 5.3.x** - Primary UI framework
- **Vanilla JS + TypeScript** - Frontend modules (no React)
- **Webpack** - Build system for frontend assets
- **Cypress** - End-to-end testing

## Quick Start

Use locally installed PHP, Composer, Node.js/npm, and Docker. `DEVELOPING.md` is the canonical onboarding guide; do not infer setup steps from optional environment files.

## Setup & Build

### Initial Setup

```bash
npm install               # Install Node dependencies
npm run build             # Build everything (PHP + frontend)
npm run docker:test:start # Start the local test stack
```

### Development Cycle

```bash
npm run build:frontend       # Rebuild JS/CSS (watches via Webpack)
npm run build:php            # Update Composer dependencies
npm run docker:dev:logs      # View container logs
npm run docker:dev:login:web # Shell into web container
```

## Docker Management

### Development Stack

```bash
npm run docker:dev:start     # Start the Docker development stack
npm run docker:dev:stop      # Stop containers
npm run docker:dev:logs      # View logs
npm run docker:dev:login:web # Shell into web container
```

### Building Without a Local PHP/Composer/Node.js Toolchain <!-- learned: 2026-09-17 -->

The `dev` Docker image (`docker/Dockerfile.churchcrm-apache-php8`, `target: dev`) already has PHP, Composer, and Node 24/npm installed, so none of that toolchain needs to exist on the host — only `docker`/`docker compose` and `npm` itself (just to invoke the wrapper scripts below) are assumed, with `docker:dev:start` running. If even `npm` isn't available on the host, skip straight to the raw `docker compose exec` fallback at the end of this section.

```bash
npm run docker:dev:start   # bring the stack up first
npm run docker:dev:build   # composer install + full npm run build, inside the container
npm run docker:dev:watch   # webpack --watch, inside the container
```

`docker:dev:build`/`docker:dev:watch` run the whole repo (mounted at `/home/ChurchCRM` in the container — `src/` alone is separately mounted at `/var/www/html`, the Apache document root) through `npm ci && npm run build` inside `webserver`. `npm run build:php`'s validators (`scripts/validate-php-syntax.js` etc.) shell out to `php -l`, so the full chain needs PHP too, not just Node — the container has both, a bare host doesn't need either.

**Gotcha:** Node/npm are installed via `nvm` into `/root/.nvm`, whose installer only wires itself into `~/.bashrc`. `docker compose exec ... bash -c "..."` is a **non-interactive** shell and never sources `~/.bashrc`, so a raw `bash -c "npm ..."` exec fails with `npm: command not found` even though `composer` (installed straight to `/usr/local/bin`) works fine. `docker:dev:build`/`docker:dev:watch` must `source /root/.nvm/nvm.sh &&` before any `npm`/`node` call — the same requirement applies to any new script or manual `docker compose exec` invocation that touches Node in this container (#9890).

If npm itself isn't available on the host either (so `npm run docker:dev:build` can't even be typed), run the equivalent directly:

```bash
docker compose -f docker/docker-compose.dev.yaml exec webserver bash -c \
  "source /root/.nvm/nvm.sh && cd /home/ChurchCRM && npm ci && npm run build"
```

### Testing Containers

```bash
npm run docker:test:start       # Start test containers
npm run docker:test:stop        # Stop containers and keep volumes
npm run docker:test:rebuild     # Full rebuild with new images
npm run docker:test:down        # Remove containers and volumes
npm run docker:test:reset:db    # Reload the seeded test database
npm run docker:test:logs        # Follow test logs
```

### CI Containers

```bash
npm run docker:ci:root:start           # Parallel test — root path install
npm run docker:ci:root:down            # Tear down root profile
npm run docker:ci:subdir:start         # Parallel test — subdirectory install
npm run docker:ci:subdir:down          # Tear down subdir profile
npm run docker:ci:new-system:start     # Fresh empty database (setup wizard test)
npm run docker:ci:new-system:down      # Tear down new-system profile
```

The development stack uses `docker/docker-compose.dev.yaml`. Test and CI profiles use `docker/docker-compose.yaml` with `docker/docker-compose.parallel.yaml` and `docker/docker-compose.subdir.yaml` where required.

## Testing Workflows

### Local Testing

```bash
# Run all tests (headless)
npm run test

# Run specific test file
npx cypress run --config-file cypress/configs/docker.config.ts \
  --spec "cypress/e2e/api/path/to/test.spec.js"

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

- **Docker profiles**: `test` and `ci` in `docker/docker-compose.yaml`, with parallel overlays for CI scenarios
- **CI commands**: use the exact `docker:ci:*` scripts defined in `package.json`
- **Artifacts uploaded**: `cypress-artifacts-{run_id}` contains logs, screenshots, videos
- **Access**: Actions → Workflow run → Artifacts section
- **Debugging**: Download `cypress-reports-{branch}` for detailed failure analysis

### Parallel Testing (Root + Subdirectory)

ChurchCRM supports both root path (`/`) and subdirectory (`/churchcrm/`) installs.
Parallel infrastructure runs both configurations simultaneously without conflicts.

```bash
# Root path tests
npm run docker:ci:root:start
npx cypress run --config-file cypress/configs/docker.config.ts
npm run docker:ci:root:down

# Subdirectory tests
npm run docker:ci:subdir:start
npx cypress run --config-file cypress/configs/docker.config.ts
npm run docker:ci:subdir:down

# Fresh-system tests (empty database, triggers setup wizard)
npm run docker:ci:new-system:start
npm run test:new-system
npm run docker:ci:new-system:down
```

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
npm run build:webpack:watch # Watch mode for development
```

**Output:** `src/skin/v2/churchcrm.min.js`, `src/skin/v2/churchcrm.min.css`

### PHP Build

```bash
npm run build:php          # Update Composer dependencies
npm run build              # Full build (PHP + frontend)
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
  - "Fix issue #7698: Migrate dropdown to Tabler action menu pattern"

### Pull Request

- One issue per branch - do not mix fixes
- Keep commits small and focused
- Test each branch independently
- Include issue number in PR title
- Link related issues in PR description

## Configuration Files

- **Build**: `webpack.config.js`, `Gruntfile.js`, `package.json`
- **Docker**: `docker/docker-compose.dev.yaml`, `docker/docker-compose.yaml`, `docker/docker-compose.parallel.yaml`, `docker/docker-compose.subdir.yaml`, and deployment examples under `docker/examples/`
- **Cypress**: `cypress/configs/docker.config.ts`, `cypress/configs/new-system.config.ts`, `cypress/configs/base.config.ts`, `cypress/configs/_shared.ts`
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
