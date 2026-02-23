# ChurchCRM Development Guide

This file is loaded by Claude Code for every session. Follow these instructions for all work on this project.

---

## Skills System

Structured development skills live in `.agents/skills/`. **Always consult the relevant skill before starting work.**

- **Index**: [`.agents/skills/churchcrm/SKILL.md`](.agents/skills/churchcrm/SKILL.md) — use this to find the right skill for your task
- **Generic skills**: `gh-cli`, `interface-design`, `php-best-practices`, `web-design-guidelines` (see `.agents/skills/`)

### Skill Selection by Task

| Task type | Skills to read |
|-----------|---------------|
| New API endpoint | `api-development.md` → `service-layer.md` → `slim-4-best-practices.md` → `security-best-practices.md` |
| Migrate legacy page | `routing-architecture.md` → `admin-mvc-migration.md` → `frontend-development.md` |
| Database / ORM work | `database-operations.md` → `db-schema-migration.md` |
| UI / frontend changes | `bootstrap-adminlte.md` → `frontend-development.md` → `webpack-typescript.md` |
| i18n / translations | `i18n-localization.md` → `frontend-development.md` |
| Security issue | `security-best-practices.md` → `authorization-security.md` |
| Plugin work | `plugin-system.md` → `plugin-development.md` |
| Testing | `testing.md` → `cypress-testing.md` |
| Commit / PR | `git-workflow.md` → `github-interaction.md` |
| Refactor | `refactor.md` → `service-layer.md` |
| Performance | `performance-optimization.md` → `database-operations.md` |
| Configuration | `configuration-management.md` |

---

## Always-Apply Standards

These rules apply to **every code change** in this project.

@.agents/skills/churchcrm/code-standards.md

---

## Git & PR Workflow

@.agents/skills/churchcrm/git-workflow.md
