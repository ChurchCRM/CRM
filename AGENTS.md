# ChurchCRM Development Guide

Loaded by Codex every session. Other agents should follow the same files.

## Community feedback

Feature Proposals and RFC Reviews Discussions: [`docs/decisions/0003-rfc-community-prioritization.md`](docs/decisions/0003-rfc-community-prioritization.md).

## Task ownership

Read [the AI operating model](docs/contributing/ai-operating-model.md) for task owners, handoffs and approval boundaries.

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
| Tests | `ci-test-infrastructure.md` → PHP tests or `cypress-testing.md` |
| API | `api-development.md` → `service-layer.md` |

Always-on:

@.agents/skills/churchcrm/code-standards.md

@.agents/skills/churchcrm/git-workflow.md

Keep these heading names (this file links them):
[Task approval covers commit and push](.agents/skills/churchcrm/git-workflow.md),
[Mandatory Pre-Push Biome Check](.agents/skills/churchcrm/git-workflow.md),
[Strict vs Loose Comparisons](.agents/skills/churchcrm/code-standards.md).

## Environment

Canonical setup and verification: [DEVELOPING.md](DEVELOPING.md#shared-environment-setup).
Run `npm run env:check -- php` before PHP work, or
`npm run env:check -- integration --services` before integration tests.
Prepare dependencies, browsers, and service images while network access is
available. The readiness check uses installed tools and never installs packages.
Use the existing Compose stacks; Playwright captures still require the separate
worktree/project described in `marketing-visuals-pipeline.md`.

## Updating a skill

If a durable rule is missing or wrong, edit the existing card. Replace the sentence.
Do not append `<!-- learned: -->` essays. Do not paste catalogs from `src/`.

Plugin registry URL: `CentralServices::PLUGIN_REGISTRY_URL` (External branch today).

## Issue and PR readiness

Before triaging or implementing an issue, read [`docs/contributing/issue-readiness.md`](docs/contributing/issue-readiness.md). Check existing issues/PRs first, size work by review burden and risk, use epics for independently meaningful outcomes or cross-subsystem work, and treat `good first issue` as a maintainer certification. Keep issue intake friendly and PRs focused. Human maintainers remain accountable for decisions and approvals.

## Session hygiene

- PR reviews follow the token discipline in `.agents/skills/churchcrm/pr-review.md`: one fresh session per PR, explicit model routing for subagents, delta re-reviews keyed to the recorded head SHA — never a full re-read by default, and never poll CI with an agent loop

- Confirm `git branch --show-current` before committing docs
- Do not land cross-cutting skill edits on an unrelated feature branch
- After a push that fixes review comments, resolve every thread it fixed before you report done: check each against the pushed code, reply with the SHA when unclear, leave unfixed threads open (`git-workflow.md`)
- Do not merge or close issues unless the maintainer answers yes to a direct question
- User-visible behavior: open a docs tracking issue in ChurchCRM/docs.churchcrm.io (link it from the CRM issue when one exists). It does not block feature merge. Docs PRs wait for the release. See `docs/contributing/pr-review.md`
- Behavior changes: one PR carries the code and the Cypress specs. Add a Playwright capture spec (`playwright/workflows/`) only when it passes the value test in `marketing-visuals-pipeline.md`; a standard click-through flow earns none. Images, videos and the manifest are generated artifacts, never committed to this repo; visuals publishes them to cdn.churchcrm.io. Check captures in a git worktree on its own compose project, never on the dev checkout: the pipeline deletes `src/Include/Config.php`
- Follow the human-first writing rules in [`docs/contributing/issue-readiness.md`](docs/contributing/issue-readiness.md): BLUF first, concise goals and outcomes, details only when they help a decision.
- Write the current state: positive instructions, no history, no negating comments, migrate a legacy page when you touch it (`skill-architecture.md`)
- Write little. Names and tests carry intent. Comment only to explain intent or a non-obvious constraint; do not narrate the code.
- If the PR author is the same GitHub user the agent is acting as, do not post a review comment. Fix the branch.

## Tests

Run the failing spec in isolation before committing test fixes, when the session's environment can run it. Before a push, run the specs the change touches locally and check UI changes in a browser at desktop, tablet, and phone widths, when the environment has them; a session without a toolchain declares the checks not run in the PR body instead (`git-workflow.md`, `cypress-testing.md`). Do not assume Cypress / Yasumi fixture shapes — read the source. `cy.visit()` paths start with `/`.
