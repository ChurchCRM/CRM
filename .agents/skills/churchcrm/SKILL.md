---
name: churchcrm
description: ChurchCRM project-specific development skills covering architecture, API, database, frontend, security, plugins, testing, and workflows. Use when working on any ChurchCRM feature, bug fix, or migration.
metadata:
  author: ChurchCRM
  version: "1.0.0"
---

# ChurchCRM Development Skills

Project-specific skills for AI agents and developers working on ChurchCRM. Each skill covers a focused workflow area with ChurchCRM-specific patterns, conventions, and examples.

## Architecture & API

**Reading order for API development:**
1. [Routing & Architecture](./routing-architecture.md) — Understand file organization and entry points
2. [Slim 4 Best Practices](./slim-4-best-practices.md) — Framework patterns (middleware, error handling)
3. [API Development](./api-development.md) — Create or modify REST endpoints
4. [Service Layer](./service-layer.md) — Extract business logic into services
5. [API Compatibility & Deprecation](./api-compatibility-and-deprecation.md) — Maintain backward compatibility

**Additional skills:**
| Skill | When to Use |
|-------|------------|
| [Slim MVC Skill](./slim-mvc-skill.md) | MVC route groups, security patterns, migration guidance (optional) |
| [Configuration Management](./configuration-management.md) | Settings, SystemConfig, admin panels |

## Database

| Skill | When to Use |
|-------|------------|
| [Database Operations](./database-operations.md) | ORM queries, Perpl ORM patterns, data persistence |
| [DB Schema Migration](./db-schema-migration.md) | Schema changes, migration scripts |

## Frontend & UI

> **Any time you add or edit a table with row actions — read [`table-action-menu.md`](./table-action-menu.md) first.**
> **Any time you add settings/config to a page — read [`frontend-development.md`](./frontend-development.md) (System Settings Panel section) for the gold-standard pattern from Finance Dashboard.**

| Skill | When to Use |
|-------|------------|
| [Table Action Menu](./table-action-menu.md) | **Required for every table with row-level actions** — dropdown pattern, overflow fix, cart buttons, checklist |
| [Frontend Development](./frontend-development.md) | **Settings Panel (gold-standard pattern), UI changes, Bootstrap 5, React, i18n, notifications, confirmations, modals, asset management** |
| [Tabler Components](./tabler-components.md) | Page layout, cards, tables, forms, nav, badges, modals, toasts, icons |
| [Bootstrap 5 Migration](./bootstrap-5-migration.md) | Complete BS4→BS5 migration reference: data attributes, class renames, JS API, components |
| [Webpack & TypeScript](./webpack-typescript.md) | Frontend bundling, React, asset management |
| [i18n & Localization](./i18n-localization.md) | Adding UI text, translations |
| [AI Locale Translation](./locale-ai-translation.md) | Translating missing terms via Claude AI before a release |
| [Locale Stack Ranking](./locale-stack-ranking.md) | **NEW** — Prioritize translation effort by impact (TIER-1: 53% world pop, TIER-2: 80%, etc.) |

## Tabler Migration (Vision 2026)

| Skill | When to Use |
|-------|------------|
| [Tabler Components](./tabler-components.md) | Page layout, cards, tables, forms, nav, badges, modals, toasts, icons — the new UI reference |
| [Library Replacement Guide](./tabler-library-replacement.md) | Which 3rd-party libs to swap (Select2→Tom Select, etc.), npm/webpack/Grunt changes |
| [Migration Playbook](./tabler-migration-playbook.md) | Per-page migration steps, full codebase audit inventory, phased execution plan |
| [Bootstrap 5 Migration](./bootstrap-5-migration.md) | Data attribute renames, CSS class mapping, JS API changes (shared with Frontend section) |
| [Error Reporting & Issue Filing](./error-reporting.md) | Shared Tabler-styled error pages (4xx/5xx), consistent UX, wiring to Issue Reporter modal, and E2E testing patterns |

**Agent-only skill file**: `.claudecode/migration-rules.md` — strict rules for the Tabler shell, personas, iconography, and legacy bridge.

