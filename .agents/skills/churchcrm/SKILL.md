---
name: churchcrm
description: ChurchCRM project-specific development skills covering architecture, API, database, frontend, security, plugins, testing, and workflows. Use when working on any ChurchCRM feature, bug fix, or migration.
metadata:
  author: ChurchCRM
  version: "1.0.0"
---

# ChurchCRM Development Skills

Project-specific skills for AI agents and developers working on ChurchCRM. Each skill covers a focused workflow area with ChurchCRM-specific patterns, conventions, and examples.

These files apply to **every agent family** that reads this repo. Do not assume Claude, Copilot, or a local `~/.claude/` path. Product floor is PHP 8.4+. Community plugins are current. Do not invent `/metrics`, SaaS hosting, or the retired `External` plugin-registry branch.

Load `skill-architecture.md` only when adding or editing a skill.

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
| [Slim MVC Skill](./slim-mvc-skill.md) | Inventory of the Slim apps under `src/`, their role gates, and the shared API entity middleware |
| [Configuration Management](./configuration-management.md) | Settings, SystemConfig, admin panels |

## Database

| Skill | When to Use |
|-------|------------|
| [Database Operations](./database-operations.md) | ORM queries, Perpl ORM patterns, data persistence |
| [DB Schema Migration](./db-schema-migration.md) | Schema changes, migration scripts |

## Frontend & UI

> [!IMPORTANT] Read first
> Any time you add or edit a table with row actions — read [`table-action-menu.md`](./table-action-menu.md) first.
> Any time you add settings/config to a page — read [`frontend-development.md`](./frontend-development.md) (System Settings Panel section) for the gold-standard pattern from Finance Dashboard.

| Skill | When to Use |
|-------|------------|
| [Icon Management](./icon-management.md) | **Font Awesome only** — icon patterns, free tier compliance (no paid variants), Tabler→FA migration reference, common substitutions, accessibility |
| [Table Action Menu](./table-action-menu.md) | **Required for every table with row-level actions** — dropdown pattern, overflow fix, cart buttons, checklist |
| [Frontend Development](./frontend-development.md) | **Settings Panel, UI changes, Bootstrap 5, i18n, notifications, modals, assets** |
| [Timezone Handling](./timezone-handling.md) | Datetime-aware changes. Read before event calendar / kiosk time code |
| [Responsive Design Guidelines](./responsive-design-guidelines.md) | Mobile / tablet / laptop layout |
| [Tabler Components](./tabler-components.md) | Page layout, cards, tables, forms, nav, badges, modals, toasts |
| [Webpack & TypeScript](./webpack-typescript.md) | Frontend bundling |
| [i18n & Localization](./i18n-localization.md) | Adding UI text. Wrap only; `locale:build` on merge to master |
| [Locale Translation Workflow](./locale-translation-workflow.md) | POEditor translate → upload → download |
| [Currency Localization](./currency-localization.md) | Money display. Epic: [#8459](https://github.com/ChurchCRM/CRM/issues/8459) |
| [Error Reporting & Issue Filing](./error-reporting.md) | Tabler error pages and Issue Reporter |

## Security

| Skill | When to Use |
|-------|------------|
| [Authorization & Security](./authorization-security.md) | Permission checks, authentication |
| [Security Best Practices](./security-best-practices.md) | Escaping, sensitive operations |
| [Security Advisory Review](./security-advisory-review.md) | Draft GHSA analysis |
| [GitHub Interaction](./github-interaction.md) | Advisory publish / CVE / reporters |

## Plugins

| Skill | Audience | When to Use |
|-------|----------|------------|
| [Plugin System](./plugin-system.md) | All | Runtime architecture |
| [Plugin Development](./plugin-development.md) | Plugin authors | Start here |
| [Plugin Create (Community)](./plugin-create.md) | Community authors | Scaffold + submit |
| [Plugin Migration (Core only)](./plugin-migration.md) | Core maintainers | `src/plugins/core/*` |
| [Plugin Security Scan](./plugin-security-scan.md) | Maintainers | Before `approved-plugins.json` |
| [Plugin Compliance (Admin Audit)](./plugin-compliance.md) | Site admins | Installed-plugin audit |

## Testing

| Skill | When to Use |
|-------|------------|
| [Testing](./testing.md) | Writing tests |
| [Cypress Testing](./cypress-testing.md) | E2E / API tests. Commands live in `package.json` |
| [Testing Migration & E2E](./testing-migration-e2e.md) | Migration test strategy |
| [Marketing Visual-Media Pipeline](./marketing-visuals-pipeline.md) | Playwright marketing captures |

## MVC Migration

| Skill | When to Use |
|-------|------------|
| [Admin MVC Migration](./admin-mvc-migration.md) | Legacy pages → MVC |
| [Groups MVC Guidelines](./groups-mvc-guidelines.md) | Groups module |
| [Refactor](./refactor.md) | Legacy → services/MVC |

## PHP & Performance

| Skill | When to Use |
|-------|------------|
| [PHP Best Practices](./php-best-practices.md) | ChurchCRM PHP / Perpl |
| [Modern PHP Frameworks](./modern-php-frameworks.md) | Hardening |
| [Performance Optimization](./performance-optimization.md) | Query / scale |
| [Observability, Logging & Metrics](./observability-logging-metrics.md) | Logging |

## Development Process

| Skill | When to Use |
|-------|------------|
| [Git Workflow](./git-workflow.md) | Commits, PRs |
| [GitHub Interaction](./github-interaction.md) | Reviews, GHSA |
| [Maintainer Review Gates](./maintainer-review-gates.md) | Review a PR first. Agents never approve or merge |
| [PR Review](./pr-review.md) | Fetch PR + standards checklist |
| [Skill architecture](./skill-architecture.md) | Editing skills only |
| [PR Description Guidelines](../pr-description-guidelines.md) | PR body sections |
| [Development Workflows](./development-workflows.md) | Setup, Docker |
| [Code Standards](./code-standards.md) | Coding checks |
| [Documentation Architecture & Wiki](./wiki-documentation.md) | Which doc home |
| [Release Management](./release-management.md) | Ship a release |
| [Security Report Triage](./security-report-triage.md) | Private vuln read |
| [Release Notes](./release-notes.md) | Changelog rewrite |
| [Social Media Release](./social-media-release.md) | Social posts |
| [Release Announcement](./release-announcement.md) | After publish |
| [Repo Health Check](./repo-health.md) | GitHub hygiene snapshot |

## Example Workflows

- **New API endpoint**: `api-development.md` → `service-layer.md` → `slim-4-best-practices.md` → `security-best-practices.md` → `cypress-testing.md` → `git-workflow.md`
- **Migrate legacy page**: `routing-architecture.md` → `admin-mvc-migration.md` → `frontend-development.md` → `database-operations.md` → `git-workflow.md`
- **Fix security issue**: `security-best-practices.md` → `authorization-security.md` → `php-best-practices.md` → `git-workflow.md`
- **Add a community plugin**: `plugin-system.md` → `plugin-development.md` → `plugin-create.md` → `plugin-security-scan.md` → `git-workflow.md`
- **Add UI text**: `i18n-localization.md` → `git-workflow.md`. Do not run `locale:build` in the PR.
- **Review a PR**: `maintainer-review-gates.md` → `pr-review.md` → `code-standards.md`. Draft only. Never approve or merge.
- **Address PR comments**: `pr-review-fix.md` → `git-workflow.md`
