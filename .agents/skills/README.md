# ChurchCRM Development Skills

This directory contains modular, task-focused development skills for AI coding agents working on ChurchCRM. Each skill covers a specific workflow or technical area.

## Directory Structure

```
.agents/skills/
├── churchcrm/          ← ChurchCRM-specific skills (34 files)
│   ├── SKILL.md        ← Entry point index for ChurchCRM skills
│   ├── api-development.md
│   ├── database-operations.md
│   └── ...             (all project-specific skills)
└── README.md           ← This file

~/.claude/skills/       ← Generic/upstream skills (shared across all projects)
├── gh-cli/             ← GitHub CLI comprehensive reference
├── interface-design/   ← Interface design patterns for dashboards and admin panels
├── php-best-practices/ ← Generic PHP 8.5+ best practices
└── web-design-guidelines/ ← Web Interface Guidelines compliance
```

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
- [AI Locale Translation](./churchcrm/locale-ai-translation.md)

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

## Generic / Upstream Skills

These skills are not ChurchCRM-specific and have been moved to `~/.claude/skills/` so they are available across **all projects** on this machine.

| Skill | Location | Description |
|-------|----------|-------------|
| `gh-cli` | `~/.claude/skills/gh-cli/` | GitHub CLI comprehensive reference |
| `interface-design` | `~/.claude/skills/interface-design/` | Interface design patterns for dashboards and admin panels |
| `php-best-practices` | `~/.claude/skills/php-best-practices/` | Generic PHP 8.5+, PSR standards, SOLID principles |
| `web-design-guidelines` | `~/.claude/skills/web-design-guidelines/` | Web Interface Guidelines compliance |

They are registered in `~/.claude/CLAUDE.md` for automatic discovery.

> **Note:** For generic guidance (language best practices, generic testing patterns, web-design templates), prefer upstream skills from https://skills.sh/ and add a small ChurchCRM-specific wrapper in `churchcrm/` noting repo-specific overrides.

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

1. Create a new skill folder (e.g., `my-skill/SKILL.md`)
2. Add an entry to the Generic Skills table above
3. For skills.sh upstream skills: `npx skills add https://skills.sh/ --skill <skill-name>`

---

**Last updated:** February 2026

