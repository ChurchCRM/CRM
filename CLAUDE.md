# ChurchCRM Development Guide

Loaded by Claude Code every session. Other agents should follow the same files.

## Skills

Index: [`.agents/skills/churchcrm/SKILL.md`](.agents/skills/churchcrm/SKILL.md).
How to edit a skill: [`.agents/skills/churchcrm/skill-architecture.md`](.agents/skills/churchcrm/skill-architecture.md).
Load the card for the task. Do not load every skill.

| Task | Start |
|------|-------|
| Review a PR | `maintainer-review-gates.md` → `pr-review.md` |
| Address review comments | `.agents/skills/pr-review-fix.md` → `git-workflow.md` |
| Commit / PR | `git-workflow.md` |
| UI | `frontend-development.md` |
| i18n | `i18n-localization.md` |
| Security | `security-best-practices.md` → `authorization-security.md` |
| Plugin | `plugin-system.md` → `plugin-registry.md` |
| Tests | `cypress-testing.md` |
| API | `api-development.md` → `service-layer.md` |

Always-on:

@.agents/skills/churchcrm/code-standards.md

@.agents/skills/churchcrm/git-workflow.md

Keep these heading names (this file links them):
[Commit freely, push only on approval](.agents/skills/churchcrm/git-workflow.md),
[Mandatory Pre-Push Biome Check](.agents/skills/churchcrm/git-workflow.md),
[Strict vs Loose Comparisons](.agents/skills/churchcrm/code-standards.md).

## Updating a skill

If a durable rule is missing or wrong, edit the existing card. Replace the sentence.
Do not append `<!-- learned: -->` essays. Do not paste catalogs from `src/`.

Plugin registry URL: `CentralServices::PLUGIN_REGISTRY_URL` (External branch today).

## Session hygiene

- Confirm `git branch --show-current` before committing docs
- Do not land cross-cutting skill edits on an unrelated feature branch
- After a push, resolve threads the commit actually fixed (or comment with SHA + URLs)
- Do not merge or close issues unless the maintainer answers yes to a direct question
- User-visible behavior: open a docs tracking issue on ChurchCRM/CRM. It does not block feature merge. Docs PRs wait for the release. See `docs/contributing-pr-review.md`
- Write little. Names and tests carry intent. Do not narrate the next line in a comment.
- If the PR author is the same GitHub user the agent is acting as, do not post a review comment. Fix the branch.

## Tests

Run the failing spec in isolation before committing test fixes. Do not assume Cypress / Yasumi fixture shapes — read the source. `cy.visit()` paths start with `/`.
