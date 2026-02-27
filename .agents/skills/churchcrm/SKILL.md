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

| Skill | When to Use |
|-------|------------|
| [Routing & Architecture](./routing-architecture.md) | Adding routes, organizing project file layout |
| [Slim 4 Best Practices](./slim-4-best-practices.md) | Building REST APIs, middleware configuration |
| [Slim MVC Skill](./slim-mvc-skill.md) | MVC route groups, migration guidance |
| [API Development](./api-development.md) | Creating/modifying REST API endpoints |
| [API Compatibility & Deprecation](./api-compatibility-and-deprecation.md) | Backward compatibility, deprecation timelines |
| [Service Layer](./service-layer.md) | Creating business logic, service classes |
| [Configuration Management](./configuration-management.md) | Settings, SystemConfig, admin panels |

## Database

| Skill | When to Use |
|-------|------------|
| [Database Operations](./database-operations.md) | ORM queries, Perpl ORM patterns, data persistence |
| [DB Schema Migration](./db-schema-migration.md) | Schema changes, migration scripts |

## Frontend & UI

| Skill | When to Use |
|-------|------------|
| [Frontend Development](./frontend-development.md) | UI changes, Bootstrap 4, i18n |
| [Bootstrap 4.6.2 & AdminLTE](./bootstrap-adminlte.md) | UI components, layouts, admin pages |
| [Webpack & TypeScript](./webpack-typescript.md) | Frontend bundling, React, asset management |
| [UI Development](./ui-development.md) | General UI development practices |
| [i18n & Localization](./i18n-localization.md) | Adding UI text, translations |
| [AI Locale Translation](./locale-ai-translation.md) | Translating missing terms via Claude AI before a release |

## Security

| Skill | When to Use |
|-------|------------|
| [Authorization & Security](./authorization-security.md) | Permission checks, authentication |
| [Security Best Practices](./security-best-practices.md) | Security features, sensitive operations |

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
| [Cypress Testing](./cypress-testing.md) | E2E tests, CI/CD testing |
| [Testing Migration & E2E](./testing-migration-e2e.md) | Testing strategy for migrations |

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
- **Write release notes**: `release-notes.md` → `github-interaction.md`
- **Publish a release**: `release-notes.md` → `social-media-release.md` → `github-interaction.md`
