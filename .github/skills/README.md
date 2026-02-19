# ChurchCRM Development Skills

Skills are stored in [.agents/skills/](../../.agents/skills/) for agent discovery.

- **ChurchCRM-specific skills** are committed to git in [.agents/skills/churchcrm/](../../.agents/skills/churchcrm/)
- **Upstream/generic skills** are managed via the [Skillshare plugin (`npx skills`)](https://skills.sh/) and are **not** committed to git

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

## Generic / Upstream Skills (Skillshare Plugin)

These skills are not ChurchCRM-specific. They are managed via the
[Skillshare plugin (`npx skills`)](https://skills.sh/) and are **not** stored in this repository.

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

**Policy:**
- Keep project-specific skills in `.agents/skills/churchcrm/` when they reference `src/`, exact
  `composer.json`/`package.json` versions, security rules, or CI requirements.
- For generic guidance (language best practices, testing patterns, web-design), use `npx skills add`
  to install from the Skillshare plugin and add a small wrapper in `churchcrm/` for any
  ChurchCRM-specific overrides.

