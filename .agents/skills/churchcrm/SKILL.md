---
name: churchcrm
description: ChurchCRM project-specific development skills covering architecture, API, database, frontend, security, plugins, testing, and workflows. Use when working on any ChurchCRM feature, bug fix, or migration.
metadata:
  author: ChurchCRM
  version: "1.0.0"
---

# ChurchCRM Development Skills

These files apply to every agent family. Product floor is PHP 8.4+. Community plugins ship in the product. The plugin registry URL is `CentralServices::PLUGIN_REGISTRY_URL`.

Load `skill-architecture.md` only when adding or editing a skill.

## Architecture & API

1. [Routing & Architecture](./routing-architecture.md)
2. [Slim 4 Best Practices](./slim-4-best-practices.md)
3. [API Development](./api-development.md)
4. [Service Layer](./service-layer.md)

| Skill | When to Use |
|-------|------------|
| [Slim MVC Skill](./slim-mvc-skill.md) | Slim apps under `src/` |
| [Configuration Management](./configuration-management.md) | SystemConfig |
| [Settings Placement](./settings-placement.md) | Where a setting lives, area admin hub |
| [Volunteer Management v2](./volunteer-v2.md) | Ministries / Volunteer v2 (full design: `docs/design/volunteer-v2.md`) |
| [Member Portal Design](./member-portal-design.md) | `/portal` and themes |

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
| [Webpack & TypeScript](./webpack-typescript.md) | Bundling |
| [i18n & Localization](./i18n-localization.md) | Wrap UI strings |
| [Locale Translation](./locale-translation-workflow.md) | Translation agents |
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
| [Plugin Migration](./plugin-migration.md) | Update `src/plugins/core/*` when core APIs change |
| [Plugin Security Scan](./plugin-security-scan.md) | Intake |
| [Plugin Compliance](./plugin-compliance.md) | Installed-plugin audit |

## Testing

| Skill | When to Use |
|-------|------------|
| [Cypress Testing](./cypress-testing.md) | Writing and running specs, permission (gate) tests |
| [CI & Test Infrastructure](./ci-test-infrastructure.md) | Test stack, red CI, shards, test database and seed, cleanup guard |
| [Marketing Visual-Media Pipeline](./marketing-visuals-pipeline.md) | Playwright captures |

## MVC / PHP

| Skill | When to Use |
|-------|------------|
| [Admin MVC Migration](./admin-mvc-migration.md) | Convert a page to Slim MVC |
| [Groups MVC Guidelines](./groups-mvc-guidelines.md) | Groups |
| [Refactor](./refactor.md) | Feature refactor |
| [PHP Best Practices](./php-best-practices.md) | PHP / Perpl |
| [Performance Optimization](./performance-optimization.md) | Queries |

Community feedback policy (Feature Proposals and RFC Reviews Discussions): [`docs/decisions/0003-rfc-community-prioritization.md`](../../../docs/decisions/0003-rfc-community-prioritization.md).

## Process

| Skill | When to Use |
|-------|------------|
| [Git Workflow](./git-workflow.md) | Commit / push |
| [Release Management](./release-management.md) | Release preparation and verification |
| [Maintainer Review Gates](./maintainer-review-gates.md) | Review a PR |
| [PR Review](./pr-review.md) | Standards checklist |
| [Dependabot PR Review](./dependabot-review.md) | `/dependabot-review <PR>`: npm or Composer upgrade review |
| [PR Review Fix](../pr-review-fix.md) | Address review comments |
| [Skill architecture](./skill-architecture.md) | Edit a skill |
| [PR Description Guidelines](../pr-description-guidelines.md) | Title / body |
| [Development Workflows](./development-workflows.md) | Local setup commands |
| [Code Standards](./code-standards.md) | Always-on floor |

## Example Workflows

- **Review a PR**: `maintainer-review-gates.md` → `pr-review.md`. Draft only.
- **Address PR comments**: `../pr-review-fix.md` → `git-workflow.md`
- **Add UI text**: `i18n-localization.md`. `locale:build` runs on merge to `master`.
