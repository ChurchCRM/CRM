# ChurchCRM Development Skills

This directory contains modular, task-focused development skills for AI coding agents working on ChurchCRM. Each skill covers a specific workflow or technical area.

## Directory Structure

```
.agents/skills/
├── churchcrm/          ← ChurchCRM-specific skills (committed to git)
│   ├── SKILL.md        ← Entry point index for ChurchCRM skills
│   ├── api-development.md
│   ├── database-operations.md
│   └── ...             (all project-specific skills)
└── README.md           ← This file
```

> **Upstream skills** (`gh-cli/`, `interface-design/`, `php-best-practices/`, `web-design-guidelines/`)
> are managed via the [Skillshare plugin (`npx skills`)](https://skills.sh/) and are **not** committed
> to this repository. Install them locally using the commands in the [Upstream Skills](#upstream-skills) section below.

## ChurchCRM Skills

All project-specific skills live in **[`churchcrm/`](./churchcrm/)**. See [`churchcrm/SKILL.md`](./churchcrm/SKILL.md) for the full index.

### Architecture & API
- [Routing & Architecture](./churchcrm/routing-architecture.md)
- [Slim 4 Best Practices](./churchcrm/slim-4-best-practices.md)
- [Slim MVC Skill](./churchcrm/slim-mvc-skill.md)
- [API Development](./churchcrm/api-development.md)
- [API Compatibility & Deprecation](./churchcrm/api-compatibility-and-deprecation.md)
- [Service Layer](./churchcrm/service-layer.md)
- [Configuration Management](./churchcrm/configuration-management.md)

### Database
- [Database Operations](./churchcrm/database-operations.md)
- [DB Schema Migration](./churchcrm/db-schema-migration.md)

### Frontend & UI
- [Frontend Development](./churchcrm/frontend-development.md)
- [Bootstrap 4.6.2 & AdminLTE](./churchcrm/bootstrap-adminlte.md)
- [Webpack & TypeScript](./churchcrm/webpack-typescript.md)
- [UI Development](./churchcrm/ui-development.md)
- [i18n & Localization](./churchcrm/i18n-localization.md)

### Security
- [Authorization & Security](./churchcrm/authorization-security.md)
- [Security Best Practices](./churchcrm/security-best-practices.md)

### Plugins
- [Plugin System](./churchcrm/plugin-system.md)
- [Plugin Development](./churchcrm/plugin-development.md)
- [Plugin Migration](./churchcrm/plugin-migration.md)

### Testing
- [Testing](./churchcrm/testing.md)
- [Cypress Testing](./churchcrm/cypress-testing.md)
- [Testing Migration & E2E](./churchcrm/testing-migration-e2e.md)

### MVC Migration
- [Admin MVC Migration](./churchcrm/admin-mvc-migration.md)
- [Groups MVC Guidelines](./churchcrm/groups-mvc-guidelines.md)
- [Refactor](./churchcrm/refactor.md)

### PHP & Performance
- [PHP Best Practices](./churchcrm/php-best-practices.md) (ChurchCRM-specific)
- [Modern PHP Frameworks](./churchcrm/modern-php-frameworks.md)
- [Performance Optimization](./churchcrm/performance-optimization.md)
- [Observability, Logging & Metrics](./churchcrm/observability-logging-metrics.md)

### Development Process
- [Git Workflow](./churchcrm/git-workflow.md)
- [GitHub Interaction](./churchcrm/github-interaction.md)
- [Development Workflows](./churchcrm/development-workflows.md)
- [Code Standards](./churchcrm/code-standards.md)
- [Wiki Documentation](./churchcrm/wiki-documentation.md)

## Upstream Skills

These skills are not ChurchCRM-specific. They are sourced from upstream skill packages and managed
via the [Skillshare plugin (`npx skills`)](https://skills.sh/). **They are not stored in this repo.**
Install them locally after cloning:

```bash
# Web Interface Guidelines compliance
npx skills add vercel-labs/agent-skills --skill web-design-guidelines

# Generic PHP 8.5+, PSR standards, SOLID principles
npx skills add https://skills.sh/ --skill php-best-practices

# GitHub CLI comprehensive reference
npx skills add https://skills.sh/ --skill gh-cli

# Interface design for dashboards and admin panels
npx skills add https://skills.sh/ --skill interface-design
```

> For generic guidance that overlaps with ChurchCRM patterns (e.g., PHP best practices), prefer the
> ChurchCRM-specific wrapper in `churchcrm/php-best-practices.md` which notes repo-specific overrides.

## How to Use These Skills

### For AI Agents

1. **Identify the workflow** — What type of work is being done?
2. **Load relevant skills** — Start with [`churchcrm/SKILL.md`](./churchcrm/SKILL.md) to find the right skill
3. **Follow the patterns** — Apply the specific guidance from each skill
4. **Combine when needed** — Multiple skills may be relevant for complex tasks

**Example workflows:**

- **New API endpoint**: `api-development.md` → `service-layer.md` → `slim-4-best-practices.md` → `security-best-practices.md` → `cypress-testing.md` → `git-workflow.md`
- **Migrate legacy page**: `routing-architecture.md` → `admin-mvc-migration.md` → `frontend-development.md` → `database-operations.md` → `git-workflow.md`
- **Fix security issue**: `security-best-practices.md` → `authorization-security.md` → `php-best-practices.md` → `git-workflow.md`
- **Add plugin**: `plugin-system.md` → `plugin-development.md` → `api-development.md` → `git-workflow.md`
- **Optimize queries**: `performance-optimization.md` → `database-operations.md` → `service-layer.md`
- **Add UI text**: `i18n-localization.md` → `frontend-development.md` → `git-workflow.md`

### For Human Developers

- **Quick reference** — Jump to the skill in `churchcrm/` covering your current task
- **Learning guide** — Read skills to understand ChurchCRM patterns
- **Quality check** — Use skills to verify your code follows standards
- **Pre-commit review** — Check relevant skills before submitting PRs

## Maintaining These Skills

### When to Update

- New patterns are established in the codebase
- Technology is upgraded (PHP version, frameworks, etc.)
- Common mistakes are identified
- Standards change or evolve

### Adding New ChurchCRM Skills

1. Create a new `.md` file in `churchcrm/`
2. Add an entry to `churchcrm/SKILL.md`
3. Add an entry to this README

### Adding Generic/Upstream Skills

Generic skills are managed via the Skillshare plugin. Do NOT add them as files to this repo.

1. Find the upstream skill on [skills.sh](https://skills.sh/) or a GitHub repository
2. Install it locally with `npx skills add <source> --skill <skill-name>`
3. Add the install command to the [Upstream Skills](#upstream-skills) section above
4. If ChurchCRM-specific overrides are needed, create a wrapper in `churchcrm/`

---

**Last updated:** February 2026

