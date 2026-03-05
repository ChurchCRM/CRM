# ChurchCRM Development Skills

⚠️ **Skills have been moved to [.agents/skills/](../../.agents/skills/)** for optimal agent discovery.

This directory now serves as a human-friendly index. All skill files are in `.agents/skills/` and committed to git for agent access on every clone.

## Where to Find Skills

- **For AI agents:** [.agents/skills/churchcrm/](../../.agents/skills/churchcrm/) — all ChurchCRM-specific skills
- **For humans:** Same `.md` files in `.agents/skills/churchcrm/` — use for reference, learning, and code reviews
- **Agent entry point:** [.github/copilot-instructions.md](../copilot-instructions.md) — pointer to skills and workflow guide
- **Full skill index:** [.agents/skills/churchcrm/SKILL.md](../../.agents/skills/churchcrm/SKILL.md)

## Why Split into Skills?

1. **Easier to navigate** - Find exactly what you need for your task
2. **Better context loading** - Agents load only relevant skills (e.g., API dev doesn't load plugin-system)
3. **Clearer organization** - Each skill is self-contained with all necessary context
4. **Easier to maintain** - Update specific skills without affecting others
5. **Agent-first** - Stored in `.agents/skills/` for automatic discovery by agent runtimes

## Available Skills

**All ChurchCRM-specific skills are in [.agents/skills/churchcrm/](../../.agents/skills/churchcrm/). Here's a quick index:**

### Architecture & API
- [Routing & Architecture](../../.agents/skills/churchcrm/routing-architecture.md)
- [Slim 4 Best Practices](../../.agents/skills/churchcrm/slim-4-best-practices.md)
- [API Development](../../.agents/skills/churchcrm/api-development.md)
- [Service Layer](../../.agents/skills/churchcrm/service-layer.md)
- [Configuration Management](../../.agents/skills/churchcrm/configuration-management.md)

### Database
- [Database Operations](../../.agents/skills/churchcrm/database-operations.md)
- [DB Schema Migration](../../.agents/skills/churchcrm/db-schema-migration.md)

### Frontend & UI
- [Frontend Development](../../.agents/skills/churchcrm/frontend-development.md)
- [Bootstrap 4.6.2 & AdminLTE](../../.agents/skills/churchcrm/bootstrap-adminlte.md)
- [Webpack & TypeScript](../../.agents/skills/churchcrm/webpack-typescript.md)
- [i18n & Localization](../../.agents/skills/churchcrm/i18n-localization.md)

### Security
- [Authorization & Security](../../.agents/skills/churchcrm/authorization-security.md)
- [Security Best Practices](../../.agents/skills/churchcrm/security-best-practices.md)

### Plugins
- [Plugin System](../../.agents/skills/churchcrm/plugin-system.md)
- [Plugin Development](../../.agents/skills/churchcrm/plugin-development.md)

### Testing
- [Testing](../../.agents/skills/churchcrm/testing.md)
- [Cypress Testing](../../.agents/skills/churchcrm/cypress-testing.md)

### PHP & Performance
- [PHP Best Practices](../../.agents/skills/churchcrm/php-best-practices.md)
- [Performance Optimization](../../.agents/skills/churchcrm/performance-optimization.md)

### Development Process
- [Git Workflow](../../.agents/skills/churchcrm/git-workflow.md)
- [Development Workflows](../../.agents/skills/churchcrm/development-workflows.md)
- [Code Standards](../../.agents/skills/churchcrm/code-standards.md)

## Generic / Upstream Skills

These skills are not ChurchCRM-specific and can be used across any project. They are sourced from upstream skill packages and maintained independently from ChurchCRM conventions. Use these for general-purpose guidance; if there are ChurchCRM-specific overrides, look for a corresponding file in `churchcrm/`.

> **Tip:** We recommend sourcing generic, upstream skills from https://skills.sh/ rather than duplicating them here. Use `npx skills add https://skills.sh/ --skill <skill-name>` to install an upstream skill, and add a small wrapper in `churchcrm/` if repo-specific overrides are needed.

| Folder | Description |
|--------|-------------|
| [`gh-cli/`](../../.agents/skills/gh-cli/) | GitHub CLI comprehensive reference |
| [`interface-design/`](../../.agents/skills/interface-design/) | Interface design patterns |
| [`php-best-practices/`](../../.agents/skills/php-best-practices/) | Generic PHP 8.5+, PSR standards, SOLID principles |
| [`web-design-guidelines/`](../../.agents/skills/web-design-guidelines/) | Web Interface Guidelines compliance |

## Using skills.sh (recommended for generic skills)

We recommend sourcing generic, upstream skills from https://skills.sh/ rather than duplicating them verbatim in this repo.

```bash
npx skills add https://skills.sh/ --skill <skill-name>
```

- **Recommended policy:**
  - Keep project-specific skills in `.agents/skills/churchcrm/` when they reference `src/`, exact `composer.json`/`package.json` versions, security rules, or CI requirements.
  - For generic guidance (language best practices, generic testing patterns, web-design templates), prefer linking to or installing the skills.sh copy and add a small wrapper in `churchcrm/` noting repo-specific overrides.

