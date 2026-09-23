# ChurchCRM Development Guide

This file is loaded by Claude Code for every session.

## Skills System

Index: [`.agents/skills/churchcrm/SKILL.md`](.agents/skills/churchcrm/SKILL.md).
How to edit a skill: [`.agents/skills/churchcrm/skill-architecture.md`](.agents/skills/churchcrm/skill-architecture.md).

Read the card for the task. Do not load every skill.

| Task | Start here |
|------|------------|
| Review a PR | `maintainer-review-gates.md` → `pr-review.md` |
| Commit / PR | `git-workflow.md` |
| UI | `frontend-development.md` |
| i18n | `i18n-localization.md` |
| Security | `security-best-practices.md` → `authorization-security.md` |
| Plugin | `plugin-system.md` → `plugin-registry.md` |
| Tests | `cypress-testing.md` |

## Always-Apply Standards

@.agents/skills/churchcrm/code-standards.md

## Git & PR Workflow

@.agents/skills/churchcrm/git-workflow.md

Named sections that must stay in `git-workflow.md`:

- [Commit freely, push only on approval](.agents/skills/churchcrm/git-workflow.md)
- [Mandatory Pre-Push Biome Check](.agents/skills/churchcrm/git-workflow.md)

Push only when the latest user message says push. Every push runs the full CI matrix.
`.githooks/pre-push` runs `npm run lint`. Never `--no-verify` unless the maintainer says so on a hotfix.

Do not merge or close issues unless the maintainer answers yes to a direct question.

## Updating a skill

When a durable rule is wrong or missing, edit the **existing card** — replace the sentence. Do not append `<!-- learned: -->` essays. Do not paste catalogs that live in `src/`.

Plugin registry URL: read `CentralServices::PLUGIN_REGISTRY_URL`, do not hard-code a branch name from memory.
