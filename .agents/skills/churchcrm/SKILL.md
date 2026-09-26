---
name: churchcrm
description: ChurchCRM project-specific development skills covering architecture, API, database, frontend, security, plugins, testing, and workflows. Use when working on any ChurchCRM feature, bug fix, or migration.
metadata:
  author: ChurchCRM
  version: "1.0.0"
---

# ChurchCRM Development Skills

These files apply to every agent family. Product floor is PHP 8.4+. Community plugins are current. Do not invent `/metrics` or SaaS hosting. Plugin registry URL is `CentralServices::PLUGIN_REGISTRY_URL` (External branch today).

Load `skill-architecture.md` only when adding or editing a skill.

## Architecture & API

1. [Routing & Architecture](./routing-architecture.md)
2. [Slim 4 Best Practices](./slim-4-best-practices.md)
3. [API Development](./api-development.md)
4. [Service Layer](./service-layer.md)
5. [API Compatibility & Deprecation](./api-compatibility-and-deprecation.md)

| Skill | When to Use |
|-------|------------|
| [Slim MVC Skill](./slim-mvc-skill.md) | Slim apps under `src/` |
| [Configuration Management](./configuration-management.md) | SystemConfig |
| [Online Giving Design](./online-giving-design.md) | Proposed design (feedback wanted): payment gateway plugins, online and recurring gifts, per-person attribution, portal Giving page |

## Database

| Skill | When to Use |
|-------|------------|
| [Database Operations](./database-operations.md) | ORM |
| [DB Schema Migration](./db-schema-migration.md) | Schema / upgrade scripts |

## Frontend & UI

Row actions: [`table-action-menu.md`](./table-action-menu.md). Settings UI: copy the Finance dashboard pattern (`frontend-development.md`).

| Skill | When to Use |
|-------|------------|
| [Icon Management](./icon-management.md) | Font Awesome |
| [Table Action Menu](./table-action-menu.md) | Table row actions |
| [Frontend Development](./frontend-development.md) | UI changes |
| [Timezone Handling](./timezone-handling.md) | Event / kiosk time |
| [Responsive Design Guidelines](./responsive-design-guidelines.md) | Layout |
| [Tabler Components](./tabler-components.md) | Tabler markup |
| [Webpack & TypeScript](./webpack-typescript.md) | Bundling |
| [i18n & Localization](./i18n-localization.md) | Wrap UI strings |
| [Locale Translation Workflow](./locale-translation-workflow.md) | POEditor |
| [Currency Localization](./currency-localization.md) | Money display |
| [Error Reporting & Issue Filing](./error-reporting.md) | Error pages |

## Security

| Skill | When to Use |
|-------|------------|
| [Authorization & Security](./authorization-security.md) | Roles / EditSelf |
| [Security Best Practices](./security-best-practices.md) | Escaping / CSRF |
| [Security Advisory Review](./security-advisory-review.md) | GHSA |
| [GitHub Interaction](./github-interaction.md) | Advisory publish |

## Plugins

| Skill | When to Use |
|-------|------------|
| [Plugin System](./plugin-system.md) | Runtime + registry constant |
| [Plugin Registry](./plugin-registry.md) | approved-plugins.json |
| [Plugin Development](./plugin-development.md) | Author a plugin |
| [Plugin Create (Community)](./plugin-create.md) | Submit a plugin |
| [Plugin Migration (Core only)](./plugin-migration.md) | `src/plugins/core/*` |
| [Plugin Security Scan](./plugin-security-scan.md) | Intake |
| [Plugin Compliance](./plugin-compliance.md) | Installed-plugin audit |

## Testing

| Skill | When to Use |
|-------|------------|
| [Testing](./testing.md) | Writing tests |
| [Cypress Testing](./cypress-testing.md) | E2E |
| [Testing Migration & E2E](./testing-migration-e2e.md) | Migrations |
| [Marketing Visual-Media Pipeline](./marketing-visuals-pipeline.md) | Playwright captures |

## MVC / PHP

| Skill | When to Use |
|-------|------------|
| [Admin MVC Migration](./admin-mvc-migration.md) | Legacy → MVC |
| [Groups MVC Guidelines](./groups-mvc-guidelines.md) | Groups |
| [Refactor](./refactor.md) | Feature refactor |
| [PHP Best Practices](./php-best-practices.md) | PHP / Perpl |
| [Performance Optimization](./performance-optimization.md) | Queries |

## Process

| Skill | When to Use |
|-------|------------|
| [Git Workflow](./git-workflow.md) | Commit / push |
| [Maintainer Review Gates](./maintainer-review-gates.md) | Review a PR |
| [PR Review](./pr-review.md) | Standards checklist |
| [PR Review Fix](../pr-review-fix.md) | Address review comments |
| [Skill architecture](./skill-architecture.md) | Edit a skill |
| [PR Description Guidelines](../pr-description-guidelines.md) | Title / body |
| [Development Workflows](./development-workflows.md) | Setup |
| [Code Standards](./code-standards.md) | Always-on floor |

## Example Workflows

- **Review a PR**: `maintainer-review-gates.md` → `pr-review.md`. Draft only.
- **Address PR comments**: `../pr-review-fix.md` → `git-workflow.md`
- **Add UI text**: `i18n-localization.md`. Do not run `locale:build` in the PR.
