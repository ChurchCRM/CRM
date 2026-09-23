# ChurchCRM Development Guide

This file is loaded by Claude Code for every session. Follow these instructions for all work on this project.

---

## Skills System

Structured development skills live in `.agents/skills/`. **Consult the relevant skill before starting work.** Do not load every skill.

- **Index**: [`.agents/skills/churchcrm/SKILL.md`](.agents/skills/churchcrm/SKILL.md)
- **How to edit a skill**: [`.agents/skills/churchcrm/skill-architecture.md`](.agents/skills/churchcrm/skill-architecture.md)

### Skill Selection by Task

| Task type | Skills to read |
|-----------|---------------|
| Review a PR | `maintainer-review-gates.md` → `pr-review.md` |
| Address review comments | `../pr-review-fix.md` → `git-workflow.md` |
| New API endpoint | `api-development.md` → `service-layer.md` → `slim-4-best-practices.md` → `security-best-practices.md` |
| Migrate legacy page | `routing-architecture.md` → `admin-mvc-migration.md` → `frontend-development.md` |
| Database / ORM work | `database-operations.md` → `db-schema-migration.md` |
| UI / frontend changes | `responsive-design-guidelines.md` → `frontend-development.md` → `webpack-typescript.md` |
| Datetime / timezone work | `timezone-handling.md` |
| i18n / translations | `i18n-localization.md` |
| Security issue | `security-best-practices.md` → `authorization-security.md` |
| New community plugin | `plugin-system.md` → `plugin-registry.md` → `plugin-development.md` |
| Testing | `cypress-testing.md` |
| Commit / PR | `git-workflow.md` |

---

## Updating a skill

When a durable rule is missing or wrong, edit the **existing card**. Replace the sentence. Do not append `<!-- learned: -->` essays. Do not paste catalogs that live in `src/` or `package.json`.

Plugin registry URL: read `CentralServices::PLUGIN_REGISTRY_URL`. Do not hard-code a branch name from memory.

Named headings that this file links must keep those names:
[`git-workflow.md → Commit freely, push only on approval`](.agents/skills/churchcrm/git-workflow.md),
[`git-workflow.md → Mandatory Pre-Push Biome Check`](.agents/skills/churchcrm/git-workflow.md),
[`code-standards.md → Strict vs Loose Comparisons`](.agents/skills/churchcrm/code-standards.md).

---

## Always-Apply Standards

@.agents/skills/churchcrm/code-standards.md

---

## Git & PR Workflow

@.agents/skills/churchcrm/git-workflow.md

### Branch Hygiene

- Before committing skill/doc updates, verify `git branch --show-current`
- Never commit cross-cutting docs onto an unrelated feature branch

### Always Resolve PR Comments After Push

After every push, resolve threads the commit actually fixed. If the tool cannot resolve them, comment with the commit SHA and thread URLs.

Do not merge or close issues unless the maintainer answers yes to a direct question.

### User-visible changes need a docs tracking issue

Open a documentation issue on ChurchCRM/CRM. It does not block feature merge. Docs PRs merge only after that release ships. See `docs/contributing-pr-review.md`.

---

## Test Review & Commit Workflow

Test changes to test files before committing. Run the failing spec in isolation. Check the actual fixture — do not assume Yasumi/holiday shapes. `cy.visit()` paths start with `/`.

---

## Mandatory Pre-Commit Checklist

1. Change the code
2. `npm run lint`
3. Matching build (`build:webpack`, `build:php`, or `build`)
4. Show `git diff`
5. Wait for commit approval
6. Push only when the latest user message says push

Never `git push --no-verify` unless the maintainer authorizes a hotfix and the PR names the bypass.