**Epic Issue**: [#8301 — UI Migration: AdminLTE to Tabler 2026](https://github.com/ChurchCRM/CRM/issues/8301)

## Security

| Skill | When to Use |
|-------|------------|
| [Authorization & Security](./authorization-security.md) | Permission checks, authentication |
| [Security Best Practices](./security-best-practices.md) | Security features, sensitive operations, output escaping (incl. data-* attributes) |
| [GitHub Interaction](./github-interaction.md) | Security Advisory lifecycle: draft → publish → CVE request, notifying reporters |

## Plugins

| Skill | When to Use |
|-------|------------|
| [Plugin System](./plugin-system.md) | Plugin architecture, hooks, PluginManager |
| [Plugin Development](./plugin-development.md) | Creating/modifying plugins |
| [Plugin Migration](./plugin-migration.md) | Migrating plugins to new structure |

## Testing

| Skill | When to Use |
|-------|------------|
| [Testing](./testing.md) | Writing tests, debugging, test suites |
| [Cypress Testing](./cypress-testing.md) | E2E tests, CI/CD testing, API test patterns |
| [Testing Migration & E2E](./testing-migration-e2e.md) | Testing strategy for migrations |

### Running Cypress Locally

Follow these steps to run Cypress tests locally and generate machine-readable reports useful for CI parity:

- Install dependencies:

  ```bash
  npm ci
  ```

- Run a single spec (headless, Electron):

  ```bash
  npx cypress run --spec "cypress/e2e/path/to/specfile.spec.js" --browser electron
  ```

- Run the full test suite with JUnit output (for CI-like reports):

  ```bash
  npx cypress run --reporter junit --reporter-options "mochaFile=cypress/reports/junit-[name].xml"
  ```

- Run with a specific base URL (useful for docker/local server):

  ```bash
  CYPRESS_BASE_URL=http://127.0.0.1:8080/churchcrm/ npx cypress run --config-file cypress/configs/docker.config.ts
  ```

- Run a spec in headed mode for interactive debugging:

  ```bash
  npx cypress open --config-file cypress/configs/docker.config.ts
  ```

- Generate an HTML report (mochawesome) locally (optional):

  1. Install reporters:

     ```bash
     npm install --save-dev mochawesome mochawesome-merge mochawesome-report-generator
     ```

  2. Run and write JSON output:

     ```bash
     npx cypress run --reporter mochawesome --reporter-options "reportDir=cypress/reports,overwrite=false,html=false,json=true"
     ```

  3. Merge and generate HTML:

     ```bash
     npx mochawesome-merge cypress/reports/*.json > cypress/reports/merged.json
     npx mochawesome-report-generator cypress/reports/merged.json -o cypress/reports/html
     ```

- Tips & diagnostics:
  - Use `--headed --browser chrome` to visually reproduce failures.
  - Use `--config video=true,screenshotOnRunFailure=true` to capture artifacts.
  - When testing admin routes, ensure the local app is running and reachable (see `docker/` compose profiles used in CI).
  - Use `--reporter json` to produce structured output you can parse for automated triage.
  - For flaky selectors after UI changes, prefer stable selectors: `id`, `data-cy`, `input[name=]`, link href/text, and avoid visual utility classes.


**Before committing ANY test changes:** See `CLAUDE.md` → Test Review & Commit Workflow for mandatory checklist

## MVC Migration

| Skill | When to Use |
|-------|------------|
| [Admin MVC Migration](./admin-mvc-migration.md) | Migrating legacy pages to modern MVC |
| [Groups MVC Guidelines](./groups-mvc-guidelines.md) | Groups module MVC patterns |
| [Refactor](./refactor.md) | Refactoring legacy code to services/MVC |

## PHP & Performance

| Skill | When to Use |
|-------|------------|
| [PHP Best Practices](./php-best-practices.md) | ChurchCRM PHP patterns, Perpl ORM |
| [Modern PHP Frameworks](./modern-php-frameworks.md) | Security hardening, framework features |
| [Performance Optimization](./performance-optimization.md) | Query optimization, scaling, response times |
| [Observability, Logging & Metrics](./observability-logging-metrics.md) | Logging, metrics, monitoring |

## Development Process

| Skill | When to Use |
|-------|------------|
| [Git Workflow](./git-workflow.md) | Commits, PRs, pre-commit validation |
| [GitHub Interaction](./github-interaction.md) | Reviews, commits, PR management |
| [PR Review](./pr-review.md) | Full PR review: fetch changes, validate standards, check docs/wiki, manual testing, address comments, capture learnings |
| [PR Description Guidelines](../pr-description-guidelines.md) | Ensure PR bodies are written in Markdown with required sections (Summary, Changes, Files Changed, Validation, Testing) |
| [Development Workflows](./development-workflows.md) | Setup, build, Docker management |
| [Code Standards](./code-standards.md) | General coding, quality checks, PR reviews |
| [Wiki Documentation](./wiki-documentation.md) | Complex documentation, admin guides |
| [Release Notes](./release-notes.md) | Authoring GitHub release notes for any version type |
| [Social Media Release](./social-media-release.md) | Generating platform posts for X, Facebook, Instagram, LinkedIn |

## Example Workflows

- **New API endpoint**: `api-development.md` → `service-layer.md` → `slim-4-best-practices.md` → `security-best-practices.md` → `cypress-testing.md` → `git-workflow.md`
- **Migrate legacy page**: `routing-architecture.md` → `admin-mvc-migration.md` → `frontend-development.md` → `database-operations.md` → `git-workflow.md`
- **Fix security issue**: `security-best-practices.md` → `authorization-security.md` → `php-best-practices.md` → `git-workflow.md`
- **Add plugin**: `plugin-system.md` → `plugin-development.md` → `api-development.md` → `git-workflow.md`
- **Optimize queries**: `performance-optimization.md` → `database-operations.md` → `service-layer.md`
- **Add UI text**: `i18n-localization.md` → `frontend-development.md` → `git-workflow.md`
- **Manage security advisory** (publish GHSA, request CVE, notify reporters): `github-interaction.md` (Security Advisory Management section) → `security-best-practices.md`
- **Write release notes**: `release-notes.md` → `github-interaction.md`
- **Publish a release**: `release-notes.md` → `social-media-release.md` → `github-interaction.md`
- **Review a PR**: `pr-review.md` → `code-standards.md` → `security-best-practices.md` → `wiki-documentation.md`
- **Address PR comments**: `pr-review.md` → `github-interaction.md` → `git-workflow.md`
- **Add print support to a page**: `frontend-development.md` (Print Support section) → `security-best-practices.md` (CSP) → `git-workflow.md`
- **Migrate a page to Tabler**: `tabler-migration-playbook.md` → `tabler-components.md` → `table-action-menu.md` → `bootstrap-5-migration.md` → `git-workflow.md`
- **Add or edit a table with row actions**: `table-action-menu.md` → `tabler-components.md` → `git-workflow.md`
- **Swap a 3rd-party library**: `tabler-library-replacement.md` → `webpack-typescript.md` → `git-workflow.md`
