# ChurchCRM Development Skills

This directory contains modular, task-focused development skills for AI coding agents working on ChurchCRM. Each skill covers a specific workflow or technical area.

## Directory Structure

```
.agents/skills/
├── churchcrm/          ← ChurchCRM-specific skills (see churchcrm/SKILL.md for the index)
│   ├── SKILL.md        ← Entry point index for ChurchCRM skills
│   ├── api-development.md
│   ├── database-operations.md
│   └── ...             (all project-specific skills)
└── README.md           ← This file
```

These skills are repo-owned and apply to every agent family
(Claude, Copilot, Cursor, Grok, Codex, and anything else that
reads `.agents/skills/`). Do not assume a `~/.claude/` layout.

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
- [Tabler Components](./churchcrm/tabler-components.md)
- [Webpack & TypeScript](./churchcrm/webpack-typescript.md)
- [i18n & Localization](./churchcrm/i18n-localization.md)
- [Locale Translation Workflow](./churchcrm/locale-translation-workflow.md)
- [Currency Localization](./churchcrm/currency-localization.md)
- [Icon Management](./churchcrm/icon-management.md)
- [Responsive Design Guidelines](./churchcrm/responsive-design-guidelines.md)
- [Table Action Menu](./churchcrm/table-action-menu.md)
- [Timezone Handling](./churchcrm/timezone-handling.md)

### Security
- [Authorization & Security](./churchcrm/authorization-security.md)
- [Security Best Practices](./churchcrm/security-best-practices.md)

### Plugins
- [Plugin System](./churchcrm/plugin-system.md)
- [Plugin Development](./churchcrm/plugin-development.md)
- [Plugin Create (Community)](./churchcrm/plugin-create.md)
- [Plugin Migration (Core only)](./churchcrm/plugin-migration.md)
- [Plugin Security Scan](./churchcrm/plugin-security-scan.md)
- [Plugin Compliance (Admin Audit)](./churchcrm/plugin-compliance.md)

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
- [PR Review](./churchcrm/pr-review.md)
- [Development Workflows](./churchcrm/development-workflows.md)
- [Code Standards](./churchcrm/code-standards.md)
- [Wiki Documentation](./churchcrm/wiki-documentation.md)

## Agent-family notes

- Read `churchcrm/SKILL.md` first. That index is the source of truth.
- Also in this folder (not under `churchcrm/`): `milestone-sweep.md`, `pr-description-guidelines.md`, `pr-review-fix.md`.
- Product floor is **PHP 8.4+**. Do not document older PHP as supported.
- Never invent install counts, SaaS hosting, native apps, payment processing, or endpoints that are not in `src/`.
- Push only after the maintainer approves the diff. See `churchcrm/git-workflow.md`.
- Community plugins are current product, not future work.
- Approved plugin registry: bundled `src/plugins/approved-plugins.json` plus remote `https://raw.githubusercontent.com/ChurchCRM/CRM/Notifications/approved-plugins.json`. Do not use the dead `External` branch URL.

Generic language skills may live on a developer's own machine. ChurchCRM overrides always win when they conflict.

## How to Use These Skills

### For AI Agents

1. **Identify the workflow** — What type of work is being done?
2. **Load relevant skills** — Start with [`churchcrm/SKILL.md`](./churchcrm/SKILL.md) to find the right skill
3. **Follow the patterns** — Apply the specific guidance from each skill
4. **Combine when needed** — Multiple skills may be relevant for complex tasks

**Example workflows:**

- **Review a PR**: `pr-review.md` → `code-standards.md` → `security-best-practices.md` → `wiki-documentation.md`
- **New API endpoint**: `api-development.md` → `service-layer.md` → `slim-4-best-practices.md` → `security-best-practices.md` → `cypress-testing.md` → `git-workflow.md`
- **Migrate legacy page**: `routing-architecture.md` → `admin-mvc-migration.md` → `frontend-development.md` → `database-operations.md` → `git-workflow.md`
- **Fix security issue**: `security-best-practices.md` → `authorization-security.md` → `php-best-practices.md` → `git-workflow.md`
- **Add community plugin**: `plugin-system.md` → `plugin-development.md` → `plugin-create.md` → `plugin-security-scan.md` → `git-workflow.md`
- **Update a core plugin**: `plugin-system.md` → `plugin-development.md` → `plugin-migration.md` → `git-workflow.md`
- **Audit installed plugins**: `plugin-compliance.md`
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

1. Prefer a ChurchCRM-specific wrapper in `churchcrm/` over a machine-local skill.
2. Add the new file to `churchcrm/SKILL.md` and this README.

---

**Last updated:** September 2026
